<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureActiveSpace;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RejectMalformedJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        $middleware->api(prepend: RejectMalformedJson::class);

        // L'espace actif doit être fixé avant que Laravel charge les modèles de l'URL ({adherent}…) :
        // sinon le cloisonnement par CGA ne s'appliquerait pas à ce chargement.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureActiveSpace::class);

        $middleware->alias([
            'espace.actif' => EnsureActiveSpace::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiExceptionRenderer);
    })->create();
