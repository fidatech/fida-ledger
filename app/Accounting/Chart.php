<?php

namespace App\Accounting;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Sequence;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/** Opens accounts: the chart a new company starts with, and the accounts users add later. */
class Chart
{
    /** The starting chart of accounts (config/ledger.php 'chart') and a first cash account. */
    public static function install(Company $company): void
    {
        $rows = config('ledger.chart');
        $parents = array_filter(array_column($rows, 3));
        $ids = [];

        foreach ($rows as [$code, $name, $class, $parent, $kind, $systemKey]) {
            $account = new Account([
                'parent_id' => $parent ? $ids[$parent] : null,
                'code' => $code,
                'name' => $name,
                'class' => $class,
                'kind' => $kind,
                'is_group' => in_array($code, $parents, true),
                'system_key' => $systemKey,
            ]);
            $account->company_id = $company->id;
            $account->save();
            $ids[$code] = $account->id;
        }

        self::open($company, ['kind' => 'cash', 'name' => 'Cash', 'currency' => $company->base_currency]);
    }

    /**
     * Open an account of one of the kinds users can add (config/ledger.php 'kinds').
     *
     * @param  array{kind: string, name: string, currency?: ?string, phone?: ?string, email?: ?string, address?: ?string, notes?: ?string,
     *     opening_amount?: mixed, opening_side?: ?string, opening_currency?: ?string, opening_rate?: mixed, opening_date?: ?string}  $data
     */
    public static function open(Company $company, array $data): Account
    {
        $kind = config('ledger.kinds.'.$data['kind']);
        if (! $kind) {
            throw new UserError(__('This kind of account cannot be opened here.'));
        }

        return DB::transaction(function () use ($company, $data, $kind) {
            $group = Account::withoutGlobalScope('company')->where('company_id', $company->id)->where('code', $kind['group'])->firstOrFail();

            $account = new Account([
                'parent_id' => $group->id,
                'code' => $group->code.'-'.str_pad((string) Sequence::next($company->id, 'account.'.$group->code), 4, '0', STR_PAD_LEFT),
                'name' => $data['name'],
                'class' => $kind['class'],
                'kind' => $data['kind'],
                'currency' => ! empty($kind['currency']) ? strtoupper((string) ($data['currency'] ?? $company->base_currency)) : null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $account->company_id = $company->id;
            $account->save();

            if (Dec::valid($data['opening_amount'] ?? null) && Dec::isPositive($data['opening_amount'])) {
                self::openingBalance($company, $account, $data);
            }

            return $account;
        });
    }

    /**
     * The balance an account starts with, posted against "Opening balances" (equity).
     * 'opening_side' says on which side the amount stands; left out, it is the side on which the account grows.
     */
    private static function openingBalance(Company $company, Account $account, array $data): void
    {
        $side = in_array($data['opening_side'] ?? null, ['debit', 'credit'], true) ? $data['opening_side'] : $account->normalSide();
        $other = $side === 'debit' ? 'credit' : 'debit';
        $currency = strtoupper((string) ($account->currency ?? $data['opening_currency'] ?? $company->base_currency));
        $rate = $data['opening_rate'] ?? null;
        $amount = Dec::round($data['opening_amount']);

        Ledger::post($company, [
            'date' => $data['opening_date'] ?? $company->today(),
            'description' => __('Opening balance of :account', ['account' => $account->name]),
            'source' => $account,
            'lines' => [
                ['account' => $account, $side => $amount, 'currency' => $currency, 'rate' => $rate],
                ['account' => Account::system($company->id, 'opening_balances'), $other => $amount, 'currency' => $currency, 'rate' => $rate],
            ],
        ]);
    }
}
