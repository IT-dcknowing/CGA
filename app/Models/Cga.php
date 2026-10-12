<?php

namespace App\Models;

use App\Enums\CgaStatus;
use App\Models\Concerns\Auditable;
use App\Support\PhoneNumber;
use Database\Factories\CgaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche d'un CGA du registre (Q17). Le statut, la suspension et les fichiers d'identité
 * visuelle ne sont jamais remplis par assignation de masse.
 */
#[Fillable([
    'name',
    'abbreviation',
    'ncc',
    'rccm',
    'legal_form',
    'capital',
    'approval_no',
    'approval_date',
    'approval_expires_at',
    'city',
    'address',
    'phone',
    'email',
    'manager_name',
    'accountant_name',
    'tax_region_id',
    'tax_center_id',
    'is_subscribed',
    'next_member_seq',
])]
class Cga extends Model
{
    /** @use HasFactory<CgaFactory> */
    use Auditable, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'is_subscribed' => false,
        'next_member_seq' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CgaStatus::class,
            'capital' => 'integer',
            'approval_date' => 'date',
            'approval_expires_at' => 'date',
            'is_subscribed' => 'boolean',
            'next_member_seq' => 'integer',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Un CGA en cours d'agrément devient actif dès que son agrément est enregistré (Q18).
     */
    protected static function booted(): void
    {
        static::saving(function (Cga $cga): void {
            if ($cga->status === CgaStatus::Pending && $cga->hasApproval()) {
                $cga->status = CgaStatus::Active;
            }
        });
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : PhoneNumber::normalize($value));
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsTo<TaxRegion, $this>
     */
    public function taxRegion(): BelongsTo
    {
        return $this->belongsTo(TaxRegion::class);
    }

    /**
     * @return BelongsTo<TaxCenter, $this>
     */
    public function taxCenter(): BelongsTo
    {
        return $this->belongsTo(TaxCenter::class);
    }

    public function hasApproval(): bool
    {
        return $this->approval_no !== null && $this->approval_date !== null;
    }

    public function isSuspended(): bool
    {
        return $this->status === CgaStatus::Suspended;
    }

    /**
     * Suspension décidée par la Présidente (Q19) : le CGA ne peut plus que consulter.
     */
    public function suspend(string $reason): void
    {
        $this->forceFill([
            'status' => CgaStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill([
            'status' => $this->hasApproval() ? CgaStatus::Active : CgaStatus::Pending,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();
    }
}
