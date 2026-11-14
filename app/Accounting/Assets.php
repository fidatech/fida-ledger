<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AssetDepreciation;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Fixed assets. They are not stock: they never pass through Inventory.
 *
 *   Acquire      debit Fixed assets, credit what paid for it (cash, bank, a supplier) or Opening
 *                balances for an asset the company already owned.
 *   Depreciate   every month: debit Depreciation, credit Accumulated depreciation.
 *                Straight line: (cost - residual value) / useful life in months.
 *   Dispose      take the cost and its accumulated depreciation out, bring in the proceeds, and
 *                post the difference to "Gain or loss on asset disposal".
 */
class Assets
{
    /** @param  array{name: string, asset_category_id?: ?int, acquired_on: string, cost: mixed, residual_value?: mixed, useful_life_months: int, paid_from_account_id?: ?int, location?: ?string, assigned_to?: ?string, notes?: ?string}  $data */
    public static function acquire(Company $company, array $data): FixedAsset
    {
        return DB::transaction(function () use ($company, $data) {
            $cost = Dec::round($data['cost']);
            $residual = Dec::round($data['residual_value'] ?? '0');
            if (! Dec::isPositive($cost) || Dec::isNegative($residual) || Dec::cmp($residual, $cost) > 0) {
                throw new UserError(__('The cost must be more than zero and the residual value cannot be more than the cost.'));
            }

            // no account chosen: the company owned it before it started these books
            $paidFrom = ! empty($data['paid_from_account_id'])
                ? Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->whereIn('kind', ['cash', 'bank', 'supplier'])->find($data['paid_from_account_id'])
                : Account::system($company->id, 'opening_balances');
            if (! $paidFrom) {
                throw new UserError(__('Choose the cash, bank or supplier account that paid for the asset.'));
            }
            if ($paidFrom->currency !== null && $paidFrom->currency !== $company->base_currency) {
                throw new UserError(__('Pay for an asset from an account in :currency.', ['currency' => $company->base_currency]));
            }

            $asset = new FixedAsset([
                'number' => Sequence::next($company->id, 'fixed_asset'), 'name' => $data['name'], 'asset_category_id' => $data['asset_category_id'] ?? null,
                'acquired_on' => substr((string) $data['acquired_on'], 0, 10), 'cost' => $cost, 'residual_value' => $residual, 'useful_life_months' => (int) $data['useful_life_months'],
                'location' => $data['location'] ?? null, 'assigned_to' => $data['assigned_to'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $asset->company_id = $company->id;
            $asset->save();

            $entry = Ledger::post($company, ['date' => $asset->acquired_on->toDateString(), 'description' => __('Asset acquired').' · '.$asset->name, 'reference' => $asset->code(), 'source' => $asset, 'lines' => [
                ['account' => Account::system($company->id, 'fixed_assets'), 'debit' => $cost],
                ['account' => $paidFrom, 'credit' => $cost],
            ]]);
            $asset->update(['acquisition_entry_id' => $entry->id]);

            return $asset;
        });
    }

    /**
     * Depreciate every active asset for one month ("2026-11"). Running it again for the same
     * month does nothing: each asset has at most one row per month.
     *
     * @return array{assets: int, amount: string}
     */
    public static function depreciate(Company $company, string $period): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new UserError(__('Choose a month.'));
        }
        $monthEnd = date('Y-m-t', strtotime($period.'-01'));

        return DB::transaction(function () use ($company, $period, $monthEnd) {
            $assets = FixedAsset::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'active')
                ->where('acquired_on', '<=', $monthEnd)
                ->whereNotExists(fn ($q) => $q->from('asset_depreciations')->whereColumn('asset_depreciations.fixed_asset_id', 'fixed_assets.id')->where('period', $period))
                ->lockForUpdate()->get();

            $amounts = [];
            $total = '0';
            foreach ($assets as $asset) {
                // never below the residual value: the last month takes what is left
                $left = Dec::sub(Dec::sub($asset->cost, $asset->residual_value), $asset->depreciated);
                $amount = Dec::cmp($asset->monthly(), $left) < 0 ? $asset->monthly() : $left;
                if (Dec::isPositive($amount)) {
                    $amounts[$asset->id] = $amount;
                    $total = Dec::add($total, $amount);
                }
            }
            if (! $amounts) {
                return ['assets' => 0, 'amount' => '0.0000'];
            }

            $entry = Ledger::post($company, ['date' => $monthEnd, 'description' => __('Depreciation').' · '.$period, 'reference' => 'DEP-'.$period, 'lines' => [
                ['account' => Account::system($company->id, 'depreciation'), 'debit' => $total],
                ['account' => Account::system($company->id, 'accumulated_depreciation'), 'credit' => $total],
            ]]);
            foreach ($assets as $asset) {
                if (! isset($amounts[$asset->id])) {
                    continue;
                }
                $row = new AssetDepreciation(['fixed_asset_id' => $asset->id, 'period' => $period, 'amount' => $amounts[$asset->id], 'journal_entry_id' => $entry->id]);
                $row->company_id = $company->id;
                $row->save();
                $asset->update(['depreciated' => Dec::add($asset->depreciated, $amounts[$asset->id])]);
            }

            return ['assets' => count($amounts), 'amount' => $total];
        });
    }

    /** Sell or scrap an asset. $proceeds may be zero (scrapped); then no account is needed. */
    public static function dispose(FixedAsset $asset, string $date, mixed $proceeds, ?int $receivedIntoAccountId): FixedAsset
    {
        return DB::transaction(function () use ($asset, $date, $proceeds, $receivedIntoAccountId) {
            $asset = FixedAsset::withoutGlobalScope('company')->lockForUpdate()->findOrFail($asset->id);
            if ($asset->status !== 'active') {
                throw new UserError(__('This asset was already disposed of.'));
            }
            $company = Company::findOrFail($asset->company_id);
            $proceeds = Dec::round($proceeds ?? '0');
            if (Dec::isNegative($proceeds)) {
                throw new UserError(__('The proceeds cannot be negative.'));
            }

            $lines = [];
            if (Dec::isPositive($proceeds)) {
                $account = Account::withoutGlobalScope('company')->where('company_id', $company->id)->postable()->cashOrBank()->where('currency', $company->base_currency)->find($receivedIntoAccountId);
                if (! $account) {
                    throw new UserError(__('Choose the cash or bank account (in :currency) that received the money.', ['currency' => $company->base_currency]));
                }
                $lines[] = ['account' => $account, 'debit' => $proceeds];
            }
            if (Dec::isPositive($asset->depreciated)) {
                $lines[] = ['account' => Account::system($company->id, 'accumulated_depreciation'), 'debit' => $asset->depreciated];
            }
            $lines[] = ['account' => Account::system($company->id, 'fixed_assets'), 'credit' => $asset->cost];
            // proceeds above the book value are a gain; below it, a loss
            $gain = Dec::sub($proceeds, $asset->bookValue());
            if (! Dec::isZero($gain)) {
                $lines[] = ['account' => Account::system($company->id, 'asset_disposal'), Dec::isPositive($gain) ? 'credit' : 'debit' => Dec::abs($gain)];
            }

            $entry = Ledger::post($company, ['date' => substr($date, 0, 10), 'description' => __('Asset disposed').' · '.$asset->name, 'reference' => $asset->code(), 'source' => $asset, 'idempotency_key' => 'asset-disposal-'.$asset->id, 'lines' => $lines]);
            $asset->update(['status' => 'disposed', 'disposed_on' => substr($date, 0, 10), 'disposal_proceeds' => $proceeds, 'disposal_entry_id' => $entry->id]);

            return $asset;
        });
    }
}
