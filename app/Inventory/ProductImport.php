<?php

namespace App\Inventory;

use App\Exceptions\UserError;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Support\Dec;
use App\Support\Modules;
use Illuminate\Support\Facades\DB;

/**
 * Brings a list of products in from a CSV file (saved from Excel).
 *
 * A row whose code (SKU) or, without a code, whose name is already in the catalogue updates
 * that product; any other row adds one. Categories and units named in the file are created.
 * The whole file is saved or nothing is: one bad row stops the import and names the row.
 *
 * Opening stock is taken for NEW products only, so importing the same file twice never
 * doubles the stock. It is posted like any opening stock: Inventory against Opening balances.
 */
class ProductImport
{
    public const MAX_ROWS = 2000;

    /** column => the headings accepted for it (lower case) */
    public const COLUMNS = [
        'name' => ['name', 'product', 'product name', 'item'],
        'sku' => ['sku', 'code', 'item code', 'product code'],
        'barcode' => ['barcode', 'bar code', 'ean', 'upc'],
        'category' => ['category', 'group'],
        'unit' => ['unit', 'uom'],
        'sale_price' => ['sale price', 'selling price', 'sale_price', 'price'],
        'purchase_price' => ['purchase price', 'cost', 'cost price', 'purchase_price', 'buying price'],
        'tax_rate' => ['tax', 'tax %', 'tax rate', 'tax_rate', 'vat'],
        'reorder_level' => ['reorder level', 'reorder_level', 'minimum', 'min stock'],
        'quantity' => ['quantity', 'qty', 'stock', 'opening stock', 'on hand'],
    ];

    /**
     * @param  int|null  $warehouseId  where opening stock goes; null (or the Inventory module off) ignores the quantity column
     * @return array{added: int, updated: int, stocked: int}
     */
    public static function import(Company $company, string $path, ?int $warehouseId, string $date): array
    {
        [$columns, $rows] = self::read($path);
        $withStock = $warehouseId !== null && $columns['quantity'] !== null && Modules::active($company, 'inventory');

        return DB::transaction(function () use ($company, $columns, $rows, $withStock, $warehouseId, $date) {
            $categories = ProductCategory::withoutGlobalScope('company')->where('company_id', $company->id)->get()->keyBy(fn ($category) => mb_strtolower($category->name));
            $units = Unit::withoutGlobalScope('company')->where('company_id', $company->id)->get()->keyBy(fn ($unit) => mb_strtolower($unit->name));
            $catalogue = Product::withoutGlobalScope('company')->where('company_id', $company->id)->where('type', Product::PRODUCT)->get();
            $bySku = $catalogue->filter(fn ($product) => filled($product->sku))->keyBy(fn ($product) => mb_strtolower($product->sku));
            $byName = $catalogue->keyBy(fn ($product) => mb_strtolower($product->name));
            $result = ['added' => 0, 'updated' => 0, 'stocked' => 0];

            foreach ($rows as $number => $row) {
                $cell = fn (string $column) => $columns[$column] === null ? null : trim((string) ($row[$columns[$column]] ?? ''));
                $name = (string) $cell('name');
                if ($name === '' || mb_strlen($name) > 255) {
                    throw new UserError(__('Row :row: the name is missing or too long.', ['row' => $number]));
                }
                $data = ['name' => $name];
                foreach (['sku', 'barcode'] as $text) {
                    $value = $cell($text);
                    if ($value !== null && $value !== '') {
                        if (mb_strlen($value) > 60) {
                            throw new UserError(__('Row :row: ":value" is too long.', ['row' => $number, 'value' => mb_substr($value, 0, 30)]));
                        }
                        $data[$text] = $value;
                    }
                }
                foreach (['sale_price', 'purchase_price', 'tax_rate', 'reorder_level'] as $numeric) {
                    $value = $cell($numeric);
                    if ($value !== null && $value !== '') {
                        $data[$numeric] = self::number($value, $number);
                    }
                }
                if (isset($data['tax_rate']) && Dec::cmp($data['tax_rate'], '100') > 0) {
                    throw new UserError(__('Row :row: ":value" is not a valid number.', ['row' => $number, 'value' => $data['tax_rate']]));
                }
                if (($category = (string) $cell('category')) !== '') {
                    $key = mb_strtolower($category);
                    $categories[$key] ??= self::make(new ProductCategory(['name' => mb_substr($category, 0, 255)]), $company);
                    $data['product_category_id'] = $categories[$key]->id;
                }
                if (($unit = (string) $cell('unit')) !== '') {
                    $key = mb_strtolower($unit);
                    $units[$key] ??= self::make(new Unit(['name' => mb_substr($unit, 0, 60)]), $company);
                    $data['unit_id'] = $units[$key]->id;
                }

                $existing = isset($data['sku']) ? ($bySku[mb_strtolower($data['sku'])] ?? null) : ($byName[mb_strtolower($name)] ?? null);
                if ($existing) {
                    $existing->update($data);
                    $result['updated']++;

                    continue;
                }

                $quantity = $withStock && (string) $cell('quantity') !== '' ? self::number((string) $cell('quantity'), $number) : '0';
                $product = self::make(new Product($data + ['type' => Product::PRODUCT, 'track_stock' => true, 'is_active' => true, 'sale_price' => 0, 'purchase_price' => 0, 'tax_rate' => 0, 'reorder_level' => 0]), $company);
                $byName[mb_strtolower($name)] = $product;
                if (isset($data['sku'])) {
                    $bySku[mb_strtolower($data['sku'])] = $product;
                }
                $result['added']++;

                if (Dec::isPositive($quantity)) {
                    Stock::adjust($company, $product, $warehouseId, $quantity, $product->purchase_price, $date, __('Imported'), true);
                    $result['stocked']++;
                }
            }

            return $result;
        });
    }

    /** @return array{0: array<string, ?int>, 1: array<int, array>} the column positions, and the rows keyed by their line number in the file */
    private static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = $handle ? fgetcsv($handle) : false;
        if (! $header) {
            throw new UserError(__('The file is empty or is not a CSV file.'));
        }
        $header = array_map(fn ($name) => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))), $header);
        $columns = [];
        foreach (self::COLUMNS as $column => $names) {
            $columns[$column] = null;
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true)) {
                    $columns[$column] = $index;
                    break;
                }
            }
        }
        if ($columns['name'] === null) {
            fclose($handle);
            throw new UserError(__('The file needs a "name" column in its first row.'));
        }

        $rows = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if ($row === [null] || trim(implode('', array_map('strval', $row))) === '') {
                continue; // an empty line
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw new UserError(__('The file has more than :max products. Split it into smaller files.', ['max' => self::MAX_ROWS]));
            }
            $rows[$line] = $row;
        }
        fclose($handle);
        if (! $rows) {
            throw new UserError(__('The file has no products.'));
        }

        return [$columns, $rows];
    }

    /** A number of the file ("1,250.50") as an exact decimal; never negative. */
    private static function number(string $value, int $row): string
    {
        $clean = str_replace([',', ' '], '', $value);
        if (! preg_match('/^\d{1,12}(\.\d{1,8})?$/', $clean)) {
            throw new UserError(__('Row :row: ":value" is not a valid number.', ['row' => $row, 'value' => mb_substr($value, 0, 30)]));
        }

        return Dec::round($clean);
    }

    private static function make($model, Company $company)
    {
        $model->company_id = $company->id;
        $model->save();

        return $model;
    }
}
