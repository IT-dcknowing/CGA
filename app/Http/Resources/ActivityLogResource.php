<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une ligne du journal d'audit, telle qu'elle s'affiche dans l'historique d'un dossier (Q32).
 *
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    private const LABELS = [
        'created' => 'Dossier créé',
        'updated' => 'Dossier modifié',
        'validated' => 'Dossier validé',
        'regime_changed' => 'Changement de régime',
        'struck_off' => 'Radiation',
        'strike_off_cancelled' => 'Radiation annulée',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'label' => self::LABELS[$this->event] ?? $this->event,
            'author' => $this->whenLoaded('causer', fn (): ?array => $this->causer === null ? null : [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ]),
            'details' => $this->properties,
            'created_at' => $this->created_at,
        ];
    }
}
