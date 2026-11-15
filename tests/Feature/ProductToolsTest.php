<?php

namespace Tests\Feature;

use App\Accounting\Balances;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Support\Barcode;
use App\Support\Dec;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Importing products from a file, the count sheet and barcode labels. Base currency AFN. */
class ProductToolsTest extends TestCase
{
    private function file(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $csv);
    }

    private function inventory(): string
    {
        return Balances::baseByAccount(company_id(), null, null)->get(Account::system(company_id(), 'inventory')->id, '0.0000');
    }

    public function test_code_128_follows_the_standard_table(): void
    {
        // checked against an independent implementation of Code 128 (code set B)
        $this->assertSame('2112141323111321311221321131412122222221222131312331112', Barcode::widths('FL-0001'));
        $this->assertSame([104, 33, 33, 106], Barcode::symbols('A')); // start B, "A", check (104 + 33) % 103, stop
        $this->assertNull(Barcode::widths('محصول')); // not printable as Code 128
        $this->assertNull(Barcode::widths(''));
        $this->assertStringStartsWith('<svg', Barcode::svg('FL-0001'));
        $this->assertSame('', Barcode::svg(null));
    }

    public function test_products_are_imported_once_with_their_opening_stock(): void
    {
        $company = $this->activeCompany();
        $warehouse = Stock::defaultWarehouse($company);
        $csv = "Name,Code,Barcode,Category,Unit,Selling price,Purchase price,Tax %,Reorder level,Quantity\n"
            ."Green tea,T-1,8690000000011,Drinks,Box,\"1,500\",100,0,5,20\n"
            ."Sugar,S-1,,Food,Kg,80,60,,,\n";

        $this->post('/products/import', ['file' => $this->file($csv), 'warehouse_id' => $warehouse->id, 'date' => '2026-11-01'])->assertSessionHas('success');

        $tea = Product::where('sku', 'T-1')->firstOrFail();
        $this->assertSame(0, Dec::cmp($tea->sale_price, '1500'));
        $this->assertSame('Drinks', $tea->category->name);
        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(2, Unit::count());
        // 20 boxes at 100: stock and the Inventory account agree
        $this->assertSame(0, Dec::cmp(StockLevel::where('product_id', $tea->id)->value('quantity'), '20'));
        $this->assertSame(0, Dec::cmp($this->inventory(), '2000'));

        // the same file again: prices are updated, nothing is added and stock is not doubled
        $again = str_replace('"1,500"', '1600', $csv);
        $this->post('/products/import', ['file' => $this->file($again), 'warehouse_id' => $warehouse->id, 'date' => '2026-11-02'])->assertSessionHas('success');
        $this->assertSame(2, Product::count());
        $this->assertSame(0, Dec::cmp($tea->fresh()->sale_price, '1600'));
        $this->assertSame(0, Dec::cmp($this->inventory(), '2000'));
    }

    public function test_one_bad_row_saves_nothing(): void
    {
        $this->activeCompany();

        $this->post('/products/import', ['file' => $this->file("Name,Selling price\nGood,10\nBad,abc\n")])->assertSessionHas('danger');
        $this->assertSame(0, Product::count());

        $this->post('/products/import', ['file' => $this->file("Code,Price\nX,1\n")])->assertSessionHas('danger'); // no name column
        $this->assertSame(0, Product::count());
    }

    public function test_the_count_sheet_adjusts_only_what_was_counted(): void
    {
        $company = $this->activeCompany();
        $warehouse = Stock::defaultWarehouse($company);
        $this->post('/products/import', ['file' => $this->file("Name,Purchase price,Quantity\nTea,100,20\nSugar,50,10\n"), 'warehouse_id' => $warehouse->id, 'date' => '2026-11-01']);
        $tea = Product::where('name', 'Tea')->firstOrFail();
        $sugar = Product::where('name', 'Sugar')->firstOrFail();

        $this->get('/inventory/count-sheet')->assertOk()->assertSee('Tea')->assertSee('Sugar');

        // 18 teas were found; sugar was not counted
        $this->post('/inventory/count-sheet', ['warehouse_id' => $warehouse->id, 'date' => '2026-11-10', 'counted' => [$tea->id => '18', $sugar->id => '']])->assertSessionHas('success');
        $this->assertSame(0, Dec::cmp(StockLevel::where('product_id', $tea->id)->value('quantity'), '18'));
        $this->assertSame(0, Dec::cmp(StockLevel::where('product_id', $sugar->id)->value('quantity'), '10'));
        // 2500 came in; two teas at 100 are gone
        $this->assertSame(0, Dec::cmp($this->inventory(), '2300'));
        $this->assertSame(0, Dec::cmp(StockLevel::sum('value'), $this->inventory()));

        $this->post('/inventory/count-sheet', ['warehouse_id' => $warehouse->id, 'date' => '2026-11-10', 'counted' => [$tea->id => '-1']])->assertSessionHasErrors('counted.'.$tea->id);
    }

    public function test_labels_print_for_products_with_a_code_and_tools_follow_the_module(): void
    {
        $this->activeCompany();
        $coded = Product::create(['type' => 'product', 'name' => 'Tea', 'sku' => 'T-1', 'sale_price' => '150']);
        Product::create(['type' => 'product', 'name' => 'No code']);

        $this->get('/products/labels')->assertOk()->assertSee('T-1')->assertSee('No barcode or code');
        $this->get('/products/labels?print=1&copies['.$coded->id.']=3')->assertOk()->assertSee('<svg', false)->assertSee('T-1');
        $this->get('/products/import')->assertOk();
        $this->get('/products/import/template')->assertOk();

        // a Starter company has no catalogue, so none of its tools either
        $this->workIn($this->activeCompany('Small', Plan::STARTER));
        $this->get('/products/labels')->assertNotFound();
        $this->get('/products/import')->assertNotFound();
        $this->get('/inventory/count-sheet')->assertNotFound();
    }
}
