<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Cga\BrandingController;
use App\Http\Controllers\Api\Cga\ProfileController;
use App\Http\Controllers\Api\Cncga\CgaController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
| Chaque route authentifiée qui touche aux données d'un espace passe par
| « espace.actif » (et « role » si besoin). Les seules exceptions sont les
| routes de session, utilisables avant le choix de l'espace.
*/

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    // Invitation et mot de passe oublié par SMS (Q12), sans jeton.
    Route::post('auth/accept-invitation', [InvitationController::class, 'accept'])
        ->middleware('throttle:accept-invitation')
        ->name('auth.accept-invitation');
    Route::post('auth/forgot-password', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:sms-code')
        ->name('auth.forgot-password');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:login')
        ->name('auth.reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/switch-space', [AuthController::class, 'switchSpace'])->name('auth.switch-space');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Étape 2 : référentiels et paramètres.
        Route::middleware('espace.actif')->prefix('ref')->name('ref.')->group(function (): void {
            Route::get('regimes', [ReferenceController::class, 'regimes'])->name('regimes');
            Route::get('sectors', [ReferenceController::class, 'sectors'])->name('sectors');
            Route::get('tax-centers', [ReferenceController::class, 'taxCenters'])->name('tax-centers');
        });

        Route::middleware('espace.actif:cncga,cga')->group(function (): void {
            Route::get('ref/strike-off-reasons', [ReferenceController::class, 'strikeOffReasons'])->name('ref.strike-off-reasons');
            Route::get('ref/obligations', [ReferenceController::class, 'obligations'])->name('ref.obligations');

            Route::get('settings', [SettingController::class, 'index'])
                ->middleware('role:presidente,agent_cncga,membre_bureau,gerant')
                ->name('settings.index');
            Route::put('settings/{key}', [SettingController::class, 'update'])
                ->middleware('role:presidente,gerant')
                ->name('settings.update');

            // Étape 1 : invitations (Q11, Q12).
            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('role:presidente,agent_cncga,gerant')
                ->name('invitations.store');
        });

        // Étape 3 : registre des CGA, tenu par le CNCGA.
        Route::middleware('espace.actif:cncga')->prefix('cncga')->name('cncga.')->group(function (): void {
            Route::middleware('role:presidente,agent_cncga')->group(function (): void {
                Route::get('cgas', [CgaController::class, 'index'])->name('cgas.index');
                Route::post('cgas', [CgaController::class, 'store'])->name('cgas.store');
                Route::get('cgas/{cga}', [CgaController::class, 'show'])->name('cgas.show');
                Route::patch('cgas/{cga}', [CgaController::class, 'update'])->name('cgas.update');
            });

            Route::middleware('role:presidente')->group(function (): void {
                Route::post('cgas/{cga}/suspend', [CgaController::class, 'suspend'])->name('cgas.suspend');
                Route::post('cgas/{cga}/reactivate', [CgaController::class, 'reactivate'])->name('cgas.reactivate');
            });
        });

        // Étape 3 : fiche du CGA de l'espace actif.
        Route::middleware('espace.actif:cga')->prefix('cga')->name('cga.')->group(function (): void {
            Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('profile', [ProfileController::class, 'update'])
                ->middleware('role:gerant')
                ->name('profile.update');
            Route::get('profile/branding/{asset}', [BrandingController::class, 'show'])->name('profile.branding.show');
            Route::post('profile/branding', [BrandingController::class, 'store'])
                ->middleware('role:gerant')
                ->name('profile.branding.store');
        });
    });
});
