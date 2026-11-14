<?php

namespace Tests\Feature;

use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Accounting\Invoices;
use App\Accounting\Money;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Invoices: income is counted once, when the invoice is issued; a payment only settles the receivable. */
class InvoiceTest extends TestCase
{
    private Account $customer;

    private function draft(array $lines, string $currency = 'AFN', array $extra = []): Invoice
    {
        $sales = Account::system(company_id(), 'sales');

        return Invoices::saveDraft(company(), [
            'customer_account_id' => $this->customer->id,
            'date' => '2026-11-15',
            'currency' => $currency,
            'lines' => array_map(fn (array $line) => $line + ['income_account_id' => $sales->id], $lines),
        ] + $extra);
    }

    private function begin(): void
    {
        $this->activeCompany();
        $this->customer = Chart::open(company(), ['kind' => 'customer', 'name' => 'Ahmad']);
    }

    private function cash(string $currency = 'AFN'): Account
    {
        return Account::where('kind', 'cash')->where('currency', $currency)->first()
            ?? Chart::open(company(), ['kind' => 'cash', 'name' => 'Cash '.$currency, 'currency' => $currency]);
    }

    public function test_a_draft_posts_nothing_and_issuing_posts_once(): void
    {
        $this->begin();
        $invoice = $this->draft([
            ['description' => 'Website', 'quantity' => '2', 'unit_price' => '500', 'discount' => '100', 'tax_rate' => '10'],
            ['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '250'],
        ]);

        $this->assertSame(0, Dec::cmp($invoice->subtotal, '1150'));
        $this->assertSame(0, Dec::cmp($invoice->tax, '90'));
        $this->assertSame(0, Dec::cmp($invoice->total, '1240'));
        $this->assertSame(0, JournalEntry::count());

        Invoices::issue($invoice);
        Invoices::issue($invoice->fresh()); // pressed twice

        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(['AFN' => '1240.0000'], Balances::byCurrency($this->customer));
        $this->assertSame('1150.0000', Balances::profit(company(), null, null));
        $this->assertSame('90.0000', Balances::natural('liability', Balances::byCurrency(Account::system(company_id(), 'taxes_payable'))['AFN']));
    }

    public function test_a_payment_settles_the_invoice_without_counting_income_again(): void
    {
        $this->begin();
        $invoice = Invoices::issue($this->draft([['description' => 'Work', 'quantity' => '1', 'unit_price' => '1000']]));

        $this->get('/money/in/create?invoice='.$invoice->id)->assertOk()->assertSee($invoice->code());
        $this->post('/money/in', ['kind' => 'customer_receipt', 'date' => '2026-11-16', 'cash_account_id' => $this->cash()->id,
            'counter_account_id' => $this->customer->id, 'amount' => '400', 'once' => (string) str()->uuid(), 'invoice_id' => $invoice->id])->assertRedirect();

        $invoice = $invoice->fresh();
        $this->assertSame('partial', $invoice->state());
        $this->assertSame(0, Dec::cmp($invoice->due(), '600'));
        $this->assertSame('1000.0000', Balances::profit(company(), null, null)); // still 1,000: not 1,400
        $this->assertSame(['AFN' => '600.0000'], Balances::byCurrency($this->customer));

        // paying the rest closes it; a payment larger than what is due is refused for the invoice
        $movement = Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-17', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->customer->id, 'amount' => '600']);
        Invoices::allocate($invoice, $movement);
        $this->assertSame('paid', $invoice->fresh()->state());

        // cancelling a payment makes the invoice due again
        Money::void($movement);
        $this->assertSame(0, Dec::cmp($invoice->fresh()->due(), '600'));
        $this->assertSame(['AFN' => '600.0000'], Balances::byCurrency($this->customer));
    }

    public function test_an_invoice_with_payments_cannot_be_cancelled_and_one_without_is_reversed(): void
    {
        $this->begin();
        $paid = Invoices::issue($this->draft([['description' => 'A', 'quantity' => '1', 'unit_price' => '100']]));
        Invoices::allocate($paid, Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->customer->id, 'amount' => '100']));
        try {
            Invoices::void($paid);
            $this->fail('An invoice with a payment was cancelled.');
        } catch (UserError) {
        }

        $open = Invoices::issue($this->draft([['description' => 'B', 'quantity' => '1', 'unit_price' => '300']]));
        Invoices::void($open);

        $this->assertSame('void', $open->fresh()->state());
        $this->assertSame('100.0000', Balances::profit(company(), null, null));
    }

    public function test_a_credit_note_lowers_the_receivable_and_the_income(): void
    {
        $this->begin();
        Invoices::issue($this->draft([['description' => 'Goods', 'quantity' => '10', 'unit_price' => '50']]));
        $note = Invoices::issue($this->draft([['description' => 'Returned', 'quantity' => '2', 'unit_price' => '50']], 'AFN', ['type' => 'credit_note']));

        $this->assertSame('CN-000001', $note->code());
        $this->assertSame(['AFN' => '400.0000'], Balances::byCurrency($this->customer));
        $this->assertSame('400.0000', Balances::profit(company(), null, null));
    }

    public function test_a_foreign_invoice_balances_to_the_last_decimal(): void
    {
        $this->begin();
        // three lines whose base values each round: the entry must still balance
        $invoice = Invoices::issue($this->draft([
            ['description' => 'A', 'quantity' => '1', 'unit_price' => '0.3333', 'tax_rate' => '7.5'],
            ['description' => 'B', 'quantity' => '3', 'unit_price' => '0.1111'],
        ], 'USD', ['rate' => '70.12345678']));

        $totals = DB::table('journal_lines')->where('journal_entry_id', $invoice->journal_entry_id)->selectRaw('SUM(base_debit) AS d, SUM(base_credit) AS c')->first();
        $this->assertSame(0, Dec::cmp($totals->d, $totals->c));
        $this->assertSame(['USD'], array_keys(Balances::byCurrency($this->customer)));

        // it must be paid in its own currency
        $afn = Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->customer->id, 'amount' => '10']);
        $this->expectException(UserError::class);
        Invoices::allocate($invoice, $afn);
    }

    public function test_the_invoice_screens_work_and_respect_roles(): void
    {
        $this->begin();
        $sales = Account::system(company_id(), 'sales');
        $form = ['type' => 'invoice', 'customer_account_id' => $this->customer->id, 'date' => '2026-11-15', 'due_date' => '2026-11-30', 'currency' => 'AFN', 'action' => 'issue',
            'lines' => [['description' => 'Design', 'quantity' => '1', 'unit_price' => '2,500', 'income_account_id' => $sales->id]]];

        $this->get('/invoices/create')->assertOk();
        $this->post('/invoices', $form)->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->assertSame(Invoice::ISSUED, $invoice->status);
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('2,500.00 AFN');
        $this->get('/invoices')->assertOk()->assertSee('INV-000001');
        $this->get('/invoices/'.$invoice->id.'/edit')->assertNotFound(); // issued: no longer a draft

        $viewer = $this->user('viewer@acme.test');
        company()->users()->attach($viewer->id, ['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer, 'web');
        $this->get('/invoices')->assertOk();
        $this->get('/invoices/create')->assertForbidden();
        $this->post('/invoices/'.$invoice->id.'/void')->assertForbidden();
    }
}
