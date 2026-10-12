<?php

namespace App\Http\Resources;

use App\Models\StrikeOffReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StrikeOffReason
 */
class StrikeOffReasonResource extends JsonResource
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
            'requires_document' => $this->requires_document,
            'requires_details' => $this->requires_details,
        ];
    }
}
