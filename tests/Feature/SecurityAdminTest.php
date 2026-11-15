<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\Modules;
use App\Support\Totp;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityAdminTest extends TestCase
{
    public function test_one_time_codes_follow_the_standard(): void
    {
        // RFC 6238 test secret "12345678901234567890"
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertTrue(Totp::verify($secret, '287 082', 59 + 30)); // the code of 30 seconds ago still works
        $this->assertFalse(Totp::verify($secret, '287082', 59 + 120));
    }

    public function test_two_factor_sign_in_needs_the_password_and_then_a_code(): void
    {
        $company = $this->activeCompany();
        $user = $company->users()->first();

        // switching it on needs a first right code; the recovery codes are shown once
        $this->post('/profile/two-factor')->assertRedirect();
        $secret = (string) $user->fresh()->two_factor_secret;
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->post('/profile/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $response = $this->post('/profile/two-factor/confirm', ['code' => Totp::code($secret)])->assertSessionHas('recovery_codes');
        $recovery = $response->getSession()->get('recovery_codes');
        $this->assertCount(8, $recovery);
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->get('/profile')->assertOk();

        // sign out; the password alone no longer signs in
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest('web');
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/two-factor')->assertOk();
        $this->post('/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest('web');

        $this->post('/two-factor', ['code' => Totp::code($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh(), 'web');

        // a recovery code signs in once, and only once
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor', ['code' => $recovery[0]])->assertRedirect(route('dashboard'));
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor', ['code' => $recovery[0]])->assertSessionHasErrors('code');
        $this->assertGuest('web');

        // without the first step there is no second step
        session()->forget('two_factor');
        $this->get('/two-factor')->assertRedirect(route('login'));
    }

    public function test_switching_two_factor_off_and_changing_the_password_need_the_current_password(): void
    {
        $this->activeCompany();
        // the signed-in user himself: a second copy of the record would leave his copy out of date
        $user = auth('web')->user();
        $user->forceFill(['two_factor_secret' => Totp::secret(), 'two_factor_confirmed_at' => now()])->save();

        $this->delete('/profile/two-factor', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->delete('/profile/two-factor', ['current_password' => 'password123'])->assertSessionHas('success');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'password123', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHas('success');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-1'])->assertRedirect(route('dashboard'));
    }

    public function test_where_it_is_switched_on_a_new_user_confirms_his_email_first(): void
    {
        config(['billing.verify_email' => true]);
        Notification::fake();

        $this->post('/register', ['name' => 'Fida', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'new@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/start')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk();

        // a link for another address or without a signature opens nothing
        $this->get('/email/verify/'.$user->id.'/'.sha1('other@example.com'))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/start')->assertOk();
    }

    public function test_an_administrator_changes_a_companys_version_and_its_modules_follow(): void
    {
        $company = $this->activeCompany('Solo', Plan::STARTER);
        $this->assertSame([], Modules::activeKeys($company));
        $business = Plan::where('code', Plan::BUSINESS)->firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->get('/admin/companies/'.$company->id)->assertOk()->assertSee('Core accounting only');
        $this->put('/admin/companies/'.$company->id, ['plan_id' => $business->id])->assertSessionHasErrors('note'); // a reason is required
        $this->put('/admin/companies/'.$company->id, ['plan_id' => $business->id, 'subscription_ends_at' => '2028-01-31', 'note' => 'Upgrade paid in cash'])->assertSessionHas('success');

        $company = Company::with('plan')->findOrFail($company->id);
        $this->assertSame(Plan::BUSINESS, $company->plan->code);
        $this->assertCount(7, Modules::activeKeys($company));
        $this->assertSame('2028-01-31', $company->subscription_ends_at->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'changed company subscription', 'company_id' => $company->id, 'admin_id' => $admin->id]);

        // a viewer administrator can look but not change
        $viewer = Admin::create(['name' => 'Viewer', 'email' => 'viewer@fidaledger.test', 'password' => 'password12345', 'role' => 'viewer']);
        $this->actingAs($viewer, 'admin');
        $this->get('/admin/companies/'.$company->id)->assertOk();
        $this->put('/admin/companies/'.$company->id, ['plan_id' => $business->id, 'note' => 'x'])->assertForbidden();
        $this->post('/admin/users/1/toggle')->assertForbidden();
        $this->put('/admin/modules', [])->assertForbidden();
        $this->get('/admin/announcements/create')->assertForbidden();
    }

    public function test_module_entitlements_apply_to_every_company_at_once(): void
    {
        $company = $this->activeCompany();
        $this->actingAs($this->admin(), 'admin');
        $this->get('/admin/modules')->assertOk();

        // payroll is taken out of Business: the company loses it, its records would stay
        $all = array_keys(Modules::all());
        $plans = array_fill_keys(array_diff($all, ['payroll']), ['business']);
        $this->put('/admin/modules', ['available' => array_fill_keys($all, 1), 'plans' => $plans])->assertSessionHas('success');
        $this->assertNotContains('payroll', Modules::activeKeys($company->fresh()));
        $this->assertCount(6, Modules::activeKeys($company->fresh()));

        // a module that is not offered at all is off for everyone, whatever the plans say
        $this->put('/admin/modules', ['available' => ['products' => 1], 'plans' => array_fill_keys($all, ['business'])]);
        $this->assertSame(['products'], Modules::activeKeys($company->fresh()));
    }

    public function test_the_platform_screens_open_and_protect_the_last_super_administrator(): void
    {
        $company = $this->activeCompany();
        $owner = $company->users()->first();
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        foreach (['/admin', '/admin/companies', '/admin/users', '/admin/administrators', '/admin/audit', '/admin/audit?only=platform', '/admin/revenue', '/admin/modules', '/admin/health', '/admin/announcements', '/admin/plans', '/admin/payments'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/admin/revenue')->assertSee('600.00'); // the company's first-year payment

        // the only super administrator cannot be demoted or switched off
        $this->put('/admin/administrators/'.$admin->id, ['role' => 'viewer', 'is_active' => 1])->assertSessionHas('danger');
        $this->post('/admin/administrators', ['name' => 'Second', 'email' => 'second@fidaledger.test', 'role' => 'manager', 'password' => 'a-long-password'])->assertSessionHas('success');
        $second = Admin::where('email', 'second@fidaledger.test')->firstOrFail();
        $this->put('/admin/administrators/'.$second->id, ['role' => 'viewer', 'is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($second->fresh()->is_active);

        // a blocked user cannot sign in
        $this->post('/admin/users/'.$owner->id.'/toggle')->assertSessionHas('success');
        auth('web')->logout();
        $this->post('/login', ['email' => $owner->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->post('/admin/users/'.$owner->id.'/toggle');

        // an announcement reaches the company's dashboard only inside its dates
        $this->post('/admin/announcements', ['title' => 'Maintenance on Friday', 'tone' => 'warning', 'is_active' => 1])->assertRedirect();
        Announcement::create(['title' => 'Old news', 'ends_on' => '2026-01-01']);
        $this->workIn($company->fresh());
        $this->get('/dashboard')->assertOk()->assertSee('Maintenance on Friday')->assertDontSee('Old news');
    }
}
