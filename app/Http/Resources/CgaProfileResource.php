<?php

namespace App\Http\Resources;

use App\Enums\BrandingAsset;
use App\Models\Cga;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche complète d'un CGA (Q17), pour le registre du CNCGA et pour le CGA lui-même.
 *
 * @mixin Cga
 */
class CgaProfileResource extends JsonResource
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
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'status' => $this->status,
            'is_subscribed' => $this->is_subscribed,
            'ncc' => $this->ncc,
            'rccm' => $this->rccm,
            'legal_form' => $this->legal_form,
            'capital' => $this->capital,
            'approval_no' => $this->approval_no,
            'approval_date' => $this->approval_date?->toDateString(),
            'approval_expires_at' => $this->approval_expires_at?->toDateString(),
            'city' => $this->city,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'manager_name' => $this->manager_name,
            'accountant_name' => $this->accountant_name,
            'tax_region' => new TaxRegionResource($this->whenLoaded('taxRegion')),
            'tax_center' => new TaxCenterResource($this->whenLoaded('taxCenter')),
            'next_member_seq' => $this->next_member_seq,
            'suspension' => $this->isSuspended() ? [
                'since' => $this->suspended_at,
                'reason' => $this->suspension_reason,
            ] : null,
            'branding' => collect(BrandingAsset::cases())
                ->mapWithKeys(fn (BrandingAsset $asset): array => [$asset->value => $this->{$asset->column()} !== null]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
