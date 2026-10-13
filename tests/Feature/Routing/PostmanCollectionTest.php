<?php

namespace Tests\Feature\Routing;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La collection Postman doit suivre les routes de l'API : chaque route y a au moins
 * une requête, et aucune requête ne vise une route qui n'existe plus.
 */
class PostmanCollectionTest extends TestCase
{
    private const COLLECTION = 'postman/CGA-Connect.postman_collection.json';

    public function test_every_api_route_has_a_request_in_the_postman_collection(): void
    {
        $requests = $this->collectionRequests();

        $routesWithoutRequest = collect($this->apiRoutes())
            ->reject(fn (array $route): bool => collect($requests)->contains(
                fn (array $request): bool => $request['method'] === $route['method'] && preg_match($route['pattern'], $request['path']) === 1,
            ))
            ->map(fn (array $route): string => "{$route['method']} {$route['uri']}")
            ->values()
            ->all();

        $this->assertSame([], $routesWithoutRequest, 'Ajoutez ces routes à '.self::COLLECTION.' (voir CLAUDE.md).');
    }

    public function test_every_request_of_the_postman_collection_targets_an_existing_route(): void
    {
        $routes = $this->apiRoutes();

        $requestsWithoutRoute = collect($this->collectionRequests())
            ->reject(fn (array $request): bool => collect($routes)->contains(
                fn (array $route): bool => $route['method'] === $request['method'] && preg_match($route['pattern'], $request['path']) === 1,
            ))
            ->map(fn (array $request): string => "{$request['name']} ({$request['method']} {$request['path']})")
            ->values()
            ->all();

        $this->assertSame([], $requestsWithoutRoute, 'Ces requêtes de '.self::COLLECTION.' visent une route qui n\'existe plus.');
    }

    /**
     * Routes de /api/v1, chacune avec une expression qui accepte n'importe quelle valeur
     * à la place de ses paramètres ({cga}, {key}…).
     *
     * @return list<array{method: string, uri: string, pattern: string}>
     */
    private function apiRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => Str::startsWith($route->uri(), 'api/v1/'))
            ->flatMap(function (RoutingRoute $route): array {
                $uri = Str::after($route->uri(), 'api/v1/');
                $pattern = collect(explode('/', $uri))
                    ->map(fn (string $segment): string => Str::startsWith($segment, '{') ? '[^/]+' : preg_quote($segment, '#'))
                    ->implode('/');

                return collect($route->methods())
                    ->reject(fn (string $method): bool => $method === 'HEAD')
                    ->map(fn (string $method): array => ['method' => $method, 'uri' => $uri, 'pattern' => "#^{$pattern}$#"])
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, method: string, path: string}>
     */
    private function collectionRequests(): array
    {
        $collection = json_decode((string) file_get_contents(base_path(self::COLLECTION)), true, flags: JSON_THROW_ON_ERROR);

        return $this->flattenItems($collection['item']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{name: string, method: string, path: string}>
     */
    private function flattenItems(array $items): array
    {
        return collect($items)
            ->flatMap(fn (array $item): array => isset($item['item'])
                ? $this->flattenItems($item['item'])
                : [[
                    'name' => $item['name'],
                    'method' => $item['request']['method'],
                    'path' => implode('/', $item['request']['url']['path']),
                ]])
            ->values()
            ->all();
    }
}
