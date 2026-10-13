<?php

namespace Tests\Feature\Http\Controllers\Api\Adherent;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\DeadlineRecord;
use App\Models\Membership;
use App\Models\Obligation;
use App\Models\User;
use App\Support\Deadlines;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ObligationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->travelTo('2026-07-01 09:00:00');
    }

    public function test_adherent_sees_only_the_obligations_confirmed_by_the_cga(): void
    {
        [$bakery, $token] = $this->adherentSpace();
        $bakery->adherentObligations()->whereHas('obligation', fn ($query) => $query->where('code', 'metro'))
            ->update(['status' => AssignmentStatus::Dismissed->value, 'dismissal_reason' => 'Pas de balance']);

        $response = $this->withToken($token)->getJson('/api/v1/adherent/obligations');

        $response->assertOk()->assertJsonCount(32, 'data');
        $this->assertNotContains('metro', $response->json('data.*.obligation.code'));
    }

    public function test_adherent_sees_the_deadlines_of_their_company(): void
    {
        [, $token] = $this->adherentSpace();

        $response = $this->withToken($token)->getJson('/api/v1/adherent/deadlines');

        $response->assertOk()->assertJsonCount(11, 'data')->assertJsonPath('meta.months', 3);
    }

    public function test_adherent_reports_a_payment_that_stays_to_be_confirmed(): void
    {
        [$bakery, $token] = $this->adherentSpace();
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($token)->putJson("/api/v1/adherent/deadlines/{$micro->id}/2026-07-10", ['report' => 'paid', 'amount' => 50000]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'to_do')
            ->assertJsonPath('data.adherent_report.type', 'paid')
            ->assertJsonPath('data.adherent_report.label', "Payée selon l'adhérent — à confirmer")
            ->assertJsonPath('data.adherent_report.amount', 50000);
        $this->assertDatabaseHas('deadline_records', ['adherent_id' => $bakery->id, 'adherent_report' => 'paid', 'status' => 'to_do']);
    }

    public function test_payment_report_needs_its_amount(): void
    {
        [, $token] = $this->adherentSpace();
        $micro = Obligation::firstWhere('code', 'micro');

        $response = $this->withToken($token)->putJson("/api/v1/adherent/deadlines/{$micro->id}/2026-07-10", ['report' => 'paid']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['amount' => 'Indiquez le montant payé.']);
    }

    public function test_settled_deadline_can_no_longer_be_reported(): void
    {
        [$bakery, $token] = $this->adherentSpace();
        $micro = Obligation::firstWhere('code', 'micro');
        app(Deadlines::class)->record($bakery, $micro, CarbonImmutable::parse('2026-07-10'), true, 50000, 50000, User::factory()->create());

        $response = $this->withToken($token)->putJson("/api/v1/adherent/deadlines/{$micro->id}/2026-07-10", ['report' => 'declared']);

        $response->assertConflict()->assertJsonPath('code', 'deadline_already_settled');
    }

    public function test_adherent_cannot_report_on_an_obligation_that_is_not_theirs(): void
    {
        [, $token] = $this->adherentSpace();
        $tva = Obligation::firstWhere('code', 'tva');

        $response = $this->withToken($token)->putJson("/api/v1/adherent/deadlines/{$tva->id}/2026-07-10", ['report' => 'declared']);

        $response->assertNotFound();
        $this->assertSame(0, DeadlineRecord::count());
    }

    public function test_cga_agent_cannot_use_the_adherent_routes(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/adherent/deadlines');

        $response->assertForbidden()->assertJsonPath('code', 'space_forbidden');
    }

    /**
     * @return array{Adherent, string}
     */
    private function adherentSpace(): array
    {
        $cga = Cga::factory()->create();
        $bakery = $this->bakeryWithObligations($cga);
        $membership = Membership::factory()->for($cga)->role(Role::Adherent)->create(['adherent_id' => $bakery->id]);

        return [$bakery, $this->createTokenFor($membership->user, $membership)->plainTextToken];
    }
}
