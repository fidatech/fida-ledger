<?php

namespace Tests\Feature;

use App\Accounting\Ledger;
use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\Billing;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_the_owner_adds_a_user_changes_his_role_and_switches_him_off(): void
    {
        $company = $this->activeCompany();

        $this->post('/settings/users', ['name' => 'Cashier', 'email' => 'Cash@Acme.test', 'role' => 'cashier', 'password' => 'password123'])->assertSessionHas('success');
        $cashier = User::where('email', 'cash@acme.test')->firstOrFail();
        $this->assertSame('cashier', $cashier->roleIn($company));

        // nobody is made an owner from this screen, and the owner cannot be changed
        $this->post('/settings/users', ['name' => 'X', 'email' => 'x@acme.test', 'role' => 'owner', 'password' => 'password123'])->assertSessionHasErrors('role');
        $this->put('/settings/users/'.auth()->id(), ['role' => 'viewer', 'is_active' => 1])->assertSessionHas('danger');

        $this->put('/settings/users/'.$cashier->id, ['role' => 'viewer', 'is_active' => 0])->assertSessionHas('success');
        $this->actingAs($cashier->fresh(), 'web');
        $this->get('/dashboard')->assertRedirect(route('onboarding.create'));
    }

    public function test_the_period_lock_is_set_in_settings_and_stops_posting(): void
    {
        $company = $this->activeCompany();
        $this->put('/settings/company', ['name' => 'Acme Ltd', 'timezone' => 'Asia/Kabul', 'locked_until' => '2026-10-31'])->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['action' => 'changed period lock', 'company_id' => $company->id]);

        $this->expectException(UserError::class);
        Ledger::post($company->fresh(), ['date' => '2026-10-15', 'lines' => [
            ['account' => Account::where('kind', 'cash')->firstOrFail(), 'debit' => '10'],
            ['account' => Account::system($company->id, 'other_income'), 'credit' => '10'],
        ]]);
    }

    public function test_currencies_can_be_added_and_the_base_currency_stays_on(): void
    {
        $this->activeCompany();
        $this->post('/settings/currencies', ['code' => 'aed', 'name' => 'Dirham'])->assertSessionHas('success');
        $this->assertArrayHasKey('AED', currencies());

        $base = \App\Models\Currency::where('code', 'AFN')->firstOrFail();
        $this->post('/settings/currencies/'.$base->id.'/toggle')->assertSessionHas('danger');
        $this->get('/settings/currencies')->assertOk();
        $this->get('/settings/users')->assertOk();
        $this->get('/settings/company')->assertOk();
    }

    public function test_the_catalogue_exists_only_while_the_products_module_is_on(): void
    {
        $company = $this->activeCompany();

        $this->get('/products/product')->assertOk();
        $this->post('/units', ['name' => 'Piece', 'symbol' => 'pc'])->assertRedirect();
        $this->post('/products/product', ['name' => 'Laptop', 'sku' => 'LP-1', 'sale_price' => '45,000', 'track_stock' => 1, 'is_active' => 1])->assertRedirect();
        $this->post('/products/service', ['name' => 'Installation', 'sale_price' => '500', 'track_stock' => 1, 'is_active' => 1])->assertRedirect();

        $this->assertTrue(Product::where('name', 'Laptop')->firstOrFail()->isStocked());
        $this->assertFalse(Product::where('name', 'Installation')->firstOrFail()->isStocked()); // a service never moves stock
        $this->get('/products/service')->assertOk()->assertSee('Installation')->assertDontSee('Laptop');
        $this->get('/invoices/create')->assertOk();

        // switched off: the screens are gone, the records are kept
        $this->put('/settings/modules', ['modules' => []]);
        $this->get('/products/product')->assertNotFound();
        $this->assertSame(2, Product::count());

        // Starter never has it
        $this->activeCompany('Solo', Plan::STARTER);
        $this->get('/products/product')->assertNotFound();
        $this->get('/invoices')->assertOk();
    }

    public function test_an_administrator_suspends_a_company_and_changes_prices(): void
    {
        $company = $this->activeCompany();
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/companies')->assertOk()->assertSee('Acme');
        $this->post('/admin/companies/'.$company->id.'/suspend')->assertSessionHas('success');
        $this->assertSame(Company::SUSPENDED, $company->fresh()->status);
        $this->get('/dashboard')->assertRedirect(route('billing.show')); // the company's own user is shut out
        $this->post('/admin/companies/'.$company->id.'/suspend');
        $this->assertTrue($company->fresh()->isUsable());

        $starter = Plan::where('code', Plan::STARTER)->firstOrFail();
        $this->get('/admin/plans')->assertOk();
        $this->put('/admin/plans/'.$starter->id, ['name' => 'Starter', 'setup_fee' => '250', 'annual_fee' => '120'])->assertSessionHas('success');
        $this->put('/admin/plans/'.$starter->id, ['name' => 'Starter', 'setup_fee' => '0', 'annual_fee' => '0'])->assertSessionHasErrors('annual_fee'); // no free plan

        $this->assertSame('370.00', Billing::quote($this->company('New', Plan::STARTER))['amount']);
    }

    public function test_exports_need_the_export_permission(): void
    {
        $company = $this->activeCompany();
        $this->get('/reports/trial-balance?export=csv')->assertOk()->assertHeader('content-disposition');
        $this->get('/money/in?export=csv')->assertOk();

        $viewer = $this->user('viewer@acme.test');
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer, 'web');
        $this->get('/reports/trial-balance?export=csv')->assertForbidden();
    }
}
