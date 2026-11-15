<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Plan;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\WhatsApp\WhatsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LanguageWhatsappApiTest extends TestCase
{
    /* ------------------------------------------------------------------ languages */

    public function test_the_language_switch_is_remembered_and_dari_reads_right_to_left(): void
    {
        $this->activeCompany();

        $this->post('/locale', ['locale' => 'fa'])->assertRedirect();
        $this->assertSame('fa', auth()->user()->fresh()->locale);
        $this->get('/dashboard')->assertOk()->assertSee('dir="rtl"', false)->assertSee('داشبورد');

        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_every_translation_keeps_the_placeholders_of_its_english_text(): void
    {
        foreach (['fa', 'ps'] as $language) {
            $texts = json_decode(file_get_contents(base_path('lang/'.$language.'.json')), true);
            $this->assertNotEmpty($texts);
            foreach ($texts as $english => $translated) {
                preg_match_all('/:[a-z_]+/', $english, $want);
                preg_match_all('/:[a-z_]+/', $translated, $have);
                sort($want[0]);
                sort($have[0]);
                $this->assertSame($want[0], $have[0], $language.': '.$english);
            }
        }
    }

    /* ------------------------------------------------------------------ WhatsApp */

    public function test_phone_numbers_are_made_international(): void
    {
        $this->assertSame('93700123456', WhatsApp::number('+93 700 123 456'));
        $this->assertSame('93700123456', WhatsApp::number('0093700123456'));
        $this->assertSame('93700123456', WhatsApp::number('0700123456', '93'));
        $this->assertNull(WhatsApp::number('0700123456'));
        $this->assertNull(WhatsApp::number('12'));
    }

    public function test_a_message_goes_to_relaywa_with_the_company_key_and_is_logged(): void
    {
        $company = $this->activeCompany();
        Http::fake(['relaywa.com/*' => Http::response(['id' => 'wamid-1'], 200)]);

        $this->put('/settings/whatsapp', ['session_key' => 'secret-key', 'is_enabled' => 1, 'country_code' => '93'])->assertSessionHas('success');
        // the key is encrypted in the database and never printed on the page
        $this->assertStringNotContainsString('secret-key', (string) DB::table('whatsapp_settings')->value('session_key'));
        $this->get('/settings/whatsapp')->assertOk()->assertDontSee('secret-key');

        $this->post('/whatsapp/send', ['to' => '0700123456', 'body' => 'Hello', 'kind' => 'custom'])->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->url() === 'https://relaywa.com/api/send-message'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && $request['to'] === '93700123456' && $request['text'] === 'Hello');
        $this->assertDatabaseHas('whatsapp_messages', ['company_id' => $company->id, 'to' => '93700123456', 'status' => WhatsappMessage::ACCEPTED, 'provider_id' => 'wamid-1']);
    }

    public function test_a_refused_message_is_logged_as_failed_and_nothing_is_sent_without_a_key(): void
    {
        $company = $this->activeCompany();
        Http::fake(['relaywa.com/*' => Http::response([], 401)]);

        $this->post('/whatsapp/send', ['to' => '93700123456', 'body' => 'Hello', 'kind' => 'custom'])->assertSessionHas('danger');
        Http::assertNothingSent();

        WhatsappSetting::of($company)->forceFill(['session_key' => 'wrong', 'is_enabled' => true])->save();
        $this->post('/whatsapp/send', ['to' => '93700123456', 'body' => 'Hello', 'kind' => 'custom'])->assertSessionHas('danger');
        $this->assertDatabaseHas('whatsapp_messages', ['company_id' => $company->id, 'status' => WhatsappMessage::FAILED]);
    }

    public function test_a_viewer_cannot_send_or_change_whatsapp(): void
    {
        $company = $this->activeCompany();
        $viewer = $this->user('viewer@acme.test');
        $company->users()->attach($viewer->id, ['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer, 'web');

        $this->post('/whatsapp/send', ['to' => '93700123456', 'body' => 'Hello', 'kind' => 'custom'])->assertForbidden();
        $this->get('/settings/whatsapp')->assertForbidden();
        $this->get('/settings/api')->assertForbidden();
    }

    /* ------------------------------------------------------------------ API */

    public function test_an_api_key_opens_its_own_company_only(): void
    {
        $company = $this->activeCompany();
        $this->post('/settings/api', ['name' => 'Mobile', 'access' => 'read'])->assertSessionHas('api_key');
        $key = session('api_key');
        // only the hash is stored
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $key]);
        $this->assertDatabaseHas('api_tokens', ['token_hash' => hash('sha256', $key), 'company_id' => $company->id]);

        $other = $this->company('Other', Plan::STARTER);
        $foreign = \App\Models\Account::withoutGlobalScope('company')->where('company_id', $other->id)->where('is_group', false)->firstOrFail();

        auth('web')->logout();
        app()->forgetInstance('current.company');

        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $headers = ['Authorization' => 'Bearer '.$key];
        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('company.id', $company->id);
        $mine = \App\Models\Account::withoutGlobalScope('company')->where('company_id', $company->id)->pluck('id')->all();
        $listed = $this->getJson('/api/v1/accounts?per_page=100', $headers)->assertOk()->json('data');
        $this->assertNotEmpty($listed);
        $this->assertSame([], array_diff(array_column($listed, 'id'), $mine));
        $this->getJson('/api/v1/accounts/'.$foreign->id, $headers)->assertNotFound();
    }

    public function test_a_read_only_key_cannot_write_and_a_revoked_key_stops_working(): void
    {
        $company = $this->activeCompany();
        [$token, $key] = ApiToken::issue($company, auth()->user(), 'Reader', false);
        auth('web')->logout();
        app()->forgetInstance('current.company');
        $headers = ['Authorization' => 'Bearer '.$key];

        $this->postJson('/api/v1/money', [], $headers)->assertForbidden();
        $this->getJson('/api/v1/money', $headers)->assertOk();

        $token->forceFill(['revoked_at' => now()])->save();
        app()->forgetInstance('current.company');
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }

    public function test_the_api_closes_when_the_subscription_is_not_active(): void
    {
        $company = $this->company('Pending');
        [, $key] = ApiToken::issue($company, $company->users()->first(), 'Early', true);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$key])->assertStatus(402);
    }
}
