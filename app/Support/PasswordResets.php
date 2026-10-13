<?php

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mot de passe oublié (Q12) : un code à 6 chiffres est envoyé par SMS, valable 15 minutes
 * et 5 essais. Rien n'indique à l'appelant si le numéro a un compte.
 */
class PasswordResets
{
    public function request(string $phone): void
    {
        $user = User::firstWhere('phone', $phone);

        if ($user === null || $user->isDisabled()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        (PasswordResetCode::firstWhere('user_id', $user->id) ?? new PasswordResetCode)->forceFill([
            'user_id' => $user->id,
            'code_hash' => $code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(PasswordResetCode::VALID_MINUTES),
        ])->save();

        ActivityLog::record('password_reset_requested', $user);
        $user->notify(new PasswordResetCodeNotification($code));
    }

    /**
     * Change le mot de passe et ferme toutes les sessions ouvertes avec l'ancien.
     */
    public function reset(string $phone, string $code, string $password): User
    {
        $user = User::firstWhere('phone', $phone);
        $resetCode = $user === null ? null : PasswordResetCode::firstWhere('user_id', $user->id);

        if ($user === null || $user->isDisabled() || $resetCode === null || ! $resetCode->isUsable()) {
            throw ApiException::resetCodeInvalid();
        }

        if (! Hash::check($code, $resetCode->code_hash)) {
            $resetCode->increment('attempts');

            throw ApiException::resetCodeInvalid();
        }

        DB::transaction(function () use ($user, $resetCode, $password): void {
            $user->update(['password' => $password]);
            $user->tokens()->delete();
            $resetCode->delete();

            ActivityLog::record('password_reset', $user, causer: $user);
        });

        return $user;
    }
}
