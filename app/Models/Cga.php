<?php

namespace App\Models;

use App\Enums\CgaStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\CgaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
])]
class Cga extends Model
{
    /** @use HasFactory<CgaFactory> */
    use Auditable, HasFactory;

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
        ];
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
