<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing;
use App\Support\Modules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_a_person_registers_and_is_sent_to_set_up_a_company(): void
    {
        $this->post('/register', ['name' => 'Fida', 'email' => 'Fida@Example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('onboarding.create'));

        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'fida@example.com']);
        $this->get('/dashboard')->assertRedirect(route('onboarding.create'));
    }

    public function test_a_business_company_starts_pending_with_all_seven_modules_and_the_first_year_price(): void
    {
        $this->actingAs($this->user(), 'web');

        $this->post('/start', [
            'plan' => 'business', 'modules' => array_keys(Modules::all()),
            'name' => 'Acme Trading', 'type' => 'trading', 'base_currency' => 'AFN', 'timezone' => 'Asia/Kabul',
        ])->assertRedirect(route('billing.show'));

        $company = Company::firstOrFail();
        $this->assertSame(Company::PENDING, $company->status);
        $this->assertCount(7, Modules::activeKeys($company));
        $this->assertSame(['kind' => 'first', 'setup_fee' => '400.00', 'annual_fee' => '200.00', 'amount' => '600.00', 'currency' => 'USD'], Billing::quote($company));

        // not paid: the books stay closed and every page leads to the payment page
        $this->get('/dashboard')->assertRedirect(route('billing.show'));
        $this->get('/accounts')->assertRedirect(route('billing.show'));
        $this->get('/billing')->assertOk()->assertSee('Payment needed');
    }

    public function test_starter_costs_300_the_first_year_and_never_gets_business_modules(): void
    {
        $company = $this->company('Solo', Plan::STARTER);

        $this->assertSame('300.00', Billing::quote($company)['amount']);
        $this->assertSame([], Modules::entitled($company));
        $this->assertSame([], Modules::activeKeys($company));
    }

    public function test_sending_a_receipt_does_not_activate_only_an_approval_does(): void
    {
        Storage::fake('local');
        $company = $this->workIn($this->company());

        $this->post('/billing/pay', ['method' => 'hesabpay', 'reference' => 'TX-1', 'receipt' => UploadedFile::fake()->image('receipt.jpg')])
            ->assertRedirect(route('billing.show'));

        $this->assertFalse($company->fresh()->isUsable());
        $this->get('/dashboard')->assertRedirect(route('billing.show'));
        $this->get('/billing')->assertOk()->assertSee('Payment pending');

        Billing::approve($company->payments()->firstOrFail(), $this->admin());

        $company = $company->fresh();
        $this->assertTrue($company->isUsable());
        $this->assertTrue($company->setup_fee_paid);
        $this->assertSame('2027-11-14', $company->subscription_ends_at->toDateString());
        app()->instance('current.company', $company);
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_renewal_charges_the_annual_fee_only_and_extends_from_the_end_date(): void
    {
        $company = $this->activeCompany();
        $this->travelTo('2027-11-01 10:00:00');

        $this->assertSame(['kind' => 'renewal', 'setup_fee' => '0.00', 'annual_fee' => '200.00', 'amount' => '200.00', 'currency' => 'USD'], Billing::quote($company->fresh()));

        Billing::approve($this->pendingPayment($company->fresh()), $this->admin());
        $this->assertSame('2028-11-14', $company->fresh()->subscription_ends_at->toDateString());
    }

    public function test_an_ended_subscription_closes_the_books_but_keeps_the_billing_page(): void
    {
        $company = $this->activeCompany();
        $this->travelTo('2027-11-20 10:00:00');
        app()->instance('current.company', $company->fresh());

        $this->get('/dashboard')->assertRedirect(route('billing.show'));
        $this->get('/billing')->assertOk()->assertSee('Subscription ended');
    }

    public function test_a_rejected_payment_can_be_sent_again(): void
    {
        $company = $this->company();
        Billing::reject($this->pendingPayment($company), $this->admin(), 'smaller_amount');

        $this->assertFalse($company->fresh()->isUsable());
        $this->assertTrue(Billing::canPay($company->fresh()));
    }

    public function test_sign_in_goes_straight_to_the_dashboard_of_the_company_used_last(): void
    {
        $first = $this->activeCompany('First');
        $owner = $first->users()->first();
        $second = $this->company('Second', Plan::STARTER, 'USD', $owner);
        Billing::approve($this->pendingPayment($second), $this->admin());
        auth('web')->logout();
        app()->forgetInstance('current.company');
        session()->forget('company_id');

        $this->post('/login', ['email' => $owner->email, 'password' => 'password123'])->assertRedirect(route('dashboard'));
        // the company opened last ("Second") is the one shown; there is no company selection page
        $this->get('/dashboard')->assertOk()->assertSee('Second');

        $this->post('/company/switch', ['company_id' => $first->id])->assertRedirect(route('dashboard'));
        $this->assertSame($first->id, User::find($owner->id)->last_company_id);
    }

    public function test_a_user_cannot_switch_to_a_company_he_does_not_belong_to(): void
    {
        $mine = $this->activeCompany('Mine');
        $other = $this->company('Other');
        $this->workIn($mine);

        $this->post('/company/switch', ['company_id' => $other->id])->assertNotFound();
    }
}
