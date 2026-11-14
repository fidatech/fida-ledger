<?php

namespace Tests\Feature;

use App\Accounting\Chart;
use App\Accounting\Money;
use App\Models\Account;
use App\Models\Plan;
use App\Support\Modules;
use Tests\TestCase;

/** One database, many companies: nobody reaches another company's books, and roles are enforced by the server. */
class IsolationTest extends TestCase
{
    public function test_a_company_cannot_see_or_use_another_companys_accounts(): void
    {
        $theirs = $this->activeCompany('Theirs');
        $secret = Chart::open($theirs, ['kind' => 'customer', 'name' => 'Secret customer']);
        $theirCash = Account::where('kind', 'cash')->firstOrFail();

        $mine = $this->activeCompany('Mine');
        $myCash = Account::where('kind', 'cash')->firstOrFail();
        $this->assertNotSame($theirCash->id, $myCash->id);

        // a changed id in the address finds nothing
        $this->get('/accounts/'.$secret->id)->assertNotFound();
        $this->get('/accounts/'.$secret->id.'/edit')->assertNotFound();
        $this->get('/accounts')->assertOk()->assertDontSee('Secret customer');

        // another company's account in a form is refused by validation
        $this->post('/money/in', ['kind' => 'other', 'date' => '2026-11-15', 'cash_account_id' => $myCash->id, 'counter_account_id' => $secret->id, 'amount' => '10', 'once' => (string) str()->uuid()])
            ->assertSessionHasErrors('counter_account_id');
        $this->post('/money/in', ['kind' => 'other', 'date' => '2026-11-15', 'cash_account_id' => $theirCash->id, 'counter_account_id' => $secret->id, 'amount' => '10', 'once' => (string) str()->uuid()])
            ->assertSessionHasErrors('cash_account_id');

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('journal_lines')->where('company_id', $theirs->id)->count());
    }

    public function test_a_viewer_can_read_but_the_server_refuses_to_let_him_record(): void
    {
        $company = $this->activeCompany();
        $viewer = $this->user('viewer@acme.test');
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer, 'web');

        $this->get('/money/in')->assertOk();
        $this->get('/money/in/create')->assertForbidden();
        $this->post('/money/in', [])->assertForbidden();
        $this->get('/accounts/create')->assertForbidden();
        $this->get('/settings/modules')->assertForbidden();
    }

    public function test_a_removed_member_loses_access(): void
    {
        $company = $this->activeCompany();
        $member = $this->user('gone@acme.test');
        $company->users()->attach($member->id, ['role' => 'accountant', 'is_active' => false]);
        $this->actingAs($member, 'web');

        $this->get('/dashboard')->assertRedirect(route('onboarding.create'));
    }

    public function test_the_plan_decides_the_modules_and_a_company_cannot_change_them(): void
    {
        $business = $this->activeCompany();
        $this->assertCount(7, Modules::activeKeys($business));
        $this->get('/settings/modules')->assertOk()->assertSee('Included');
        // there is nothing to submit: the page has no form and the address accepts no change
        $this->put('/settings/modules', ['modules' => []])->assertStatus(405);
        $this->assertCount(7, Modules::activeKeys($business->fresh()));

        // dependencies still hold where the platform sets modules: Point of Sale brings what it needs
        Modules::set($business, ['pos']);
        $this->assertEqualsCanonicalizing(['products', 'inventory', 'sales', 'pos'], Modules::activeKeys($business->fresh()));

        // Starter has none and is refused any
        $starter = $this->activeCompany('Solo', Plan::STARTER);
        $this->assertSame([], Modules::activeKeys($starter));
        $this->get('/settings/modules')->assertOk()->assertDontSee('Included');
        $this->expectException(\App\Exceptions\UserError::class);
        Modules::set($starter, ['payroll']);
    }

    public function test_money_in_through_the_screen_posts_once_and_shows_a_receipt(): void
    {
        $this->activeCompany();
        $customer = Chart::open(company(), ['kind' => 'customer', 'name' => 'Ahmad']);
        $form = ['kind' => 'customer_receipt', 'date' => '2026-11-15', 'cash_account_id' => Account::where('kind', 'cash')->firstOrFail()->id,
            'counter_account_id' => $customer->id, 'amount' => '1,250.50', 'once' => (string) str()->uuid()];

        $this->get('/money/in/create?kind=customer_receipt')->assertOk()->assertSee('Ahmad');
        $this->post('/money/in', $form)->assertRedirect();
        $id = \App\Models\MoneyMovement::firstOrFail()->id;
        $this->post('/money/in', $form)->assertRedirect('/money/in/'.$id); // sent twice: still one
        $this->assertSame(1, \App\Models\MoneyMovement::count());

        $this->get('/money/in/'.$id)->assertOk()->assertSee('1,250.50 AFN');
        $this->get('/money/in')->assertOk()->assertSee('MI-000001');
        $this->post('/money/in/'.$id.'/void')->assertSessionHas('success');
        $this->get('/money/in/'.$id)->assertOk()->assertSee('This was cancelled');
    }

    public function test_the_admin_panel_is_separate_and_approves_payments(): void
    {
        $company = $this->company();
        $payment = $this->pendingPayment($company);

        // a company user is not an administrator
        $this->actingAs($company->users()->first(), 'web');
        $this->get('/admin/payments')->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin(), 'admin');
        $this->get('/admin')->assertOk();
        $this->get('/admin/payments')->assertOk()->assertSee('Acme');
        $this->post('/admin/payments/'.$payment->id.'/approve')->assertSessionHas('success');
        $this->assertTrue($company->fresh()->isUsable());
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved subscription payment', 'company_id' => $company->id]);
    }
}
