<?php

namespace Tests\Feature;

use App\Accounting\Assets;
use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Accounting\Money;
use App\Accounting\Payroll;
use App\Exceptions\UserError;
use App\Inventory\Pos;
use App\Inventory\Purchasing;
use App\Inventory\Sales;
use App\Inventory\Stock;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\StockLevel;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Business modules, each followed from its first document to the general ledger.
 * Base currency AFN. Every test ends by checking that the books balance and, where stock
 * moved, that the stock value equals the Inventory account.
 */
class BusinessModulesTest extends TestCase
{
    private function product(string $name = 'Laptop', array $extra = []): Product
    {
        return Product::create(['type' => 'product', 'name' => $name, 'sale_price' => '150', 'purchase_price' => '100', 'track_stock' => true] + $extra);
    }

    private function cash(): Account
    {
        return Account::where('kind', 'cash')->where('currency', 'AFN')->firstOrFail();
    }

    private function balance(string $systemKey): string
    {
        return Balances::baseByAccount(company_id(), null, null)->get(Account::system(company_id(), $systemKey)->id, '0.0000');
    }

    private function onHand(Product $product): string
    {
        return Stock::qty(StockLevel::where('product_id', $product->id)->sum('quantity'));
    }

    private function assertSound(): void
    {
        $totals = DB::table('journal_lines')->where('company_id', company_id())->selectRaw('SUM(base_debit) AS d, SUM(base_credit) AS c')->first();
        $this->assertSame(0, Dec::cmp($totals->d, $totals->c), 'The ledger does not balance.');
        $this->assertSame(0, Dec::cmp(StockLevel::sum('value'), $this->balance('inventory')), 'Stock value and the Inventory account differ.');
    }

    public function test_purchase_order_to_payment(): void
    {
        $company = $this->activeCompany();
        $supplier = Chart::open($company, ['kind' => 'supplier', 'name' => 'Kabul Traders']);
        $laptop = $this->product();
        $delivery = Product::create(['type' => 'service', 'name' => 'Delivery', 'track_stock' => false]);

        $order = Purchasing::save($company, ['supplier_account_id' => $supplier->id, 'date' => '2026-11-15', 'currency' => 'AFN', 'lines' => [
            ['product_id' => $laptop->id, 'description' => 'Laptop', 'quantity' => '10', 'unit_price' => '100'],
            ['product_id' => $delivery->id, 'description' => 'Delivery', 'quantity' => '1', 'unit_price' => '50'],
        ]]);
        [$goods, $service] = $order->lines()->get()->all();

        // nothing happens before approval, and an order posts nothing
        try {
            Purchasing::receive($order, [$goods->id => '6'], '2026-11-15');
            $this->fail('An unapproved order was received.');
        } catch (UserError) {
        }
        Purchasing::approve($order);
        $this->assertSame('0.0000', $this->balance('inventory'));

        // part of the goods arrive: stock and the accrual rise, the supplier is not credited yet
        Purchasing::receive($order, [$goods->id => '6'], '2026-11-15');
        $this->assertSame('6', $this->onHand($laptop));
        $this->assertSame('600.0000', $this->balance('inventory'));
        $this->assertSame('-600.0000', $this->balance('grni'));
        $this->assertSame([], Balances::byCurrency($supplier));

        // the bill for what arrived plus the service: the accrual clears, stock does not move again
        Purchasing::bill($order, null, '2026-11-16', 'INV-778');
        $this->assertSame('0.0000', $this->balance('grni'));
        $this->assertSame('600.0000', $this->balance('inventory'));
        $this->assertSame('50.0000', $this->balance('purchase_expenses'));
        $this->assertSame(['AFN' => '-650.0000'], Balances::byCurrency($supplier));

        // more than ordered is refused; the rest completes the order
        try {
            Purchasing::receive($order, [$goods->id => '5'], '2026-11-17');
            $this->fail('More than ordered was received.');
        } catch (UserError) {
        }
        Purchasing::receive($order, [$goods->id => '4'], '2026-11-17');
        Purchasing::bill($order, null, '2026-11-17');
        $this->assertSame('closed', $order->fresh()->status);

        // paying the supplier settles the payable and is not an expense
        Money::record($company, ['direction' => 'out', 'kind' => 'supplier_payment', 'date' => '2026-11-18', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $supplier->id, 'amount' => '1050']);
        $this->assertSame([], array_filter(Balances::byCurrency($supplier), fn ($b) => ! Dec::isZero($b)));
        $this->assertSame('-50.0000', Balances::profit($company, null, null)); // only the delivery service

        // two billed laptops go back: stock falls, the supplier owes us 200
        Purchasing::returnGoods($order->fresh(), [$goods->id => '2'], '2026-11-19');
        $this->assertSame('8', $this->onHand($laptop));
        $this->assertSame(['AFN' => '200.0000'], Balances::byCurrency($supplier));
        $this->assertSound();
    }

