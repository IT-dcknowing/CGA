<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\Space;
use App\Models\Concerns\Auditable;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Un rôle d'une personne dans un espace : une même personne peut en avoir plusieurs (Q13).
 */
#[Fillable(['role', 'is_primary'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'space' => Space::class,
            'role' => Role::class,
            'status' => MembershipStatus::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * L'espace découle toujours du rôle, et seuls les accès CNCGA sont sans CGA.
     */
    protected static function booted(): void
    {
        static::saving(function (Membership $membership): void {
            $membership->space = $membership->role->space();

            if ($membership->space->requiresCga() !== ($membership->cga_id !== null)) {
                throw new LogicException(
                    "Le rôle « {$membership->role->value} » ".($membership->space->requiresCga() ? 'exige' : 'interdit').' un CGA.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Cga, $this>
     */
    public function cga(): BelongsTo
    {
        return $this->belongsTo(Cga::class);
    }

    /**
     * @param  Builder<Membership>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }
}
