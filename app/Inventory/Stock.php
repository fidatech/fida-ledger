<?php

namespace App\Inventory;

use App\Accounting\Ledger;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stock quantities and their cost. One costing method everywhere: moving weighted average
 * per product and warehouse, in the base currency.
 *
 *   in()   adds a quantity at a given unit cost: the average moves.
 *   out()  takes a quantity at the current average: the average stays.
 *
 * Stock can never go below zero. in() and out() only move stock; the module that calls them
 * posts the journal entry (a purchase receipt, a delivery ...), so stock and ledger change in
 * the same transaction and each event is recorded once.
 */
class Stock
{
    /** @param  array{date: string, type: string, source?: ?Model, reference?: ?string, description?: ?string, journal_entry_id?: ?int}  $meta */
    public static function in(Company $company, Product $product, int $warehouseId, mixed $quantity, mixed $unitCost, array $meta): StockMovement
    {
        $quantity = Dec::round($quantity);
        if (! Dec::isPositive($quantity) || Dec::isNegative($unitCost)) {
            throw new UserError(__('The quantity must be more than zero.'));
        }
        $value = Dec::mul($quantity, $unitCost);

        return DB::transaction(function () use ($company, $product, $warehouseId, $quantity, $value, $meta) {
            $level = self::level($company, $product, $warehouseId);
            $level->update(['quantity' => Dec::add($level->quantity, $quantity), 'value' => Dec::add($level->value, $value)]);

            return self::move($company, $product, $warehouseId, $quantity, $value, $meta);
        });
    }

    /** Takes stock out at the average cost. The movement's value is negative; use Dec::abs() for the cost. */
    public static function out(Company $company, Product $product, int $warehouseId, mixed $quantity, array $meta): StockMovement
    {
        $quantity = Dec::round($quantity);
        if (! Dec::isPositive($quantity)) {
            throw new UserError(__('The quantity must be more than zero.'));
        }

        return DB::transaction(function () use ($company, $product, $warehouseId, $quantity, $meta) {
            $level = self::level($company, $product, $warehouseId);
            if (Dec::cmp($quantity, $level->quantity) > 0) {
                throw new UserError(__('Not enough :product in stock: :have on hand, :want needed.', [
                    'product' => $product->name, 'have' => self::qty($level->quantity), 'want' => self::qty($quantity),
                ]));
            }
            // the last unit takes whatever value is left, so quantity zero always means value zero
            $value = Dec::cmp($quantity, $level->quantity) === 0 ? Dec::round($level->value) : Dec::div(Dec::mul($level->value, $quantity, 12), $level->quantity);
            $level->update(['quantity' => Dec::sub($level->quantity, $quantity), 'value' => Dec::sub($level->value, $value)]);

            return self::move($company, $product, $warehouseId, Dec::neg($quantity), Dec::neg($value), $meta);
        });
    }

    /** Move stock between two warehouses at its cost. No income, no expense, no journal entry: inventory is one account. */
    public static function transfer(Company $company, Product $product, int $fromId, int $toId, mixed $quantity, string $date, ?string $description = null): void
    {
        if ($fromId === $toId) {
            throw new UserError(__('Choose two different warehouses.'));
        }
        DB::transaction(function () use ($company, $product, $fromId, $toId, $quantity, $date, $description) {
            $out = self::out($company, $product, $fromId, $quantity, ['date' => $date, 'type' => 'transfer_out', 'description' => $description]);
            $unitCost = Dec::div(Dec::abs($out->value), Dec::abs($out->quantity), 8);
            $level = self::level($company, $product, $toId);
            $level->update(['quantity' => Dec::add($level->quantity, Dec::abs($out->quantity)), 'value' => Dec::add($level->value, Dec::abs($out->value))]);
            self::move($company, $product, $toId, Dec::abs($out->quantity), Dec::abs($out->value), ['date' => $date, 'type' => 'transfer_in', 'description' => $description, 'reference' => 'cost '.$unitCost]);
        });
    }