    public function test_stock_is_valued_at_moving_average_and_never_goes_negative(): void
    {
        $company = $this->activeCompany();
        $product = $this->product();
        $main = Stock::defaultWarehouse($company);
        $shop = \App\Models\Warehouse::create(['name' => 'Shop']);

        Stock::adjust($company, $product, $main->id, '10', '100', '2026-11-15', null, true); // opening stock
        Stock::adjust($company, $product, $main->id, '10', '120', '2026-11-15');
        $level = StockLevel::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(0, Dec::cmp($level->value, '2200')); // average 110

        Stock::adjust($company, $product, $main->id, '-5', null, '2026-11-15', 'damaged');
        $this->assertSame(0, Dec::cmp($level->fresh()->value, '1650')); // 15 left at 110
        $this->assertSame('-650.0000', $this->balance('inventory_adjustments')); // +1,200 found, -550 damaged

        // a transfer moves cost with the goods and makes no profit
        $before = Balances::profit($company, null, null);
        Stock::transfer($company, $product, $main->id, $shop->id, '5', '2026-11-15');
        $this->assertSame($before, Balances::profit($company, null, null));
        $this->assertSame(0, Dec::cmp(StockLevel::where('warehouse_id', $shop->id)->value('value'), '550'));

        try {
            Stock::adjust($company, $product, $main->id, '-11', null, '2026-11-15');
            $this->fail('Stock went negative.');
        } catch (UserError) {
        }
        $this->assertSame('15', $this->onHand($product));
        $this->assertSound();
    }

