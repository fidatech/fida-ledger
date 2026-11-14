<?php

namespace Tests\Feature;

use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Accounting\Closing;
use App\Accounting\Invoices;
use App\Accounting\Ledger;
use App\Accounting\Money;
use App\Exceptions\UserError;
use App\Http\Controllers\ReportBookController;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Support\Dec;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    private function cash(): Account
    {
        return Account::where('kind', 'cash')->firstOrFail();
    }

    /** Income 5,000 and expense 2,000 in 2025, then income 1,000 in 2026. */
    private function twoYears(): void
    {
        $income = Chart::open(company(), ['kind' => 'income', 'name' => 'Consulting']);
        $rent = Chart::open(company(), ['kind' => 'expense', 'name' => 'Rent']);
        Money::record(company(), ['direction' => 'in', 'kind' => 'income', 'date' => '2025-06-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $income->id, 'amount' => '5000']);
        Money::record(company(), ['direction' => 'out', 'kind' => 'expense', 'date' => '2025-07-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $rent->id, 'amount' => '2000']);
        Money::record(company(), ['direction' => 'in', 'kind' => 'income', 'date' => '2026-02-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $income->id, 'amount' => '1000']);
    }

    public function test_closing_a_year_moves_its_result_to_equity_and_keeps_the_reports_right(): void
    {
        $company = $this->activeCompany();
        $this->twoYears();

        Closing::close($company, '2025-12-31');
        $company = $company->fresh();
        app()->instance('current.company', $company);

        $this->assertSame('2025-12-31', $company->locked_until->toDateString());
        $this->assertSame('-3000.0000', Balances::baseByAccount($company->id, null, null)->get(Account::system($company->id, 'retained_earnings')->id));
        // the closed year still reports its profit; the new year reports only its own
        $this->assertSame('3000.0000', Balances::profit($company, '2025-01-01', '2025-12-31'));
        $this->assertSame('1000.0000', Balances::profit($company, '2026-01-01', '2026-12-31'));
        $this->get('/reports/profit-loss?from=2025-01-01&until=2025-12-31')->assertOk()->assertViewHas('profit', '3000.0000');
        $this->get('/reports/balance-sheet')->assertOk()->assertViewHas('balanced', true)->assertViewHas('earnings', '1000.0000');

        // the closed year is locked, and it cannot be closed twice
        try {
            Money::record($company, ['direction' => 'in', 'kind' => 'income', 'date' => '2025-12-01', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => Account::where('name', 'Consulting')->firstOrFail()->id, 'amount' => '1']);
            $this->fail('A locked period accepted a posting.');
        } catch (UserError) {
        }
        $this->expectException(UserError::class);
        Closing::close($company, '2025-12-31');
    }

    public function test_a_manual_journal_must_balance_and_is_corrected_by_reversal(): void
    {
        $this->activeCompany();
        $rent = Chart::open(company(), ['kind' => 'expense', 'name' => 'Rent']);
        $form = fn (string $credit) => ['date' => '2026-11-15', 'description' => 'Rent accrued', 'currency' => 'AFN', 'once' => (string) str()->uuid(), 'lines' => [
            ['account_id' => $rent->id, 'debit' => '1,500'],
            ['account_id' => Account::system(company_id(), 'other_income')->id, 'credit' => $credit],
            ['account_id' => '', 'debit' => '', 'credit' => ''],
        ]];

        $this->get('/journal/create')->assertOk();
        $this->post('/journal', $form('1400'))->assertSessionHas('danger');
        $this->assertSame(0, JournalEntry::count());

        // the Inventory account is not open to manual entries
        $guarded = $form('1500');
        $guarded['lines'][1]['account_id'] = Account::system(company_id(), 'inventory')->id;
        $this->post('/journal', $guarded)->assertSessionHasErrors('lines.1.account_id');

        $this->post('/journal', $form('1500'))->assertRedirect();
        $entry = JournalEntry::firstOrFail();
        $this->post('/journal/'.$entry->id.'/reverse')->assertRedirect();
        $this->assertTrue($entry->fresh()->isReversed());
        $this->assertSame('0.0000', Balances::profit(company(), null, null));

        // an entry that belongs to a document is cancelled from the document, not here
        $movement = Money::record(company(), ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $rent->id, 'amount' => '10']);
        $this->post('/journal/'.$movement->journal_entry_id.'/reverse')->assertSessionHas('danger');
    }

    public function test_cash_flow_explains_the_change_in_cash(): void
    {
        $company = $this->activeCompany();
        $this->twoYears();
        $bank = Chart::open($company, ['kind' => 'bank', 'name' => 'AIB', 'currency' => 'AFN']);
        \App\Accounting\Transfers::record($company, ['date' => '2026-03-01', 'from_account_id' => $this->cash()->id, 'to_account_id' => $bank->id, 'from_amount' => '500']);

        // opening 3,000 (2025), +1,000 in 2026 = 4,000; the transfer between own accounts changes nothing
        $response = $this->get('/reports/cash-flow?from=2026-01-01&until=2026-12-31')->assertOk();
        $this->assertSame(money('3000'), $response->viewData('rows')[0][4]);
        $this->assertSame(money('4000'), $response->viewData('footer')[4]);
    }

    public function test_every_report_opens_exports_and_respects_modules_and_roles(): void
    {
        $company = $this->activeCompany();
        $customer = Chart::open($company, ['kind' => 'customer', 'name' => 'Ahmad']);
        Invoices::issue(Invoices::saveDraft($company, ['customer_account_id' => $customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-10', 'currency' => 'AFN',
            'lines' => [['description' => 'Work', 'quantity' => '1', 'unit_price' => '1000', 'tax_rate' => '10', 'income_account_id' => Account::system($company->id, 'sales')->id]]]));

        foreach (array_keys(ReportBookController::REPORTS) as $report) {
            $this->get('/reports/'.$report)->assertOk();
            $this->get('/reports/'.$report.'?export=csv')->assertOk()->assertHeader('content-disposition');
        }
        $this->get('/reports')->assertOk()->assertSee('Cash Flow Statement')->assertSee('Employee Payroll');
        // 36 days past due on 15 November: the 31–60 column
        $this->assertSame(money('1100'), $this->get('/reports/receivables-aging')->viewData('rows')[0][4]);
        $this->assertSame(sums(['AFN' => '100']), $this->get('/reports/tax?from=2026-01-01')->viewData('footer')[5]);
        $this->get('/reports/unknown')->assertNotFound();

        // a viewer reads reports but not the audit trail or salaries, and cannot export
        $viewer = $this->user('viewer@acme.test');
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer, 'web');
        $this->get('/reports/cash-flow')->assertOk();
        $this->get('/reports/cash-flow?export=csv')->assertForbidden();
        $this->get('/reports/audit-log')->assertForbidden();
        $this->get('/reports/payroll-summary')->assertForbidden();
        $this->get('/journal/create')->assertForbidden();

        // Starter has no Business reports
        $this->activeCompany('Solo', Plan::STARTER);
        $this->get('/reports/cash-flow')->assertOk();
        $this->get('/reports/product-sales')->assertNotFound();
        $this->get('/reports')->assertOk()->assertDontSee('Employee Payroll');
    }
}
