<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Obligation;
use App\Models\Regime;
use App\Models\User;
use App\Support\Adherents;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdherentObligationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_validating_a_dossier_proposes_the_obligations_of_its_profile(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->complete()->for($cga)->create([
            'regime_id' => Regime::firstWhere('code', 'TEE')->id,
            'employees' => null,
        ]);
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $this->withToken($token)->postJson("/api/v1/adherents/{$draft->id}/validate")->assertOk();
        $response = $this->withToken($token)->getJson("/api/v1/adherents/{$draft->id}/obligations");

        $response->assertOk()->assertJsonPath('meta.proposed', 6);
        $this->assertSame(['ncc', 'tee', 'ef', 'e302', 'fne', 'mun_tax'], $response->json('data.*.obligation.code'));
        $this->assertSame(['proposed'], array_values(array_unique($response->json('data.*.status'))));
    }

    public function test_gerant_confirms_all_proposals_at_once(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/obligations/confirm-proposals");

        $response->assertOk()
            ->assertJsonPath('meta.confirmed_now', 6)
            ->assertJsonPath('meta.confirmed', 6)
            ->assertJsonPath('meta.proposed', 0);
    }

    public function test_dismissing_an_obligation_requires_a_reason(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);
        $assignment = $adherent->adherentObligations()->first();
        $token = $this->tokenForRole(Role::ExpertComptable, $cga);

        $this->withToken($token)->patchJson("/api/v1/adherents/{$adherent->id}/obligations/{$assignment->id}", ['status' => 'dismissed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => "Indiquez pourquoi cette obligation ne concerne pas l'adhérent."]);
        $response = $this->withToken($token)->patchJson("/api/v1/adherents/{$adherent->id}/obligations/{$assignment->id}", [
            'status' => 'dismissed',
            'reason' => "Pas d'activité commerciale sur la commune",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'dismissed')
            ->assertJsonPath('data.dismissal_reason', "Pas d'activité commerciale sur la commune");
    }

    public function test_dismissed_obligation_is_not_proposed_again(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);
        $assignment = $adherent->adherentObligations()->first();
        $assignment->forceFill(['status' => AssignmentStatus::Dismissed, 'dismissal_reason' => 'Sans objet'])->save();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/obligations/propose");

        $response->assertOk()->assertJsonPath('meta.created', 0);
        $this->assertSame(AssignmentStatus::Dismissed, $assignment->fresh()->status);
    }

    public function test_hiring_employees_proposes_the_social_obligations(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);

        $this->withToken($this->tokenForRole(Role::Comptable, $cga))
            ->patchJson("/api/v1/adherents/{$adherent->id}", ['employees' => 3])
            ->assertOk();

        $proposed = $adherent->adherentObligations()->where('status', AssignmentStatus::Proposed)->with('obligation')->get()->pluck('obligation.code');
        $this->assertContains('its', $proposed);
        $this->assertContains('cnps_cot', $proposed);
        $this->assertContains('cmu_enr', $proposed);
    }

    public function test_confirmed_obligation_that_no_longer_applies_is_flagged(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->bakeryWithObligations($cga);
        app(Adherents::class)->changeRegime($adherent, Regime::firstWhere('code', 'TEE'), now()->toImmutable(), 'Surclassement', User::factory()->create());

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->getJson("/api/v1/adherents/{$adherent->id}/obligations");

        $micro = collect($response->json('data'))->firstWhere('obligation.code', 'micro');
        $this->assertSame('confirmed', $micro['status']);
        $this->assertFalse($micro['applicable']);
        $this->assertSame('proposed', collect($response->json('data'))->firstWhere('obligation.code', 'tee')['status']);
    }

    public function test_cga_adds_an_obligation_the_engine_did_not_propose(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);
        $tva = Obligation::firstWhere('code', 'tva');
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $response = $this->withToken($token)->postJson("/api/v1/adherents/{$adherent->id}/obligations", ['obligation_id' => $tva->id]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.applicable', false);
        $this->withToken($token)->postJson("/api/v1/adherents/{$adherent->id}/obligations", ['obligation_id' => $tva->id])
            ->assertConflict()
            ->assertJsonPath('code', 'obligation_already_assigned');
    }

    public function test_secretary_cannot_change_obligations(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/obligations/confirm-proposals");

        $response->assertForbidden();
        $this->assertSame(0, $adherent->adherentObligations()->where('status', AssignmentStatus::Confirmed)->count());
    }

    public function test_obligation_of_another_adherent_cannot_be_changed_through_this_url(): void
    {
        $cga = Cga::factory()->create();
        $adherent = $this->validatedTeeAdherent($cga);
        $otherAssignment = $this->validatedTeeAdherent($cga)->adherentObligations()->first();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->patchJson("/api/v1/adherents/{$adherent->id}/obligations/{$otherAssignment->id}", ['status' => 'confirmed']);

        $response->assertNotFound();
        $this->assertSame(AssignmentStatus::Proposed, $otherAssignment->fresh()->status);
    }

    public function test_obligations_of_an_adherent_of_another_cga_return_404(): void
    {
        $adherent = $this->validatedTeeAdherent(Cga::factory()->create());

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson("/api/v1/adherents/{$adherent->id}/obligations");

        $response->assertNotFound();
    }

    private function validatedTeeAdherent(Cga $cga): Adherent
    {
        $adherent = Adherent::factory()->complete()->for($cga)->create([
            'regime_id' => Regime::firstWhere('code', 'TEE')->id,
            'employees' => null,
        ]);
        app(Adherents::class)->validate($adherent, User::factory()->create());

        return $adherent->fresh();
    }
}
