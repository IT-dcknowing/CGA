<?php

namespace App\Http\Controllers\Api\Cga;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adherents\CancelStrikeOffRequest;
use App\Http\Requests\Adherents\ChangeRegimeRequest;
use App\Http\Requests\Adherents\StrikeOffRequest;
use App\Http\Requests\Adherents\ValidateAdherentRequest;
use App\Http\Resources\AdherentResource;
use App\Models\Adherent;
use App\Models\Regime;
use App\Models\StrikeOffReason;
use App\Support\Adherents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Étapes de la vie d'un dossier : validation et numéro, régime, radiation et son annulation.
 */
class AdherentLifecycleController extends Controller
{
    private const RELATIONS = ['regime', 'sector', 'taxCenter', 'latestRegimeChange', 'activeStrikeOff.reason', 'regimeHistory.regime'];

    public function __construct(private Adherents $adherents) {}

    /**
     * Valide un dossier complet et lui attribue son numéro (Q26, Q27).
     */
    public function validateDossier(ValidateAdherentRequest $request, Adherent $adherent): AdherentResource
    {
        $this->adherents->validate($adherent, $request->user(), $request->validated('historical_member_no'));

        return new AdherentResource($adherent->load(self::RELATIONS));
    }

    public function changeRegime(ChangeRegimeRequest $request, Adherent $adherent): AdherentResource
    {
        $this->adherents->changeRegime(
            $adherent,
            Regime::findOrFail($request->validated('regime_id')),
            Carbon::parse($request->validated('effective_date')),
            $request->validated('reason'),
            $request->user(),
        );

        return new AdherentResource($adherent->load(self::RELATIONS));
    }

    public function strikeOff(StrikeOffRequest $request, Adherent $adherent): AdherentResource
    {
        $this->adherents->strikeOff(
            $adherent,
            StrikeOffReason::findOrFail($request->validated('reason_id')),
            $request->safe()->only(['details', 'observation', 'effective_date']),
            $request->file('document'),
            $request->user(),
        );

        return new AdherentResource($adherent->load(self::RELATIONS));
    }

    public function cancelStrikeOff(CancelStrikeOffRequest $request, Adherent $adherent): AdherentResource
    {
        $this->adherents->cancelStrikeOff($adherent, $request->validated('reason'), $request->user());

        return new AdherentResource($adherent->unsetRelation('activeStrikeOff')->load(self::RELATIONS));
    }

    /**
     * Pièce justificative de la radiation en vigueur (procès-verbal…).
     */
    public function strikeOffDocument(Adherent $adherent): StreamedResponse
    {
        $path = $adherent->activeStrikeOff?->document_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
