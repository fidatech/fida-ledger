<?php

namespace Tests\Feature;

use App\Accounting\Balances;
use App\Accounting\Chart;
use App\Accounting\Ledger;
use App\Accounting\Money;
use App\Accounting\Transfers;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\ExchangeRate;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MoneyMovement;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/** The accounting engine: what every module relies on. Base currency of the test company: AFN. */
class LedgerTest extends TestCase
{
    private function cash(string $currency = 'AFN'): Account
    {
        return Account::where('kind', 'cash')->where('currency', $currency)->first()
            ?? Chart::open(company(), ['kind' => 'cash', 'name' => 'Cash '.$currency, 'currency' => $currency]);
    }

    private function open(string $kind, ?string $name = null): Account
    {
        return Chart::open(company(), ['kind' => $kind, 'name' => $name ?? ucfirst($kind)]);
    }

    private function assertBooksBalance(): void
    {
        $totals = DB::table('journal_lines')->where('company_id', company_id())->selectRaw('SUM(base_debit) AS d, SUM(base_credit) AS c')->first();
        $this->assertSame(0, Dec::cmp($totals->d, $totals->c), 'Debits and credits of the ledger differ.');
    }

    public function test_a_new_company_has_a_chart_and_a_cash_account_in_its_base_currency(): void
    {
        $this->activeCompany();

        $this->assertSame('AFN', $this->cash()->currency);
        $this->assertTrue(Account::where('code', '1000')->firstOrFail()->is_group);
        $this->assertNotNull(Account::system(company_id(), 'opening_balances'));
        $this->assertNotNull(Account::system(company_id(), 'exchange_difference'));
    }

    public function test_an_unbalanced_entry_is_refused(): void
    {
        $this->activeCompany();
        $this->expectException(LogicException::class);

        Ledger::post(company(), ['date' => '2026-11-15', 'lines' => [
            ['account' => $this->cash(), 'debit' => '100'],
            ['account' => $this->open('income'), 'credit' => '99.99'],
        ]]);
    }

    public function test_nothing_is_posted_to_a_group_or_into_a_locked_period(): void
    {
        $company = $this->activeCompany();
        $income = $this->open('income');

        try {
            Ledger::post($company, ['date' => '2026-11-15', 'lines' => [
                ['account' => Account::where('code', '1000')->firstOrFail(), 'debit' => '10'],
                ['account' => $income, 'credit' => '10'],
            ]]);
            $this->fail('Posting to a group was accepted.');
        } catch (UserError) {
        }

        $company->update(['locked_until' => '2026-10-31']);
        $this->expectException(UserError::class);
        Ledger::post($company, ['date' => '2026-10-31', 'lines' => [
            ['account' => $this->cash(), 'debit' => '10'],
            ['account' => $income, 'credit' => '10'],
        ]]);
    }

    public function test_posted_entries_cannot_be_edited_or_deleted_only_reversed(): void
    {
        $this->activeCompany();
        $entry = Ledger::post(company(), ['date' => '2026-11-15', 'lines' => [
            ['account' => $this->cash(), 'debit' => '500'],
            ['account' => $this->open('income'), 'credit' => '500'],
        ]]);

        foreach ([fn () => $entry->update(['description' => 'changed']), fn () => $entry->delete(), fn () => $entry->lines->first()->update(['debit' => '1']), fn () => $entry->lines->first()->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('A posted entry was changed.');
            } catch (LogicException) {
            }
        }

        $reversal = Ledger::reverse($entry);
        $this->assertSame($reversal->id, $entry->fresh()->reversed_by_id);
        $this->assertSame([], array_filter(Balances::byCurrency($this->cash()), fn ($balance) => ! Dec::isZero($balance)));
        $this->assertBooksBalance();

        $this->expectException(UserError::class);
        Ledger::reverse($entry->fresh());
    }

