<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\TaxCenter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_any_cga_agent_sees_the_profile_of_own_cga(): void
    {
        $center = TaxCenter::factory()->create();
        $cga = Cga::factory()->create(['tax_region_id' => $center->tax_region_id, 'tax_center_id' => $center->id]);
        Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->getJson('/api/v1/cga/profile');

        $response->assertOk()
            ->assertJsonPath('data.id', $cga->id)
            ->assertJsonPath('data.tax_region.id', $center->tax_region_id)
            ->assertJsonPath('data.tax_center.id', $center->id)
            ->assertJsonPath('data.branding', ['logo' => false, 'signature' => false, 'stamp' => false]);
    }

    public function test_gerant_completes_the_profile(): void
    {
        $cga = Cga::factory()->create(['abbreviation' => null]);
        $center = TaxCenter::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->patchJson('/api/v1/cga/profile', [
            'abbreviation' => 'dck',
            'ncc' => '1864699 A',
            'phone' => '27 22 42 14 43',
            'manager_name' => 'KEYMAN Constant',
            'tax_region_id' => $center->tax_region_id,
            'tax_center_id' => $center->id,
            'next_member_seq' => 143,
        ]);

        $response->assertOk()->assertJsonPath('data.abbreviation', 'DCK');
        $cga->refresh();
        $this->assertSame('DCK', $cga->abbreviation);
        $this->assertSame('+2252722421443', $cga->phone);
        $this->assertSame($center->id, $cga->tax_center_id);
        $this->assertSame(143, $cga->next_member_seq);
    }

    public function test_gerant_cannot_change_name_approval_or_status(): void
    {
        $cga = Cga::factory()->pending()->create(['name' => 'CGA Officiel']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->patchJson('/api/v1/cga/profile', [
            'name' => 'Nouveau nom',
            'approval_no' => '1/DGI',
            'approval_date' => '2020-01-01',
            'status' => 'active',
        ]);

        $response->assertOk();
        $cga->refresh();
        $this->assertSame('CGA Officiel', $cga->name);
        $this->assertNull($cga->approval_no);
        $this->assertSame('pending', $cga->status->value);
    }

    public function test_returns_403_to_cga_roles_other_than_gerant(): void
    {
        $cga = Cga::factory()->create(['city' => 'Abidjan']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))
            ->patchJson('/api/v1/cga/profile', ['city' => 'Bouaké']);

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame('Abidjan', $cga->fresh()->city);
    }

    public function test_rejects_a_tax_center_of_another_regional_directorate(): void
    {
        $cga = Cga::factory()->create();
        $center = TaxCenter::factory()->create();
        $otherCenter = TaxCenter::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->patchJson('/api/v1/cga/profile', [
            'tax_region_id' => $center->tax_region_id,
            'tax_center_id' => $otherCenter->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'tax_center_id' => "Ce centre des impôts n'existe pas ou n'appartient pas à la direction régionale choisie.",
        ]);
    }

    public function test_suspended_cga_can_read_its_profile_but_not_change_it(): void
    {
        $cga = Cga::factory()->suspended()->create(['city' => 'Abidjan']);
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $this->withToken($token)->getJson('/api/v1/cga/profile')->assertOk();
        $response = $this->withToken($token)->patchJson('/api/v1/cga/profile', ['city' => 'Bouaké']);

        $response->assertForbidden()
            ->assertExactJson([
                'message' => 'Ce CGA est suspendu : seule la consultation est possible.',
                'code' => 'cga_suspended',
            ]);
        $this->assertSame('Abidjan', $cga->fresh()->city);
    }
}
