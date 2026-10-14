<?php

namespace Tests\Feature\Routing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionParameter;
use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    /**
     * Routes de session utilisables avant le choix d'un espace.
     */
    private const SESSION_ROUTES = ['v1.me', 'v1.auth.switch-space', 'v1.auth.logout'];

    public function test_every_authenticated_route_requires_an_active_space(): void
    {
        $routesWithoutSpace = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true))
            ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), self::SESSION_ROUTES, true))
            ->reject(fn (RoutingRoute $route): bool => collect($route->gatherMiddleware())
                ->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::is(['espace.actif', 'espace.actif:*'], $middleware)))
            ->map(fn (RoutingRoute $route): string => $route->methods()[0].' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $routesWithoutSpace);
    }

    /**
     * PostgreSQL refuse de comparer un identifiant à du texte : sans cette contrainte,
     * /adherents/abc répondrait 500 au lieu de 404.
     */
    public function test_every_model_identifier_in_a_url_is_numeric(): void
    {
        $unconstrained = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn (RoutingRoute $route): array => collect($route->signatureParameters(['subClass' => Model::class]))
                ->map(fn (ReflectionParameter $parameter): string => $parameter->getName())
                ->reject(fn (string $name): bool => ($route->wheres[$name] ?? null) === '[0-9]+')
                ->map(fn (string $name): string => $route->methods()[0].' '.$route->uri()." ({$name})")
                ->all())
            ->values()
            ->all();

        $this->assertSame([], $unconstrained);
    }
}
