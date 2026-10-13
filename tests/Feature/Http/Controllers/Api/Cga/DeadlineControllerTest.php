<?php

namespace Tests\Feature\Http\Controllers\Api\Cga;

use App\Enums\AdherentReport;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Cga;
use App\Models\DeadlineRecord;
use App\Models\Obligation;
use App\Models\User;
use App\Support\Deadlines;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeadlineControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-07-01 09:00:00');
    }

    public function test_lists_three_months_of_deadlines_from_confirmed_obligations(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))->getJson("/api/v1/adherents/{$bakery->id}/deadlines");

        $response->assertOk()
            ->assertJsonPath('meta.months', 3)
            ->assertJsonCount(11, 'data')
            ->assertJsonPath('data.0.due_date', '2026-07-10')
            ->assertJsonPath('data.0.status', 'to_do')
            ->assertJsonPath('data.0.days_left', 9);
        $this->assertSame(
            ['cmu_cot' => 3, 'cnps_cot' => 1, 'its' => 3, 'micro' => 3, 'vismed' => 1],
            collect($response->json('data'))->countBy('obligation.code')->sortKeys()->all(),
        );
    }

    public function test_months_parameter_widens_the_window(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->getJson("/api/v1/adherents/{$bakery->id}/deadlines?months=4");

        $response->assertOk()->assertJsonPath('meta.months', 4)->assertJsonCount(16, 'data');
    }

    public function test_cga_records_a_declaration_with_a_partial_payment(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", [
            'declared' => true,
            'amount_due' => 50000,
            'amount_paid' => 20000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.status_label', 'Partiellement payée')
            ->assertJsonPath('data.balance', 30000);
        $this->assertDatabaseHas('deadline_records', ['adherent_id' => $bakery->id, 'obligation_id' => $micro->id, 'amount_due' => 50000]);
    }

    public function test_full_payment_settles_the_deadline(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", [
            'declared' => true,
            'amount_due' => 50000,
            'amount_paid' => 50000,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.balance', 0);
    }

    public function test_past_deadline_not_declared_is_overdue_and_a_nil_declaration_settles_it(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');
        $this->travelTo('2026-07-12 09:00:00');
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $before = collect($this->withToken($token)->getJson("/api/v1/adherents/{$bakery->id}/deadlines")->json('data'))->firstWhere('key', "{$micro->id}|2026-07-10");
        $response = $this->withToken($token)->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", ['declared' => true, 'amount_due' => 0]);

        $this->assertTrue($before['overdue']);
        $response->assertOk()->assertJsonPath('data.status', 'declared')->assertJsonPath('data.overdue', false);
    }

    public function test_cga_decision_replaces_what_the_adherent_reported(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');
        app(Deadlines::class)->report($bakery, $micro, CarbonImmutable::parse('2026-07-10'), AdherentReport::Paid, 50000, User::factory()->create());

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", [
            'declared' => true,
            'amount_due' => 50000,
            'amount_paid' => 50000,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.adherent_report', null);
    }

    public function test_date_that_is_not_an_occurrence_returns_404(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-11", ['declared' => true]);

        $response->assertNotFound()->assertJsonPath('code', 'deadline_not_found');
        $this->assertSame(0, DeadlineRecord::count());
    }

    public function test_obligation_that_is_not_confirmed_has_no_deadline(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $tva = Obligation::firstWhere('code', 'tva');

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))
            ->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$tva->id}/2026-07-10", ['declared' => true]);

        $response->assertNotFound()->assertJsonPath('code', 'deadline_not_found');
    }

    public function test_dismissed_obligation_has_no_deadline(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');
        $bakery->adherentObligations()->where('obligation_id', $micro->id)
            ->update(['status' => AssignmentStatus::Dismissed->value, 'dismissal_reason' => 'Sans objet']);
        $token = $this->tokenForRole(Role::Gerant, $cga);

        $listed = $this->withToken($token)->getJson("/api/v1/adherents/{$bakery->id}/deadlines")->json('data.*.obligation.code');
        $response = $this->withToken($token)->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", ['declared' => true]);

        $this->assertNotContains('micro', $listed);
        $response->assertNotFound()->assertJsonPath('code', 'deadline_not_found');
    }

    public function test_secretary_cannot_record_a_deadline(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($this->tokenForRole(Role::Secretaire, $cga))
            ->putJson("/api/v1/adherents/{$bakery->id}/deadlines/{$micro->id}/2026-07-10", ['declared' => true]);

        $response->assertForbidden();
    }

    public function test_portfolio_lists_the_deadlines_of_every_active_adherent_and_filters_pending_reports(): void
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga, ['company_name' => 'Boulangerie A']);
        $this->bakeryWithObligations($cga, ['company_name' => 'Boulangerie B']);
        $this->bakeryWithObligations(Cga::factory()->create(), ['company_name' => 'Autre CGA']);
        $micro = Obligation::firstWhere('code', 'micro');
        app(Deadlines::class)->report($bakery, $micro, CarbonImmutable::parse('2026-07-10'), AdherentReport::Declared, null, User::factory()->create());
        $token = $this->tokenForRole(Role::Comptable, $cga);

        $all = $this->withToken($token)->getJson('/api/v1/deadlines');
        $pending = $this->withToken($token)->getJson('/api/v1/deadlines?status=pending_confirmation');

        $all->assertOk()->assertJsonPath('meta.total', 22);
        $this->assertSame(['Boulangerie A', 'Boulangerie B'], collect($all->json('data'))->pluck('adherent.company_name')->unique()->sort()->values()->all());
        $pending->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.adherent_report.type', 'declared');
    }
}
