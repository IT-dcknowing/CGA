<?php

namespace Tests\Feature\Http\Controllers\Api\Cncga;

use App\Enums\CgaStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Cga;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CgaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_cgas_by_name_with_pagination(): void
    {
        Cga::factory()->create(['name' => 'CGA Bêta']);
        Cga::factory()->create(['name' => 'CGA Alpha']);

        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))->getJson('/api/v1/cncga/cgas');

        $response->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame(['CGA Alpha', 'CGA Bêta'], $response->json('data.*.name'));
    }

    public function test_filters_by_status_and_search_term(): void
    {
        Cga::factory()->create(['name' => 'CGA Lagune', 'city' => 'Abidjan']);
        Cga::factory()->suspended()->create(['name' => 'CGA Plateau', 'city' => 'Abidjan']);
        Cga::factory()->create(['name' => 'CGA Savane', 'city' => 'Korhogo']);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->getJson('/api/v1/cncga/cgas?status=active&search=abidjan');

        $response->assertOk();
        $this->assertSame(['CGA Lagune'], $response->json('data.*.name'));
    }

    public function test_returns_403_in_cga_space(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/cncga/cgas');

        $response->assertForbidden()->assertJsonPath('code', 'space_forbidden');
    }

    public function test_returns_403_to_bureau_members(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::MembreBureau))->getJson('/api/v1/cncga/cgas');

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_creates_a_minimal_fiche_pending_until_approval(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))->postJson('/api/v1/cncga/cgas', [
            'name' => 'CGA de la Marahoué',
            'city' => 'Bouaflé',
            'phone' => '27 30 00 00 01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.abbreviation', null)
            ->assertJsonPath('data.phone', '+2252730000001');
        $this->assertDatabaseHas('cgas', ['name' => 'CGA de la Marahoué', 'status' => 'pending']);
    }

    public function test_creates_an_active_cga_when_approval_is_recorded(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))->postJson('/api/v1/cncga/cgas', [
            'name' => 'CGA agréé',
            'approval_no' => '000/DEMO/DGI',
            'approval_date' => '2018-03-29',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.approval_date', '2018-03-29');
    }

    public function test_store_returns_422_with_french_messages(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson('/api/v1/cncga/cgas', ['abbreviation' => 'd']);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'name' => 'Le champ dénomination est obligatoire.',
            'abbreviation' => "L'abréviation doit contenir de 2 à 10 lettres majuscules ou chiffres.",
        ]);
        $this->assertSame(0, Cga::count());
    }

    public function test_store_rejects_an_abbreviation_already_used_whatever_its_case(): void
    {
        Cga::factory()->create(['abbreviation' => 'DCK']);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson('/api/v1/cncga/cgas', ['name' => 'Autre CGA', 'abbreviation' => 'dck']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['abbreviation' => 'Cette valeur du champ abréviation est déjà utilisée.']);
    }

    public function test_updates_the_fiche_and_records_who_changed_it(): void
    {
        $cga = Cga::factory()->create(['city' => 'Abidjan']);

        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))
            ->patchJson("/api/v1/cncga/cgas/{$cga->id}", ['city' => 'Bouaké', 'is_subscribed' => true]);

        $response->assertOk()
            ->assertJsonPath('data.city', 'Bouaké')
            ->assertJsonPath('data.is_subscribed', true);
        $log = ActivityLog::where('event', 'updated')->whereMorphedTo('subject', $cga)->sole();
        $newValues = $log->properties['new'];
        ksort($newValues);
        $this->assertSame(['city' => 'Bouaké', 'is_subscribed' => true], $newValues);
        $this->assertNotNull($log->causer_id);
    }

    public function test_update_rejects_an_approval_expiry_before_the_recorded_approval_date(): void
    {
        $cga = Cga::factory()->create(['approval_no' => '1/DGI', 'approval_date' => '2020-01-01']);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->patchJson("/api/v1/cncga/cgas/{$cga->id}", ['approval_expires_at' => '2019-12-31']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['approval_expires_at']);
    }

    public function test_presidente_suspends_a_cga_with_a_reason(): void
    {
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/suspend", ['reason' => 'Cotisation impayée depuis 90 jours']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.suspension.reason', 'Cotisation impayée depuis 90 jours');
        $this->assertSame(CgaStatus::Suspended, $cga->fresh()->status);
        $this->assertNotNull($cga->fresh()->suspended_at);
    }

    public function test_suspension_requires_a_reason(): void
    {
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/suspend");

        $response->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Le champ motif est obligatoire.']);
        $this->assertSame(CgaStatus::Active, $cga->fresh()->status);
    }

    public function test_agent_cncga_cannot_suspend(): void
    {
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/suspend", ['reason' => 'Motif']);

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame(CgaStatus::Active, $cga->fresh()->status);
    }

    public function test_suspending_a_suspended_cga_returns_409(): void
    {
        $cga = Cga::factory()->suspended()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/suspend", ['reason' => 'Motif']);

        $response->assertConflict()->assertJsonPath('code', 'cga_already_suspended');
    }

    public function test_reactivation_restores_active_status_of_an_approved_cga(): void
    {
        $cga = Cga::factory()->suspended()->create(['approval_no' => '1/DGI', 'approval_date' => '2020-01-01']);

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/reactivate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.suspension', null);
    }

    public function test_reactivation_returns_a_cga_without_approval_to_pending(): void
    {
        $cga = Cga::factory()->suspended()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/reactivate");

        $response->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_reactivating_a_cga_that_is_not_suspended_returns_409(): void
    {
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))
            ->postJson("/api/v1/cncga/cgas/{$cga->id}/reactivate");

        $response->assertConflict()->assertJsonPath('code', 'cga_not_suspended');
    }

    public function test_show_returns_404_for_an_unknown_cga(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))->getJson('/api/v1/cncga/cgas/999');

        $response->assertNotFound()->assertJsonPath('code', 'not_found');
    }
}
