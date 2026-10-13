<?php

namespace App\Models;

use App\Enums\AdherentReport;
use App\Enums\DeadlineStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qui a été enregistré sur une échéance : déclaration, montants, signalement de l'adhérent.
 * Rempli par App\Support\Deadlines.
 */
class DeadlineRecord extends Model
{
    use Auditable, BelongsToCga;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => DeadlineStatus::class,
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'declared_at' => 'datetime',
            'paid_at' => 'datetime',
            'adherent_report' => AdherentReport::class,
            'adherent_report_amount' => 'integer',
            'adherent_reported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Obligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }
}
