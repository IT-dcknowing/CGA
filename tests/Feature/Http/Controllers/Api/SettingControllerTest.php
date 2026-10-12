<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\SettingKey;
use App\Models\ActivityLog;
use App\Models\Cga;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_presidente_sees_network_values_with_their_origin(): void
    {
        (new Settings)->set(SettingKey::CncgaAnnualFee, 800000);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))->getJson('/api/v1/settings');

        $response->assertOk()->assertJsonCount(count(SettingKey::cases()), 'data');
        $this->assertSame([
            'key' => 'cncga_annual_fee',
            'label' => 'Cotisation annuelle au CNCGA (FCFA)',
            'value' => 800000,
            'source' => 'network',
            'default' => 750000,
            'level' => 'network',
            'editable' => true,
            'question' => 'Q56',
            'to_be_confirmed' => true,
        ], $this->setting($response, 'cncga_annual_fee'));
    }

    public function test_gerant_sees_values_of_own_cga_and_network_values_as_read_only(): void
    {
        $cga = Cga::factory()->create();
        (new Settings)->set(SettingKey::MembershipFee, 30000, $cga->id);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->getJson('/api/v1/settings');

        $response->assertOk();
        $this->assertSame(30000, $this->setting($response, 'membership_fee')['value']);
        $this->assertSame('cga', $this->setting($response, 'membership_fee')['source']);
        $this->assertTrue($this->setting($response, 'membership_fee')['editable']);
        $this->assertFalse($this->setting($response, 'sms_monthly_quota')['editable']);
    }

    public function test_returns_403_to_cga_roles_other_than_gerant(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Comptable))->getJson('/api/v1/settings');

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_presidente_sets_the_network_value(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->putJson('/api/v1/settings/cncga_annual_fee', ['value' => 800000]);

        $response->assertOk()
            ->assertJsonPath('data.value', 800000)
            ->assertJsonPath('data.source', 'network');
        $setting = Setting::sole();
        $this->assertNull($setting->cga_id);
        $this->assertSame(800000, $setting->value);
        $this->assertSame(1, ActivityLog::where('event', 'created')->whereMorphedTo('subject', $setting)->count());
    }

    public function test_gerant_sets_a_value_for_own_cga_only(): void
    {
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->putJson('/api/v1/settings/membership_fee', ['value' => 30000]);

        $response->assertOk()->assertJsonPath('data.source', 'cga');
        $this->assertSame($cga->id, Setting::sole()->cga_id);
    }

    public function test_gerant_gets_403_on_a_network_level_setting(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))
            ->putJson('/api/v1/settings/sms_monthly_quota', ['value' => 1000]);

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'Ce paramètre est fixé par le CNCGA pour tout le réseau.',
                'code' => 'setting_not_editable',
            ]);
        $this->assertSame(0, Setting::count());
    }

    public function test_agent_cncga_gets_403_when_updating(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))
            ->putJson('/api/v1/settings/sms_monthly_quota', ['value' => 1000]);

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame(0, Setting::count());
    }

    public function test_rejects_a_value_that_breaks_the_setting_rules(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->putJson('/api/v1/settings/cncga_fee_due_date', ['value' => '31/13']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['value' => "Le format du champ valeur n'est pas valide."]);
        $this->assertSame(0, Setting::count());
    }

    public function test_rejects_an_invalid_item_in_a_list_setting(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->putJson('/api/v1/settings/approval_expiry_alert_months', ['value' => [6, 0]]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['value.1']);
    }

    public function test_returns_404_for_an_unknown_setting(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->putJson('/api/v1/settings/inconnu', ['value' => 1]);

        $response->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    /**
     * @return array<string, mixed>
     */
    private function setting(TestResponse $response, string $key): array
    {
        return collect($response->json('data'))->firstWhere('key', $key);
    }
}
