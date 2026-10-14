<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Accueil de l'API : indique où elle se trouve, sans page HTML ni version du framework.
// Sans le groupe « web », une visite n'ouvre pas de session en base.
Route::get('/', fn (): JsonResponse => response()->json([
    'name' => 'CGA Connect API',
    'api' => url('/api/v1'),
]))->withoutMiddleware('web');
