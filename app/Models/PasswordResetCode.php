<?php

namespace App\Models;

use Database\Factories\PasswordResetCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Code de réinitialisation du mot de passe envoyé par SMS (Q12). Seule son empreinte est stockée.
 */
#[Hidden(['code_hash'])]
class PasswordResetCode extends Model
{
    /** @use HasFactory<PasswordResetCodeFactory> */
    use HasFactory;

    public const MAX_ATTEMPTS = 5;

    public const VALID_MINUTES = 15;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code_hash' => 'hashed',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }
}
