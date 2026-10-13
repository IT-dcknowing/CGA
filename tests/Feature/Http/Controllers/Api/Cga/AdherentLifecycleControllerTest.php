<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\AdherentStatus;
use App\Enums\LegalForm;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Regime;
use App\Models\StrikeOff;
use App\Models\StrikeOffReason;
use App\Models\User;
use App\Support\Adherents;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdherentLifecycleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_validation_gives_the_next_number_of_the_cga(): void
    {
        $cga = Cga::factory()->create(['abbreviation' => 'DCK', 'next_member_seq' => 143]);
        $draft = Adherent::factory()->complete()->for($cga)->create(['joined_at' => '2026-03-15']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.member_no', '2026-DCK-0143')
            ->assertJsonCount(1, 'data.regime_history');
        $this->assertSame(144, $cga->fresh()->next_member_seq);
        $this->assertSame(1, ActivityLog::where('event', 'validated')->whereMorphedTo('subject', $draft)->count());
    }

    public function test_numbering_skips_a_number_already_taken(): void
    {
        $cga = Cga::factory()->create(['abbreviation' => 'DCK', 'next_member_seq' => 143]);
        Adherent::factory()->active()->for($cga)->create(['member_no' => '2026-DCK-0143']);
        $draft = Adherent::factory()->complete()->for($cga)->create(['joined_at' => '2026-03-15']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertOk()->assertJsonPath('data.member_no', '2026-DCK-0144');
        $this->assertSame(145, $cga->fresh()->next_member_seq);
    }

    public function test_historical_number_is_kept_without_using_the_sequence(): void
    {
        $cga = Cga::factory()->create(['abbreviation' => 'DCK', 'next_member_seq' => 143]);
        $draft = Adherent::factory()->complete()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::ExpertComptable, $cga))
            ->postJson("/api/v1/adherents/{$draft->id}/validate", ['historical_member_no' => 'DCK-CGA2019-A001']);

        $response->assertOk()->assertJsonPath('data.member_no', 'DCK-CGA2019-A001');
        $this->assertSame(143, $cga->fresh()->next_member_seq);
    }

    public function test_incomplete_draft_lists_every_missing_field(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'ncc' => 'Ce champ est obligatoire pour valider le dossier.',
            'regime_id',
            'tax_center_id',
            'signatory_name',
            'turnover',
            'joined_at',
        ]);
        $this->assertSame(AdherentStatus::Draft, $draft->fresh()->status);
    }

    public function test_individual_enterprise_needs_its_owner_details(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->complete()->for($cga)->create(['legal_form' => LegalForm::EntrepriseIndividuelle]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertUnprocessable()->assertJsonValidationErrors(['owner_last_name', 'owner_first_names', 'owner_nationality', 'owner_id_document']);
    }

    public function test_rsi_dossier_cannot_be_validated(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->complete()->for($cga)->create(['regime_id' => Regime::firstWhere('code', 'RSI')->id]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertUnprocessable()->assertJsonValidationErrors(['regime_id' => "Seuls les régimes RME et TEE ouvrent droit à l'adhésion."]);
    }

    public function test_cga_without_approval_cannot_validate_a_dossier(): void
    {
        $cga = Cga::factory()->pending()->create();
        $draft = Adherent::factory()->complete()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertConflict()->assertJsonPath('code', 'cga_not_approved');
        $this->assertNull($draft->fresh()->member_no);
    }

    public function test_cga_without_abbreviation_cannot_number_a_dossier(): void
    {
        $cga = Cga::factory()->create(['abbreviation' => null]);
        $draft = Adherent::factory()->complete()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertConflict()->assertJsonPath('code', 'cga_abbreviation_missing');
    }

    public function test_validating_twice_returns_409(): void
    {
        $cga = Cga::factory()->create();
        $adherent = Adherent::factory()->active()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/validate");

        $response->assertConflict()->assertJsonPath('code', 'adherent_not_draft');
    }

    public function test_secretary_cannot_validate(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->complete()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))->postJson("/api/v1/adherents/{$draft->id}/validate");

        $response->assertForbidden();
    }

    public function test_regime_change_is_dated_motivated_and_kept_in_history(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->postJson("/api/v1/adherents/{$adherent->id}/regime", [
            'regime_id' => Regime::firstWhere('code', 'TEE')->id,
            'effective_date' => '2026-06-01',
            'reason' => "Chiffre d'affaires sous le seuil du RME",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.regime.code', 'TEE')
            ->assertJsonCount(2, 'data.regime_history')
            ->assertJsonPath('data.regime_history.1.reason', "Chiffre d'affaires sous le seuil du RME");
        $log = ActivityLog::where('event', 'regime_changed')->whereMorphedTo('subject', $adherent)->sole();
        $this->assertSame(['RME', 'TEE'], [$log->properties['from'], $log->properties['to']]);
    }

    public function test_moving_to_rsi_raises_the_regularisation_alert(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/regime", [
            'regime_id' => Regime::firstWhere('code', 'RSI')->id,
            'effective_date' => '2026-06-01',
            'reason' => 'Reclassement par la DGI',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.alerts.0.code', 'rsi_regularization')
            ->assertJsonPath('data.alerts.0.deadline', '2026-08-30');
    }

    public function test_regime_change_cannot_predate_the_previous_one(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/regime", [
            'regime_id' => Regime::firstWhere('code', 'TEE')->id,
            'effective_date' => '2026-01-01',
            'reason' => 'Erreur de date',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['effective_date']);
    }

    public function test_changing_to_the_same_regime_is_refused(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/regime", [
            'regime_id' => $adherent->regime_id,
            'effective_date' => '2026-06-01',
            'reason' => 'Aucun changement',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['regime_id' => "L'adhérent est déjà à ce régime."]);
    }

    public function test_strike_off_records_reason_effective_date_and_observation(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::ExpertComptable, $cga))->postJson("/api/v1/adherents/{$adherent->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'cessation_activite')->id,
            'observation' => "Fermeture définitive de l'établissement",
            'effective_date' => '2026-09-30',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'struck_off')
            ->assertJsonPath('data.strike_off.reason.code', 'cessation_activite')
            ->assertJsonPath('data.strike_off.effective_date', '2026-09-30')
            ->assertJsonPath('data.strike_off.has_document', false);
    }

    public function test_serious_misconduct_requires_a_document(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'manquements_graves')->id,
            'observation' => 'Refus répété de fournir les pièces comptables',
            'effective_date' => '2026-09-30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'document' => 'Une pièce justificative est obligatoire pour ce motif (procès-verbal de la commission de résiliation).',
        ]);
        $this->assertSame(AdherentStatus::Active, $adherent->fresh()->status);
    }

    public function test_strike_off_document_is_stored_privately_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $this->withToken($token)->post("/api/v1/adherents/{$adherent->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'manquements_graves')->id,
            'observation' => 'Refus répété de fournir les pièces comptables',
            'effective_date' => '2026-09-30',
            'document' => UploadedFile::fake()->create('pv-commission.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.strike_off.has_document', true);
        $response = $this->withToken($token)->get("/api/v1/adherents/{$adherent->id}/strike-off/document");

        $response->assertOk();
        Storage::disk('local')->assertExists(StrikeOff::sole()->document_path);
    }

    public function test_other_reason_requires_details(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$adherent->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'autre')->id,
            'observation' => 'Radiation',
            'effective_date' => '2026-09-30',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['details' => 'Précisez le motif de la radiation.']);
    }

    public function test_accountant_cannot_strike_off(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->postJson("/api/v1/adherents/{$adherent->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'cessation_activite')->id,
            'observation' => 'Radiation',
            'effective_date' => '2026-09-30',
        ]);

        $response->assertForbidden();
    }

    public function test_draft_cannot_be_struck_off(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson("/api/v1/adherents/{$draft->id}/strike-off", [
            'reason_id' => StrikeOffReason::firstWhere('code', 'cessation_activite')->id,
            'observation' => 'Radiation',
            'effective_date' => '2026-09-30',
        ]);

        $response->assertConflict()->assertJsonPath('code', 'adherent_not_active');
    }

    public function test_gerant_cancels_within_30_days_and_the_dossier_is_restored_with_its_number(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();
        $memberNo = $adherent->member_no;
        $this->struckOff($adherent);
        $this->travel(29)->days();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/strike-off/cancel", ['reason' => 'Radiation saisie par erreur']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.member_no', $memberNo)
            ->assertJsonPath('data.strike_off', null);
        $this->assertSame('Radiation saisie par erreur', StrikeOff::sole()->cancellation_reason);
    }

    public function test_cancellation_after_30_days_is_refused(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();
        $this->struckOff($adherent);
        $this->travel(31)->days();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/strike-off/cancel", ['reason' => 'Trop tard']);

        $response->assertConflict()->assertJsonPath('code', 'strike_off_cancellation_expired');
        $this->assertSame(AdherentStatus::StruckOff, $adherent->fresh()->status);
    }

    public function test_expert_cannot_cancel_a_strike_off(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();
        $this->struckOff($adherent);

        $response = $this->withToken($this->tokenForRole(Role::ExpertComptable, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/strike-off/cancel", ['reason' => 'Erreur']);

        $response->assertForbidden();
    }

    public function test_cancellation_is_refused_once_the_ncc_has_a_new_open_dossier(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$cga, $adherent] = $this->validatedAdherent();
        $this->struckOff($adherent);
        Adherent::factory()->for($cga)->create(['ncc' => $adherent->ncc]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->postJson("/api/v1/adherents/{$adherent->id}/strike-off/cancel", ['reason' => 'Erreur']);

        $response->assertConflict()->assertJsonPath('code', 'ncc_already_open');
    }

    /**
     * @return array{Cga, Adherent}
     */
    private function validatedAdherent(): array
    {
        $cga = Cga::factory()->create(['abbreviation' => 'DCK']);
        $adherent = Adherent::factory()->complete()->for($cga)->create([
            'regime_id' => Regime::firstWhere('code', 'RME')->id,
            'joined_at' => '2026-03-15',
        ]);
        app(Adherents::class)->validate($adherent, User::factory()->create());

        return [$cga, $adherent->fresh()];
    }

    private function struckOff(Adherent $adherent): void
    {
        app(Adherents::class)->strikeOff(
            $adherent,
            StrikeOffReason::firstWhere('code', 'cessation_activite'),
            ['observation' => 'Fermeture', 'effective_date' => '2026-09-30'],
            null,
            User::factory()->create(),
        );
    }
}
