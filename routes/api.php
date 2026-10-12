<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
| Chaque route authentifiée qui touche aux données d'un espace passe par
| « espace.actif » (et « role » si besoin). Les seules exceptions sont les
| routes de session ci-dessous, utilisables avant le choix de l'espace.
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/switch-space', [AuthController::class, 'switchSpace'])->name('auth.switch-space');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    });
});
