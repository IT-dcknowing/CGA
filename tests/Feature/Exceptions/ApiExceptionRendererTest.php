<?php

namespace Tests\Feature\Exceptions;

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    public function test_returns_404_with_code_for_unknown_api_route(): void
    {
        $response = $this->getJson('/api/v1/route-inconnue');

        $response->assertNotFound()
            ->assertExactJson(['message' => 'Ressource introuvable.', 'code' => 'not_found']);
    }

    public function test_returns_405_with_code_for_wrong_http_method(): void
    {
        $response = $this->getJson('/api/v1/auth/login');

        $response->assertMethodNotAllowed()
            ->assertExactJson(['message' => 'Méthode non autorisée pour cette route.', 'code' => 'method_not_allowed']);
    }

    public function test_hides_internal_error_details_outside_debug_mode(): void
    {
        Exceptions::fake();
        config(['app.debug' => false]);
        Route::get('/api/test/failure', fn () => throw new RuntimeException('Détail interne'));

        $response = $this->getJson('/api/test/failure');

        $response->assertInternalServerError()
            ->assertExactJson(['message' => 'Une erreur interne est survenue.', 'code' => 'server_error']);
        Exceptions::assertReported(RuntimeException::class);
    }
}