    public function test_money_in_from_a_customer_lowers_the_receivable_and_is_not_income(): void
    {
        $this->activeCompany();
        $customer = Chart::open(company(), ['kind' => 'customer', 'name' => 'Ahmad', 'opening_amount' => '1000', 'opening_side' => 'debit']);

        $movement = Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-15',
            'cash_account_id' => $this->cash()->id, 'counter_account_id' => $customer->id, 'amount' => '400']);

        $this->assertSame('MI-000001', $movement->code());
        $this->assertSame(['AFN' => '600.0000'], Balances::byCurrency($customer));
        $this->assertSame(['AFN' => '400.0000'], Balances::byCurrency($this->cash()));
        $this->assertSame('0.0000', Balances::profit(company(), null, null)); // a receipt is not revenue
        $this->assertBooksBalance();
    }

    public function test_loan_received_is_a_liability_and_an_expense_paid_is_an_expense(): void
    {
        $this->activeCompany();
        $lender = $this->open('loan_payable', 'Bank loan');
        $rent = $this->open('expense', 'Office rent');

        Money::record(company(), ['direction' => 'in', 'kind' => 'loan_received', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $lender->id, 'amount' => '5000']);
        Money::record(company(), ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $rent->id, 'amount' => '1200']);

        $this->assertSame('5000.0000', Balances::natural('liability', Balances::byCurrency($lender)['AFN']));
        $this->assertSame('-1200.0000', Balances::profit(company(), null, null));
        $this->assertSame(['AFN' => '3800.0000'], Balances::byCurrency($this->cash()));
        $this->assertBooksBalance();
    }

    public function test_the_kind_of_money_limits_the_accounts_and_cash_to_cash_needs_a_transfer(): void
    {
        $this->activeCompany();
        $supplier = $this->open('supplier');
        $bank = Chart::open(company(), ['kind' => 'bank', 'name' => 'AIB', 'currency' => 'AFN']);

        foreach ([['customer_receipt', $supplier->id], ['other', $bank->id]] as [$kind, $counter]) {
            try {
                Money::record(company(), ['direction' => 'in', 'kind' => $kind, 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $counter, 'amount' => '10']);
                $this->fail('A wrong account was accepted for '.$kind);
            } catch (UserError) {
            }
        }
        $this->assertSame(0, MoneyMovement::count());
    }

    public function test_the_same_form_sent_twice_records_one_movement(): void
    {
        $this->activeCompany();
        $data = ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id,
            'counter_account_id' => $this->open('expense')->id, 'amount' => '75', 'idempotency_key' => 'a-key'];

        $first = Money::record(company(), $data);
        $second = Money::record(company(), $data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MoneyMovement::count());
        $this->assertSame(1, JournalEntry::where('source_id', $first->id)->where('source_type', $first->getMorphClass())->count());
    }

    public function test_cancelling_a_movement_reverses_its_entry_and_keeps_the_record(): void
    {
        $this->activeCompany();
        $movement = Money::record(company(), ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id,
            'counter_account_id' => $this->open('expense')->id, 'amount' => '75']);

        Money::void($movement);

        $this->assertTrue($movement->fresh()->isVoid());
        $this->assertSame('0.0000', Balances::profit(company(), null, null));
        $this->assertSame(4, JournalLine::count());
        $this->assertBooksBalance();
    }

    public function test_foreign_cash_keeps_its_own_currency_and_is_valued_at_the_rate_given(): void
    {
        $this->activeCompany();
        $usd = $this->cash('USD');
        $customer = $this->open('customer');

        Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-15', 'cash_account_id' => $usd->id, 'counter_account_id' => $customer->id, 'amount' => '100', 'rate' => '70']);

        $held = Balances::forAccounts(company_id(), [$usd->id])->get($usd->id);
        $this->assertSame(['USD' => '100.0000'], $held['currencies']);
        $this->assertSame('7000.0000', $held['base']);
        // the customer's balance is kept in USD too: currencies are never mixed
        $this->assertSame(['USD' => '-100.0000'], Balances::byCurrency($customer));

        $this->expectException(UserError::class); // no rate typed and none saved for EUR
        Money::record(company(), ['direction' => 'in', 'kind' => 'customer_receipt', 'date' => '2026-11-15', 'cash_account_id' => $this->cash('EUR')->id, 'counter_account_id' => $customer->id, 'amount' => '5']);
    }

    public function test_a_transfer_in_one_currency_makes_no_profit(): void
    {
        $this->activeCompany();
        Money::record(company(), ['direction' => 'in', 'kind' => 'income', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->open('income', 'Consulting')->id, 'amount' => '900']);
        $bank = Chart::open(company(), ['kind' => 'bank', 'name' => 'AIB', 'currency' => 'AFN']);

        $transfer = Transfers::record(company(), ['date' => '2026-11-15', 'from_account_id' => $this->cash()->id, 'to_account_id' => $bank->id, 'from_amount' => '300']);

        $this->assertSame('transfer', $transfer->type);
        $this->assertSame(['AFN' => '300.0000'], Balances::byCurrency($bank));
        $this->assertSame('900.0000', Balances::profit(company(), null, null));
        $this->assertBooksBalance();
    }

    public function test_selling_dollars_realizes_the_exchange_gain_against_their_average_cost(): void
    {
        $this->activeCompany();
        $afn = $this->cash();
        $usd = $this->cash('USD');
        $capital = Account::system(company_id(), 'capital');
        Money::record(company(), ['direction' => 'in', 'kind' => 'capital', 'date' => '2026-11-15', 'cash_account_id' => $afn->id, 'counter_account_id' => $capital->id, 'amount' => '14000']);

        // buy 200 USD for 14,000 AFN (cost 70 each): no gain or loss on buying
        $buy = Transfers::record(company(), ['date' => '2026-11-15', 'from_account_id' => $afn->id, 'to_account_id' => $usd->id, 'from_amount' => '14000', 'to_amount' => '200']);
        $this->assertSame('exchange', $buy->type);
        $this->assertTrue(Dec::isZero($buy->exchange_difference));

        // sell 100 USD for 7,300 AFN: they cost 7,000, so 300 AFN is gained
        $sell = Transfers::record(company(), ['date' => '2026-11-16', 'from_account_id' => $usd->id, 'to_account_id' => $afn->id, 'from_amount' => '100', 'to_amount' => '7300']);
        $this->assertSame(0, Dec::cmp($sell->exchange_difference, '300'));

        $this->assertSame('300.0000', Balances::profit(company(), null, null));
        $held = Balances::forAccounts(company_id(), [$usd->id])->get($usd->id);
        $this->assertSame(['USD' => '100.0000'], $held['currencies']);
        $this->assertSame('7000.0000', $held['base']);
        $this->assertSame(['AFN' => '7300.0000'], Balances::byCurrency($afn));
        $this->assertBooksBalance();
    }

    public function test_an_exchange_between_two_foreign_currencies_uses_the_saved_rate(): void
    {
        $this->activeCompany();
        $usd = $this->cash('USD');
        $eur = $this->cash('EUR');
        $capital = Account::system(company_id(), 'capital');
        Money::record(company(), ['direction' => 'in', 'kind' => 'capital', 'date' => '2026-11-15', 'cash_account_id' => $usd->id, 'counter_account_id' => $capital->id, 'amount' => '110', 'rate' => '70']);
        ExchangeRate::create(['currency' => 'EUR', 'date' => '2026-11-15', 'rate' => '78']);

        // 110 USD (carried at 7,700 AFN) for 100 EUR worth 7,800 AFN: gain 100
        $exchange = Transfers::record(company(), ['date' => '2026-11-15', 'from_account_id' => $usd->id, 'to_account_id' => $eur->id, 'from_amount' => '110', 'to_amount' => '100']);

        $this->assertSame(0, Dec::cmp($exchange->exchange_difference, '100'));
        $this->assertBooksBalance();
    }

    public function test_reports_agree_with_the_ledger(): void
    {
        $this->activeCompany();
        $customer = Chart::open(company(), ['kind' => 'customer', 'name' => 'Ahmad', 'opening_amount' => '1000']);
        Money::record(company(), ['direction' => 'in', 'kind' => 'income', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->open('income', 'Consulting')->id, 'amount' => '2500']);
        Money::record(company(), ['direction' => 'out', 'kind' => 'expense', 'date' => '2026-11-15', 'cash_account_id' => $this->cash()->id, 'counter_account_id' => $this->open('expense', 'Rent')->id, 'amount' => '700']);

        $this->get('/reports/trial-balance')->assertOk()->assertViewHas('debits', '3500.0000')->assertViewHas('credits', '3500.0000');
        $this->get('/reports/profit-loss')->assertOk()->assertViewHas('profit', '1800.0000');
        $this->get('/reports/balance-sheet')->assertOk()->assertViewHas('balanced', true)->assertViewHas('totalAssets', '2800.0000');
        $this->get('/accounts/'.$customer->id)->assertOk()->assertSee('Opening balance of Ahmad');
        $this->get('/dashboard')->assertOk();
        $this->get('/journal')->assertOk();
        $this->get('/accounts/chart')->assertOk();
    }
}
