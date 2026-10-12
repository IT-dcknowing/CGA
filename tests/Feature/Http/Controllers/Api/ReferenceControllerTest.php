<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\Role;
use App\Models\Sector;
use App\Models\TaxCenter;
use App\Models\TaxRegion;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regimes_open_membership_to_rme_and_tee_only(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $response = $this->withToken($this->tokenForRole(Role::Adherent))->getJson('/api/v1/ref/regimes');

        $response->assertOk();
        $this->assertSame(['RME', 'TEE', 'RSI'], $response->json('data.*.code'));
        $this->assertSame([true, true, false], $response->json('data.*.allows_membership'));
    }

    public function test_sectors_list_only_active_sectors(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Sector::where('code', 'NUM001')->update(['is_active' => false]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/ref/sectors');

        $response->assertOk()->assertJsonCount(7, 'data');
        $this->assertNotContains('NUM001', $response->json('data.*.code'));
    }

    public function test_strike_off_reasons_flag_the_document_and_details_requirements(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/ref/strike-off-reasons');

        $response->assertOk()->assertJsonCount(9, 'data');
        $reasons = collect($response->json('data'))->keyBy('code');
        $this->assertSame(['manquements_graves'], $reasons->where('requires_document', true)->keys()->all());
        $this->assertSame(['autre'], $reasons->where('requires_details', true)->keys()->all());
    }

    public function test_tax_centers_are_grouped_by_regional_directorate(): void
    {
        $region = TaxRegion::factory()->create(['label' => 'A — Direction régionale']);
        $center = TaxCenter::factory()->for($region, 'region')->create();
        TaxRegion::factory()->create(['label' => 'B — Direction sans centre']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/ref/tax-centers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $region->id)
            ->assertJsonPath('data.0.centers.0.id', $center->id)
            ->assertJsonCount(0, 'data.1.centers');
    }

    public function test_obligations_list_the_national_referential_with_its_version(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))->getJson('/api/v1/ref/obligations');

        $response->assertOk()
            ->assertJsonCount(42, 'data')
            ->assertJsonPath('meta.referential_version', '2026-08-08')
            ->assertJsonPath('data.0.code', 'ncc')
            ->assertJsonPath('data.0.administration.code', 'dgi');
    }

    public function test_obligations_return_403_in_adherent_space(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Adherent))->getJson('/api/v1/ref/obligations');

        $response->assertForbidden()->assertJsonPath('code', 'space_forbidden');
    }
}
