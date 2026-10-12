<?php

namespace Tests\Feature\Routing;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
}
