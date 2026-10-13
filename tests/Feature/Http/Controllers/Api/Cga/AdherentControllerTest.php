<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\AdherentStatus;
use App\Enums\Role;
use App\Enums\SettingKey;
use App\Models\ActivityLog;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Regime;
use App\Support\Settings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdherentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_the_adherents_of_the_active_cga(): void
    {
        [$cgaA, $cgaB] = Cga::factory()->count(2)->create();
        Adherent::factory()->count(2)->for($cgaA)->create();
        Adherent::factory()->for($cgaB)->create();

        $response = $this->withToken($this->tokenForRole(Role::Juriste, $cgaA))->getJson('/api/v1/adherents');

        $response->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_filters_drafts_and_searches_by_name(): void
    {
        $cga = Cga::factory()->create();
        Adherent::factory()->for($cga)->create(['company_name' => 'Boulangerie du Port']);
        Adherent::factory()->active()->for($cga)->create(['company_name' => 'Boulangerie Centrale']);
        Adherent::factory()->for($cga)->create(['company_name' => 'Garage Moderne']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->getJson('/api/v1/adherents?status=draft&search=boulangerie');

        $response->assertOk();
        $this->assertSame(['Boulangerie du Port'], $response->json('data.*.company_name'));
    }

    public function test_returns_404_for_an_adherent_of_another_cga(): void
    {
        $otherCgaAdherent = Adherent::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson("/api/v1/adherents/{$otherCgaAdherent->id}");

        $response->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_cannot_change_an_adherent_of_another_cga(): void
    {
        $otherCgaAdherent = Adherent::factory()->create(['company_name' => 'Nom intact']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant))
            ->patchJson("/api/v1/adherents/{$otherCgaAdherent->id}", ['company_name' => 'Piraté']);

        $response->assertNotFound();
        $this->assertSame('Nom intact', $otherCgaAdherent->fresh()->company_name);
    }

    public function test_returns_403_outside_the_cga_space(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Presidente))->getJson('/api/v1/adherents');

        $response->assertForbidden()->assertJsonPath('code', 'space_forbidden');
    }

    public function test_secretary_registers_a_draft_with_the_default_membership_fee_of_the_cga(): void
    {
        $cga = Cga::factory()->create();
        (new Settings)->set(SettingKey::MembershipFee, 30000, $cga->id);

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))->postJson('/api/v1/adherents', [
            'company_name' => 'Atelier Couture Awa',
            'phone' => '05 00 00 00 01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.member_no', null)
            ->assertJsonPath('data.membership_fee', 30000)
            ->assertJsonPath('data.phone', '+2250500000001')
            ->assertJsonPath('data.completeness.ready', false)
            ->assertJsonPath('warnings', []);
        $this->assertContains('ncc', $response->json('data.completeness.missing'));
        $this->assertSame($cga->id, Adherent::sole()->cga_id);
    }

    public function test_juriste_cannot_register_an_adherent(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Juriste))->postJson('/api/v1/adherents', ['company_name' => 'Essai']);

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame(0, Adherent::count());
    }

    public function test_refuses_an_ncc_already_open_in_the_same_cga(): void
    {
        $cga = Cga::factory()->create();
        Adherent::factory()->active()->for($cga)->create(['ncc' => '1234567 A']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->postJson('/api/v1/adherents', [
            'company_name' => 'Doublon',
            'ncc' => '1234567 a',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ncc' => 'Un dossier ouvert porte déjà ce NCC dans votre CGA.']);
    }

    public function test_accepts_the_ncc_of_a_struck_off_dossier_as_a_new_adhesion(): void
    {
        $cga = Cga::factory()->create();
        Adherent::factory()->active()->for($cga)->create(['ncc' => '1234567 A', 'status' => AdherentStatus::StruckOff]);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->postJson('/api/v1/adherents', [
            'company_name' => 'Retour après radiation',
            'ncc' => '1234567 A',
        ]);

        $response->assertCreated();
    }

    public function test_warns_when_the_ncc_is_open_in_another_cga_without_naming_it(): void
    {
        Adherent::factory()->active()->for(Cga::factory()->create(['name' => 'CGA Concurrent']))->create(['ncc' => '1234567 A']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable))->postJson('/api/v1/adherents', [
            'company_name' => 'Entreprise qui change de CGA',
            'ncc' => '1234567 A',
        ]);

        $response->assertCreated()->assertJsonPath('warnings.0.code', 'ncc_in_another_cga');
        $this->assertStringNotContainsString('CGA Concurrent', $response->getContent());
    }

    public function test_change_on_a_draft_is_recorded_with_old_and_new_values(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->for($cga)->create(['address' => 'Ancienne adresse']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))
            ->patchJson("/api/v1/adherents/{$draft->id}", ['address' => 'Nouvelle adresse']);

        $response->assertOk()->assertJsonPath('data.address', 'Nouvelle adresse');
        $log = ActivityLog::where('event', 'updated')->whereMorphedTo('subject', $draft)->sole();
        $this->assertSame(['address' => 'Ancienne adresse'], $log->properties['old']);
    }

    public function test_identity_change_on_a_validated_dossier_requires_a_justification(): void
    {
        $cga = Cga::factory()->create();
        $adherent = Adherent::factory()->active()->for($cga)->create(['company_name' => 'Ancien nom']);
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $this->withToken($token)->patchJson("/api/v1/adherents/{$adherent->id}", ['company_name' => 'Nouveau nom'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['change_justification' => "Un justificatif est obligatoire pour changer la raison sociale ou le NCC d'un dossier validé."]);
        $response = $this->withToken($token)->patchJson("/api/v1/adherents/{$adherent->id}", [
            'company_name' => 'Nouveau nom',
            'change_justification' => 'Extrait RCCM modifié du 02/05/2026',
        ]);

        $response->assertOk()->assertJsonPath('data.company_name', 'Nouveau nom');
        $log = ActivityLog::where('event', 'updated')->whereMorphedTo('subject', $adherent)->sole();
        $this->assertSame('Extrait RCCM modifié du 02/05/2026', $log->properties['justification']);
    }

    public function test_regime_of_a_validated_dossier_cannot_be_changed_by_a_simple_update(): void
    {
        $cga = Cga::factory()->create();
        $adherent = Adherent::factory()->active()->for($cga)->create();
        $tee = Regime::firstOrCreate(['code' => 'TEE'], ['label' => "Taxe d'État de l'entreprenant", 'allows_membership' => true]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->patchJson("/api/v1/adherents/{$adherent->id}", ['regime_id' => $tee->id]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'regime_id' => "Le régime d'un dossier validé se change par le changement de régime, daté et motivé.",
        ]);
    }

    public function test_struck_off_dossier_cannot_be_changed(): void
    {
        $cga = Cga::factory()->create();
        $adherent = Adherent::factory()->active()->for($cga)->create(['status' => AdherentStatus::StruckOff]);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->patchJson("/api/v1/adherents/{$adherent->id}", ['address' => 'Nouvelle adresse']);

        $response->assertConflict()->assertJsonPath('code', 'adherent_struck_off');
    }

    public function test_secretary_cannot_change_a_dossier(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->for($cga)->create();

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))
            ->patchJson("/api/v1/adherents/{$draft->id}", ['address' => 'Nouvelle adresse']);

        $response->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_history_lists_events_newest_first_with_their_author(): void
    {
        $cga = Cga::factory()->create();
        $token = $this->tokenForRole(Role::Comptable, $cga);
        $adherentId = $this->withToken($token)->postJson('/api/v1/adherents', ['company_name' => 'Garage Moderne'])->json('data.id');
        $this->withToken($token)->patchJson("/api/v1/adherents/{$adherentId}", ['address' => 'Yopougon'])->assertOk();

        $response = $this->withToken($token)->getJson("/api/v1/adherents/{$adherentId}/history");

        $response->assertOk();
        $this->assertSame(['updated', 'created'], $response->json('data.*.event'));
        $this->assertSame('Dossier modifié', $response->json('data.0.label'));
        $this->assertIsString($response->json('data.0.author.name'));
    }
}
