<?php

namespace App\Http\Controllers\Api\Cga;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cga\UpdateProfileRequest;
use App\Http\Resources\CgaProfileResource;
use App\Support\ActiveSpace;

/**
 * Fiche du CGA de l'espace actif, consultée par ses agents et complétée par le gérant (Q17).
 */
class ProfileController extends Controller
{
    public function show(ActiveSpace $activeSpace): CgaProfileResource
    {
        return new CgaProfileResource($activeSpace->cga()->load(['taxRegion', 'taxCenter']));
    }

    public function update(UpdateProfileRequest $request, ActiveSpace $activeSpace): CgaProfileResource
    {
        $cga = $activeSpace->cga();
        $cga->update($request->validated());

        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }
}
