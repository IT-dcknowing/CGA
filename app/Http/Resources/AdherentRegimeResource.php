<?php

namespace App\Http\Resources;

use App\Models\AdherentRegime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdherentRegime
 */
class AdherentRegimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'regime' => new RegimeResource($this->whenLoaded('regime')),
            'effective_date' => $this->effective_date->toDateString(),
            'reason' => $this->reason,
        ];
    }
}
