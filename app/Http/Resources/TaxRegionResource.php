<?php

namespace App\Http\Resources;

use App\Models\TaxRegion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaxRegion
 */
class TaxRegionResource extends JsonResource
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
            'code' => $this->code,
            'label' => $this->label,
            'city' => $this->city,
            'centers' => TaxCenterResource::collection($this->whenLoaded('centers')),
        ];
    }
}
