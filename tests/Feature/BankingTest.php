<?php

namespace Tests\Feature;

use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Accounting\Money;
use App\Accounting\Reconciliation;
use App\Accounting\Recurring;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\BudgetLine;
use App\Models\MoneyMovement;
use App\Models\Plan;
use App\Models\RecurringTransaction;
use App\Support\Dec;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankingTest extends TestCase
{
    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_a_statement_is_imported_once_and_matched_to_the_books(): void
    {
        $company = $this->activeCompany();
        $bank = Chart::open($company, ['kind' => 'bank', 'name' => 'AIB', 'currency' => 'AFN']);
        $income = Chart::open($company, ['kind' => 'income', 'name' => 'Consulting']);
        $rent = Chart::open($company, ['kind' => 'expense', 'name' => 'Rent']);
        Money::record($company, ['direction' => 'in', 'kind' => 'income', 'date' => '2026-11-02', 'cash_account_id' => $bank->id, 'counter_account_id' => $income->id, 'amount' => '5000']);
        Money::record($company, ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-05', 'cash_account_id' => $bank->id, 'counter_account_id' => $rent->id, 'amount' => '1200']);
        Money::record($company, ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-10', 'cash_account_id' => $bank->id, 'counter_account_id' => $rent->id, 'amount' => '300']); // not on the statement yet

        $file = $this->csv("Date,Description,Reference,Credit,Debit\n03/11/2026,Transfer from client,TX1,\"5,000.00\",\n2026-11-05,Rent,CHQ9,,1200\n2026-11-07,Bank charge,,,50\n");
        $this->assertSame(['added' => 3, 'skipped' => 0], Reconciliation::import($bank, $file));
        $this->assertSame(['added' => 0, 'skipped' => 3], Reconciliation::import($bank, $file)); // the same file again

        // the two lines with one candidate each match by themselves; the bank charge has nothing in the books
        $this->assertSame(2, Reconciliation::autoMatch($bank));
        $charge = BankStatementLine::whereNull('journal_line_id')->firstOrFail();
        $this->assertSame(0, Dec::cmp($charge->amount, '-50'));

        $response = $this->get('/banking/reconciliation?account='.$bank->id)->assertOk();
        $this->assertSame(0, Dec::cmp($response->viewData('inBankNotBooks'), '-50'));
        $this->assertSame(0, Dec::cmp($response->viewData('inBooksNotBank'), '-300'));

        // the charge is recorded, then matched by hand; matching posts nothing
        $movement = Money::record($company, ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-07', 'cash_account_id' => $bank->id, 'counter_account_id' => $rent->id, 'amount' => '50']);
        $ledgerLine = $movement->entry->lines->firstWhere('account_id', $bank->id);
        $entries = \App\Models\JournalEntry::count();
        $this->post('/banking/statement/'.$charge->id.'/match', ['journal_line_id' => $ledgerLine->id])->assertSessionHas('success');
        $this->assertSame($entries, \App\Models\JournalEntry::count());
        $this->assertSame(0, BankStatementLine::whereNull('journal_line_id')->count());

        // one entry cannot match two statement lines, and a different amount is refused
        $other = BankStatementLine::create(['account_id' => $bank->id, 'date' => '2026-11-07', 'amount' => '-50', 'fingerprint' => sha1('x')]);
        $this->post('/banking/statement/'.$other->id.'/match', ['journal_line_id' => $ledgerLine->id])->assertSessionHas('danger');
        $this->post('/banking/statement/'.$charge->id.'/unmatch')->assertSessionHas('success');
        $this->assertNull($charge->fresh()->journal_line_id);

        // through the screen, with a file upload
        $this->post('/banking/reconciliation/import', ['account_id' => $bank->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', "date,amount\n2026-11-12,-75\n")])->assertRedirect();
        $this->assertSame(1, BankStatementLine::where('date', '2026-11-12')->count());
        $this->post('/banking/reconciliation/import', ['account_id' => $bank->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', "when,how much\n1,2\n")])->assertSessionHas('danger');
    }

    public function test_a_budget_is_spread_over_the_year_and_compared_with_what_happened(): void
    {
        $company = $this->activeCompany();
        $rent = Chart::open($company, ['kind' => 'expense', 'name' => 'Rent']);
        $income = Chart::open($company, ['kind' => 'income', 'name' => 'Consulting']);
        $cash = Account::where('kind', 'cash')->firstOrFail();

        $this->put('/banking/budget?year=2026', ['budget' => [$rent->id => '10,000', $income->id => '60000']])->assertRedirect();
        $this->assertSame(24, BudgetLine::count());
        $this->assertSame(0, Dec::cmp(BudgetLine::where('account_id', $rent->id)->sum('amount'), '10000')); // rounding does not lose a cent
        $this->get('/banking/budget?year=2026')->assertOk();

        Money::record($company, ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-01-10', 'cash_account_id' => $cash->id, 'counter_account_id' => $rent->id, 'amount' => '1000']);
        Money::record($company, ['direction' => 'in', 'kind' => 'income', 'date' => '2026-02-10', 'cash_account_id' => $cash->id, 'counter_account_id' => $income->id, 'amount' => '12000']);

        $response = $this->get('/banking/budget/comparison?year=2026&from_month=1&to_month=3')->assertOk();
        $rentRow = $response->viewData('expenses')->first();
        $this->assertSame(0, Dec::cmp($rentRow['budget'], '2499.99')); // three months of 833.33
        $this->assertSame(0, Dec::cmp($rentRow['actual'], '1000'));
        $this->assertTrue(Dec::isPositive($rentRow['variance'])); // spent less than planned: good
        $incomeRow = $response->viewData('income')->first();
        $this->assertSame(0, Dec::cmp($incomeRow['variance'], '-3000')); // 12,000 earned against 15,000 planned
        $this->get('/banking/budget/comparison?year=2026&export=csv')->assertOk();

        // clearing the box removes the budget of that account
        $this->put('/banking/budget?year=2026', ['budget' => [$rent->id => '']]);
        $this->assertSame(12, BudgetLine::count());
    }

    public function test_recurring_transactions_post_each_due_date_once(): void
    {
        $company = $this->activeCompany();
        $rent = Chart::open($company, ['kind' => 'expense', 'name' => 'Rent']);
        $cash = Account::where('kind', 'cash')->firstOrFail();
        $base = ['direction' => 'out', 'kind' => 'expense', 'cash_account_id' => $cash->id, 'counter_account_id' => $rent->id, 'frequency' => 'monthly'];

        // due on 31 Aug, 30 Sep, 31 Oct (today is 15 Nov): three missed dates, all posted on their own dates
        $all = RecurringTransaction::create($base + ['name' => 'Office rent', 'amount' => '1000', 'next_run' => '2026-08-31', 'missed_policy' => 'all']);
        // same history, but only the latest missed date is wanted
        $latest = RecurringTransaction::create($base + ['name' => 'Internet', 'amount' => '200', 'next_run' => '2026-08-15', 'missed_policy' => 'latest']);

        $this->assertSame(['posted' => 4, 'failed' => 0], Recurring::run($company)); // three rents and one internet
        $this->assertSame(['2026-08-31', '2026-09-30', '2026-10-31'], MoneyMovement::where('reference', 'REC-'.$all->id)->orderBy('date')->get()->map(fn ($m) => $m->date->toDateString())->all());
        $this->assertSame(['2026-11-15'], MoneyMovement::where('reference', 'REC-'.$latest->id)->get()->map(fn ($m) => $m->date->toDateString())->all());

        // running again posts nothing: every date is already done and the next ones are in the future
        $this->assertSame(['posted' => 0, 'failed' => 0], Recurring::run($company));
        $this->assertSame('2026-11-30', $all->fresh()->next_run->toDateString());
        $this->assertSame('2026-12-15', $latest->fresh()->next_run->toDateString());
        $this->assertSame('-3200.0000', Balances::profit($company, null, null));

        // a transaction that cannot be posted keeps its error and blocks nothing else
        $bad = RecurringTransaction::create(['kind' => 'supplier_payment', 'name' => 'Broken', 'amount' => '10', 'next_run' => '2026-11-01'] + $base); // an expense account is not a supplier
        $this->post('/recurring/run')->assertSessionHas('warning');
        $this->assertNotNull($bad->fresh()->last_error);
        $this->assertSame(0, MoneyMovement::where('reference', 'REC-'.$bad->id)->count());

        // an end date stops it
        $ending = RecurringTransaction::create($base + ['name' => 'Short', 'amount' => '5', 'next_run' => '2026-11-10', 'end_date' => '2026-11-20']);
        Recurring::run($company);
        $this->assertFalse($ending->fresh()->is_active);
        $this->assertSame(1, MoneyMovement::where('reference', 'REC-'.$ending->id)->count());

        $this->get('/recurring/out')->assertOk()->assertSee('Office rent');
        $this->get('/recurring/out/create')->assertOk();
        $this->post('/recurring/in', ['name' => 'Rent received', 'kind' => 'income', 'amount' => '500', 'cash_account_id' => $cash->id, 'counter_account_id' => Account::system($company->id, 'other_income')->id, 'frequency' => 'monthly', 'next_run' => '2026-12-01', 'missed_policy' => 'all', 'is_active' => 1])->assertRedirect();
        $this->assertSame('in', RecurringTransaction::where('name', 'Rent received')->firstOrFail()->direction);
    }

    public function test_banking_and_planning_belongs_to_the_business_version(): void
    {
        $this->activeCompany('Solo', Plan::STARTER);
        foreach (['/banking/reconciliation', '/banking/budget', '/banking/budget/comparison', '/recurring/out'] as $page) {
            $this->get($page)->assertNotFound();
        }
        $this->artisan('ledger:recurring')->assertSuccessful();
    }
}
