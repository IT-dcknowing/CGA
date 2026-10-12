<?php

namespace App\Http\Resources;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MembershipResource extends JsonResource
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
            'space' => $this->space,
            'space_label' => $this->space->label(),
            'role' => $this->role,
            'role_label' => $this->role->label(),
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'cga' => new CgaResource($this->whenLoaded('cga')),
            'adherent_id' => $this->adherent_id,
        ];
    }
}
