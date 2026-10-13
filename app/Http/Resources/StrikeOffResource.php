<?php

namespace App\Http\Resources;

use App\Enums\SettingKey;
use App\Models\StrikeOff;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StrikeOff
 */
class StrikeOffResource extends JsonResource
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
            'reason' => new StrikeOffReasonResource($this->whenLoaded('reason')),
            'details' => $this->details,
            'observation' => $this->observation,
            'effective_date' => $this->effective_date->toDateString(),
            'has_document' => $this->document_path !== null,
            'recorded_at' => $this->created_at,
            'cancellable_until' => $this->created_at
                ->copy()
                ->addDays(app(Settings::class)->get(SettingKey::StrikeOffCancellationDays))
                ->toDateString(),
        ];
    }
}
