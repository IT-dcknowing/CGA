<?php

namespace Tests\Feature\Support;

use App\Enums\SettingKey;
use App\Models\Cga;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_the_default_value_when_nothing_is_stored(): void
    {
        $resolved = (new Settings)->resolve(SettingKey::MembershipFee);

        $this->assertSame(['value' => 25000, 'source' => 'default'], $resolved);
    }

    public function test_network_value_replaces_the_default(): void
    {
        $settings = new Settings;
        $settings->set(SettingKey::SmsMonthlyQuota, 300);

        $this->assertSame(['value' => 300, 'source' => 'network'], $settings->resolve(SettingKey::SmsMonthlyQuota, Cga::factory()->create()->id));
    }

    public function test_cga_value_replaces_the_network_value_for_that_cga_only(): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        $settings = new Settings;
        $settings->set(SettingKey::MembershipFee, 20000);
        $settings->set(SettingKey::MembershipFee, 30000, $cgaA->id);

        $this->assertSame(['value' => 30000, 'source' => 'cga'], $settings->resolve(SettingKey::MembershipFee, $cgaA->id));
        $this->assertSame(['value' => 20000, 'source' => 'network'], $settings->resolve(SettingKey::MembershipFee, $cgaB->id));
    }

    public function test_cga_value_is_ignored_for_a_network_level_setting(): void
    {
        $cga = Cga::factory()->create();
        Setting::create(['cga_id' => $cga->id, 'key' => SettingKey::CncgaAnnualFee->value, 'value' => 1]);

        $value = (new Settings)->get(SettingKey::CncgaAnnualFee, $cga->id);

        $this->assertSame(750000, $value);
    }

    public function test_all_resolves_every_setting_in_one_pass(): void
    {
        $settings = new Settings;
        $settings->set(SettingKey::ApprovalExpiryAlertMonths, [9, 6, 3]);

        $all = $settings->all();

        $this->assertSame(array_column(SettingKey::cases(), 'value'), array_keys($all));
        $this->assertSame([9, 6, 3], $all['approval_expiry_alert_months']['value']);
        $this->assertTrue($all['attestation_requires_paid_fees']['value']);
    }
}