    public function test_sales_order_to_collection_and_a_return(): void
    {
        $company = $this->activeCompany();
        $customer = Chart::open($company, ['kind' => 'customer', 'name' => 'Ahmad']);
        $product = $this->product();
        Stock::adjust($company, $product, Stock::defaultWarehouse($company)->id, '10', '100', '2026-11-15', null, true);
        $sales = Account::system($company->id, 'sales');

        $quote = Sales::save($company, ['type' => 'quotation', 'customer_account_id' => $customer->id, 'date' => '2026-11-15', 'currency' => 'AFN', 'lines' => [
            ['product_id' => $product->id, 'description' => 'Laptop', 'quantity' => '4', 'unit_price' => '150', 'income_account_id' => $sales->id],
        ]]);
        $order = Sales::confirm($quote); // the accepted quotation becomes an order
        $this->assertSame('SO-000001', $order->code());
        $this->assertSame('closed', $quote->fresh()->status);
        $line = $order->lines()->firstOrFail();

        // delivery: cost of goods sold, no income yet
        Sales::deliver($order, null, '2026-11-16');
        $this->assertSame('6', $this->onHand($product));
        $this->assertSame('400.0000', $this->balance('cogs'));
        $this->assertSame('-400.0000', Balances::profit($company, null, null));

        // the invoice recognizes the income once
        $invoice = Sales::invoice($order, '2026-11-16');
        $this->assertSame('200.0000', Balances::profit($company, null, null));
        $this->assertSame(['AFN' => '600.0000'], Balances::byCurrency($customer));
        $this->assertSame('closed', $order->fresh()->status);
        try {
            Sales::invoice($order->fresh(), '2026-11-16');
            $this->fail('The same delivery was invoiced twice.');
        } catch (UserError) {
        }

        // collecting settles the receivable and adds no income
        $movement = Money::record($company, ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-17', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $customer->id, 'amount' => '600']);
        \App\Accounting\Invoices::allocate($invoice, $movement);
        $this->assertSame('paid', $invoice->fresh()->state());
        $this->assertSame('200.0000', Balances::profit($company, null, null));

        // one comes back: stock and cost are restored, a credit note takes the income back
        Sales::returnGoods($order->fresh(), [$line->id => '1'], '2026-11-18');
        $this->assertSame('7', $this->onHand($product));
        $this->assertSame('300.0000', $this->balance('cogs'));
        $this->assertSame('150.0000', Balances::profit($company, null, null));
        $this->assertSame(1, Invoice::where('type', 'credit_note')->count());
        $this->assertSame(['AFN' => '-150.0000'], Balances::byCurrency($customer)); // we owe the customer 150
        $this->assertSound();
    }

    public function test_point_of_sale_from_opening_to_cash_closing(): void
    {
        $company = $this->activeCompany();
        $product = $this->product();
        $warehouse = Stock::defaultWarehouse($company);
        Stock::adjust($company, $product, $warehouse->id, '10', '100', '2026-11-15', null, true);
        $drawer = $this->cash();
        $bank = Chart::open($company, ['kind' => 'bank', 'name' => 'Card machine', 'currency' => 'AFN']);
        $register = PosRegister::create(['name' => 'Till 1', 'warehouse_id' => $warehouse->id, 'cash_account_id' => $drawer->id]);

        $session = Pos::open($register, '500');
        try {
            Pos::open($register, '0');
            $this->fail('A register was opened twice.');
        } catch (UserError) {
        }

        // 2 laptops at 150, paid 200 cash and 100 by card; the same request twice is one sale
        $cart = [['product_id' => $product->id, 'quantity' => '2', 'unit_price' => '150']];
        $pay = [['account_id' => $drawer->id, 'amount' => '200'], ['account_id' => $bank->id, 'amount' => '100']];
        $sale = Pos::sell($session, $cart, $pay, null, 'key-1');
        $again = Pos::sell($session, $cart, $pay, null, 'key-1');
        $this->assertSame($sale->id, $again->id);
        $this->assertSame(1, PosSale::count());

        $this->assertSame('8', $this->onHand($product));
        $this->assertSame('100.0000', Balances::profit($company, null, null)); // 300 income - 200 cost
        $this->assertSame(['AFN' => '200.0000'], Balances::byCurrency($drawer));
        $this->assertSame(['AFN' => '100.0000'], Balances::byCurrency($bank));
        $this->assertSame('paid', $sale->invoice->fresh()->state());
        $this->assertSame([], array_filter(Balances::byCurrency(Pos::walkIn($company)), fn ($b) => ! Dec::isZero($b)));

        // payments that do not equal the total, and more than is in stock, are refused and leave nothing behind
        foreach ([[$cart, [['account_id' => $drawer->id, 'amount' => '250']]], [[['product_id' => $product->id, 'quantity' => '9', 'unit_price' => '150']], [['account_id' => $drawer->id, 'amount' => '1350']]]] as [$items, $payments]) {
            try {
                Pos::sell($session, $items, $payments, null, null);
                $this->fail('A bad sale was accepted.');
            } catch (UserError) {
            }
        }
        $this->assertSame(1, PosSale::count());
        $this->assertSame(1, Invoice::where('status', 'issued')->count());

        // a second sale is refunded: stock, income and the drawer are back where they were
        $second = Pos::sell($session, [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '150']], [['account_id' => $drawer->id, 'amount' => '150']], null, 'key-2');
        Pos::refund($second, $session);
        $this->assertSame('8', $this->onHand($product));
        $this->assertSame('100.0000', Balances::profit($company, null, null));
        $this->assertSame(['AFN' => '200.0000'], Balances::byCurrency($drawer));

        // closing: 500 float + 200 cash expected; 690 counted is a shortage of 10
        $this->assertSame(0, Dec::cmp(Pos::expectedCash($session), '700'));
        $closed = Pos::close($session, '690');
        $this->assertSame(0, Dec::cmp($closed->difference, '-10'));
        $this->assertSame('90.0000', Balances::profit($company, null, null));
        $this->assertSame(['AFN' => '190.0000'], Balances::byCurrency($drawer));
        try {
            Pos::sell($closed, $cart, [['account_id' => $drawer->id, 'amount' => '300']], null, 'key-3');
            $this->fail('A closed register sold.');
        } catch (UserError) {
        }
        $this->assertSound();
    }

