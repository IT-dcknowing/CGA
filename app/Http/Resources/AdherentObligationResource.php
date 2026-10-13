<?php

namespace App\Http\Resources;

use App\Models\AdherentObligation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une obligation attribuée à un adhérent. « applicable » indique si le profil actuel la
 * déclenche encore : une obligation confirmée qui ne l'est plus est à revoir par le CGA.
 *
 * @mixin AdherentObligation
 */
class AdherentObligationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'source' => $this->source,
            'dismissal_reason' => $this->dismissal_reason,
            'decided_at' => $this->decided_at,
            'applicable' => $this->getAttribute('applicable'),
            'periodicity' => $this->getAttribute('current_periodicity'),
            'obligation' => new ObligationResource($this->whenLoaded('obligation')),
        ];
    }
}
