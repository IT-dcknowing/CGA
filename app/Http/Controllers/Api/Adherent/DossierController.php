<?php

namespace App\Http\Controllers\Api\Adherent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdherentResource;
use App\Models\Adherent;
use App\Support\ActiveSpace;

/**
 * Espace adhérent : la personne consulte le dossier de son entreprise. Elle ne le modifie
 * pas directement ; elle signale les changements à son CGA (Q32).
 */
class DossierController extends Controller
{
    public function show(ActiveSpace $activeSpace): AdherentResource
    {
        $adherent = Adherent::query()
            ->with(['regime', 'sector', 'taxCenter', 'latestRegimeChange', 'activeStrikeOff.reason'])
            ->findOrFail($activeSpace->membership()->adherent_id);

        return new AdherentResource($adherent);
    }
}
