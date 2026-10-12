<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\ActiveSpace;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveSpace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && ! ($token->tokenable instanceof User && $token->tokenable->isDisabled()),
        );

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            PhoneNumber::normalize($request->string('phone')->toString()).'|'.$request->ip()
        ));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip()
        ));
    }
}