    /**
     * Correct the stock of a product: after a count, for damage, or to enter what was on hand
     * when the company started ('opening').
     *   More stock:  debit Inventory, credit Inventory adjustments (or Opening balances).
     *   Less stock:  debit Inventory adjustments, credit Inventory.
     *
     * @param  mixed  $change  positive adds (at $unitCost), negative removes (at the average cost)
     */
    public static function adjust(Company $company, Product $product, int $warehouseId, mixed $change, mixed $unitCost, string $date, ?string $reason = null, bool $opening = false): ?StockMovement
    {
        if (Dec::isZero($change)) {
            return null;
        }

        return DB::transaction(function () use ($company, $product, $warehouseId, $change, $unitCost, $date, $reason, $opening) {
            $meta = ['date' => $date, 'type' => $opening ? 'opening' : 'adjustment', 'description' => $reason];
            $movement = Dec::isPositive($change)
                ? self::in($company, $product, $warehouseId, $change, $unitCost ?? '0', $meta)
                : self::out($company, $product, $warehouseId, Dec::abs($change), $meta);

            $value = Dec::abs($movement->value);
            if (! Dec::isZero($value)) {
                $inventory = Account::system($company->id, 'inventory');
                $other = Account::system($company->id, $opening ? 'opening_balances' : 'inventory_adjustments');
                $text = ($opening ? __('Opening stock') : __('Stock adjustment')).' · '.$product->name.($reason ? ' · '.$reason : '');
                $up = Dec::isPositive($change);
                $entry = Ledger::post($company, ['date' => $date, 'description' => $text, 'source' => $movement, 'lines' => [
                    ['account' => $up ? $inventory : $other, 'debit' => $value],
                    ['account' => $up ? $other : $inventory, 'credit' => $value],
                ]]);
                $movement->update(['journal_entry_id' => $entry->id]);
            }

            return $movement;
        });
    }

    /** The warehouse used when a document names none: the company's first active one, created if there is none. */
    public static function defaultWarehouse(Company $company): Warehouse
    {
        $warehouse = Warehouse::withoutGlobalScope('company')->where('company_id', $company->id)->where('is_active', true)->orderBy('id')->first();
        if (! $warehouse) {
            $warehouse = new Warehouse(['name' => 'Main warehouse']);
            $warehouse->company_id = $company->id;
            $warehouse->save();
        }

        return $warehouse;
    }

    /** A quantity without trailing zeros: "12.5000" is shown as "12.5". */
    public static function qty(mixed $quantity): string
    {
        $text = Dec::round($quantity);

        return rtrim(rtrim($text, '0'), '.') ?: '0';
    }

    private static function level(Company $company, Product $product, int $warehouseId): StockLevel
    {
        if ((int) $product->company_id !== $company->id || ! $product->isStocked()) {
            throw new UserError(__(':product is not kept in stock.', ['product' => $product->name]));
        }
        if (! Warehouse::withoutGlobalScope('company')->where('company_id', $company->id)->whereKey($warehouseId)->exists()) {
            throw new UserError(__('Choose a warehouse.'));
        }
        $key = ['company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouseId];
        StockLevel::withoutGlobalScope('company')->firstOrCreate($key);

        // locked until the transaction ends: two sales of the last unit cannot both succeed
        return StockLevel::withoutGlobalScope('company')->where($key)->lockForUpdate()->firstOrFail();
    }

    private static function move(Company $company, Product $product, int $warehouseId, string $quantity, string $value, array $meta): StockMovement
    {
        $movement = new StockMovement([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'date' => substr((string) $meta['date'], 0, 10),
            'type' => $meta['type'],
            'quantity' => $quantity,
            'value' => $value,
            'source_type' => isset($meta['source']) ? $meta['source']->getMorphClass() : null,
            'source_id' => isset($meta['source']) ? $meta['source']->getKey() : null,
            'reference' => $meta['reference'] ?? null,
            'description' => $meta['description'] ?? null,
            'journal_entry_id' => $meta['journal_entry_id'] ?? null,
            'created_by' => auth('web')->id(),
        ]);
        $movement->company_id = $company->id;
        $movement->save();

        return $movement;
    }
}
