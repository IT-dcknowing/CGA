<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\ActiveSpace;
use App\Support\PhoneNumber;
use App\Support\Sms\LogSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveSpace::class);

        // Pour brancher un fournisseur : ajouter son pilote ici et le choisir avec SMS_DRIVER.
        $this->app->singleton(SmsSender::class, fn (): SmsSender => match (config('services.sms.driver')) {
            'log' => new LogSmsSender(config('services.sms.log_channel')),
            default => throw new InvalidArgumentException('Pilote SMS inconnu : '.config('services.sms.driver')),
        });
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
            $this->phoneOf($request).'|'.$request->ip()
        ));

        // Chaque demande de code coûte un SMS : 3 par quart d'heure pour un numéro, 30 par heure pour une adresse IP.
        RateLimiter::for('sms-code', fn (Request $request): array => [
            Limit::perMinutes(15, 3)->by('phone:'.$this->phoneOf($request)),
            Limit::perHour(30)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('accept-invitation', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip()
        ));
    }

    private function phoneOf(Request $request): string
    {
        return PhoneNumber::normalize($request->string('phone')->toString());
    }
}
