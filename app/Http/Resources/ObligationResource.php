<?php

namespace App\Http\Resources;

use App\Models\Obligation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Obligation
 */
class ObligationResource extends JsonResource
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
            'administration' => [
                'code' => $this->administration_code,
                'name' => $this->administration_name,
            ],
            'label' => $this->label,
            'legal_basis' => $this->legal_basis,
            'periodicity' => $this->periodicity,
            'due_day' => $this->due_day,
            'due_date' => $this->due_date,
            'platform' => $this->platform,
            'applicability' => $this->applicability,
            'periodicity_rule' => $this->periodicity_rule,
        ];
    }
}
