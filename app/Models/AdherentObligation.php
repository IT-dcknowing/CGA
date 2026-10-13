<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une obligation du référentiel attribuée à un adhérent (Q45). Le statut, la source et la
 * décision sont posés par App\Support\AdherentObligations, jamais par assignation de masse.
 */
class AdherentObligation extends Model
{
    use Auditable, BelongsToCga;

    public const SOURCE_ENGINE = 'engine';

    public const SOURCE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Obligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /**
     * @return BelongsTo<Adherent, $this>
     */
    public function adherent(): BelongsTo
    {
        return $this->belongsTo(Adherent::class);
    }
}
