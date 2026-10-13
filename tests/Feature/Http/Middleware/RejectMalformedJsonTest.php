<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\Role;
use App\Models\Adherent;
use App\Models\Cga;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RejectMalformedJsonTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_400_for_a_malformed_json_body(): void
    {
        $response = $this->call('POST', '/api/v1/auth/login', server: $this->jsonHeaders(), content: '{"phone": , "password": "password"}');

        $response->assertBadRequest()->assertExactJson([
            'message' => "Le corps de la requête n'est pas un JSON valide : vérifiez les virgules, les guillemets et les variables vides.",
            'code' => 'invalid_json',
        ]);
    }

    public function test_malformed_body_changes_nothing_instead_of_being_ignored(): void
    {
        $cga = Cga::factory()->create();
        $draft = Adherent::factory()->for($cga)->create(['address' => 'Adresse intacte']);
        $token = $this->tokenForRole(Role::Comptable, $cga);

        $response = $this->call('PATCH', "/api/v1/adherents/{$draft->id}", server: [
            ...$this->jsonHeaders(),
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: '{"regime_id": , "address": "Nouvelle adresse"}');

        $response->assertBadRequest();
        $this->assertSame('Adresse intacte', $draft->fresh()->address);
    }

    public function test_empty_json_body_is_accepted(): void
    {
        $response = $this->call('POST', '/api/v1/auth/login', server: $this->jsonHeaders(), content: '');

        $response->assertUnprocessable();
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    }
}
