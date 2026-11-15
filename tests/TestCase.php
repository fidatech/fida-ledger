<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Billing;
use App\Support\Companies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // a fixed day, so tests do not depend on the clock
        $this->travelTo(Carbon::parse('2026-11-15 10:00:00', 'UTC'));
    }

    protected function user(string $email = 'owner@acme.test'): User
    {
        // read back from the database, so columns with a default (is_active) are filled in
        return User::create(['name' => 'Owner', 'email' => $email, 'password' => 'password123'])->fresh();
    }

    /** A new company (pending: not paid yet) with its owner. */
    protected function company(string $name = 'Acme', string $plan = Plan::BUSINESS, string $base = 'AFN', ?User $owner = null): Company
    {
        return Companies::open($owner ?? $this->user(strtolower($name).'@acme.test'), [
            'name' => $name,
            'plan' => Plan::where('code', $plan)->firstOrFail(),
            'base_currency' => $base,
            'timezone' => 'UTC',
        ]);
    }

    /** A company whose first payment was approved, made the current company of the test. */
    protected function activeCompany(string $name = 'Acme', string $plan = Plan::BUSINESS, string $base = 'AFN'): Company
    {
        $company = $this->company($name, $plan, $base);
        $payment = Billing::submit($company, $company->users()->first(), ['method' => 'cash']);
        Billing::approve($payment, $this->admin());

        return $this->workIn($company->fresh());
    }

    /** Sign in as the owner and make this the company the code works in (what the SetCompany middleware does). */
    protected function workIn(Company $company): Company
    {
        $this->actingAs($company->users()->first(), 'web');
        app()->instance('current.company', $company);
        session(['company_id' => $company->id]);

        return $company;
    }

    protected function admin(): Admin
    {
        return Admin::firstOrCreate(['email' => 'admin@fidaledger.test'], ['name' => 'System Admin', 'password' => 'password123']);
    }

    protected function pendingPayment(Company $company): SubscriptionPayment
    {
        return Billing::submit($company, $company->users()->first(), ['method' => 'cash']);
    }
}
