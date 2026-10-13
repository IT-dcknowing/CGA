<?php

namespace Tests\Feature\Http\Controllers\Api\Adherent;

use App\Enums\Role;
use App\Models\Membership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DossierControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_adherent_sees_the_dossier_of_their_company(): void
    {
        $membership = Membership::factory()->role(Role::Adherent)->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/adherent/dossier');

        $response->assertOk()
            ->assertJsonPath('data.id', $membership->adherent_id)
            ->assertJsonPath('data.member_no', $membership->adherent->member_no);
    }

    public function test_cga_agent_gets_403(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->getJson('/api/v1/adherent/dossier');

        $response->assertForbidden()->assertJsonPath('code', 'space_forbidden');
    }
}