    public function test_fixed_asset_from_acquisition_to_disposal(): void
    {
        $company = $this->activeCompany();
        Money::record($company, ['direction' => 'in', 'kind' => 'capital', 'date' => '2026-01-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => Account::system($company->id, 'capital')->id, 'amount' => '50000']);

        $asset = Assets::acquire($company, ['name' => 'Generator', 'acquired_on' => '2026-01-10', 'cost' => '12000', 'residual_value' => '0', 'useful_life_months' => 12, 'paid_from_account_id' => $this->cash()->id]);
        $this->assertSame('12000.0000', $this->balance('fixed_assets'));
        $this->assertSame('0.0000', Balances::profit($company, null, null)); // buying an asset is not an expense

        $this->assertSame(1, Assets::depreciate($company, '2026-01')['assets']);
        $this->assertSame(0, Assets::depreciate($company, '2026-01')['assets']); // the same month again: nothing
        Assets::depreciate($company, '2026-02');
        $asset = $asset->fresh();
        $this->assertSame(0, Dec::cmp($asset->depreciated, '2000'));
        $this->assertSame(0, Dec::cmp($asset->bookValue(), '10000'));
        $this->assertSame('-2000.0000', $this->balance('accumulated_depreciation'));
        $this->assertSame('-2000.0000', Balances::profit($company, null, null));

        // sold for 10,500 against a book value of 10,000: a gain of 500
        Assets::dispose($asset, '2026-03-01', '10500', $this->cash()->id);
        $this->assertSame('0.0000', $this->balance('fixed_assets'));
        $this->assertSame('0.0000', $this->balance('accumulated_depreciation'));
        $this->assertSame('-1500.0000', Balances::profit($company, null, null));
        $this->assertSame(0, Assets::depreciate($company, '2026-03')['assets']); // a disposed asset no longer depreciates
        $this->assertSound();
    }

    public function test_payroll_accrues_once_and_pays_once(): void
    {
        $company = $this->activeCompany();
        Money::record($company, ['direction' => 'in', 'kind' => 'capital', 'date' => '2026-11-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => Account::system($company->id, 'capital')->id, 'amount' => '100000']);
        $employee = Employee::create(['name' => 'Karim', 'basic_salary' => '20000', 'allowances' => '2000', 'deductions' => '1000']);

        Payroll::advance($company, $employee, '3000', $this->cash()->id, '2026-11-05');
        $this->assertSame('3000.0000', $this->balance('employee_advances'));
        $this->assertSame('0.0000', Balances::profit($company, null, null)); // an advance is not an expense

        $run = Payroll::prepare($company, '2026-11');
        $this->assertSame(0, Dec::cmp($run->fresh()->net, '18000')); // 22,000 - 1,000 - 3,000
        try {
            Payroll::prepare($company, '2026-11');
            $this->fail('A second payroll was made for the same month.');
        } catch (UserError) {
        }
        $this->assertSame(1, PayrollRun::count());

        Payroll::approve($run);
        Payroll::approve($run->fresh()); // pressed twice
        $this->assertSame('-22000.0000', Balances::profit($company, null, null));
        $this->assertSame('-18000.0000', $this->balance('payroll_liabilities'));
        $this->assertSame('-1000.0000', $this->balance('payroll_deductions'));
        $this->assertSame('0.0000', $this->balance('employee_advances'));
        $this->assertSame(0, Dec::cmp($employee->fresh()->advance_balance, '0'));

        Payroll::pay($run->fresh(), $this->cash()->id, '2026-11-30');
        Payroll::pay($run->fresh(), $this->cash()->id, '2026-11-30'); // pressed twice
        $this->assertSame('0.0000', $this->balance('payroll_liabilities'));
        $this->assertSame('-22000.0000', Balances::profit($company, null, null)); // paying adds no expense
        $this->assertSame(['AFN' => '79000.0000'], Balances::byCurrency($this->cash())); // 100,000 - 3,000 - 18,000
        $this->assertSound();
    }

    public function test_every_module_screen_opens_and_is_gone_when_the_module_is_off(): void
    {
        $company = $this->activeCompany();
        $pages = ['/inventory', '/inventory/movements', '/inventory/change?mode=transfer', '/warehouses', '/purchases', '/purchases/create', '/sales/quotation', '/sales/order', '/sales/order/create',
            '/pos', '/pos/sessions', '/pos/sales', '/pos/registers', '/assets', '/assets/create', '/asset-categories', '/payroll', '/payroll/advances', '/employees', '/departments', '/dashboard'];
        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        // salaries are private: an accountant has no payroll permission
        $accountant = $this->user('accountant@acme.test');
        $company->users()->attach($accountant->id, ['role' => 'accountant', 'is_active' => true]);
        $this->actingAs($accountant, 'web');
        $this->get('/payroll')->assertForbidden();
        $this->get('/employees')->assertForbidden();
        $this->get('/inventory')->assertOk();

        $this->workIn($company);
        \App\Support\Modules::set($company, ['products']); // what the platform side does when a plan changes
        foreach (['/inventory', '/purchases', '/sales/order', '/pos', '/assets', '/payroll'] as $page) {
            $this->get($page)->assertNotFound();
        }
        $this->get('/dashboard')->assertOk();
    }
}
