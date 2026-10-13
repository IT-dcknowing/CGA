<?php

namespace App\Http\Controllers\Api\Cga;

use App\Enums\AdherentStatus;
use App\Enums\AssignmentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Obligations\AddObligationRequest;
use App\Http\Requests\Obligations\DecideObligationRequest;
use App\Http\Resources\AdherentObligationResource;
use App\Models\Adherent;
use App\Models\AdherentObligation;
use App\Models\Obligation;
use App\Support\AdherentObligations;
use App\Support\ObligationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Obligations d'un adhérent (Q45) : propositions du moteur, décisions du CGA.
 */
class AdherentObligationController extends Controller
{
    public function __construct(private ObligationEngine $engine, private AdherentObligations $obligations) {}

    public function index(Adherent $adherent): AnonymousResourceCollection
    {
        return $this->listing($adherent);
    }

    /**
     * Relance le moteur, par exemple après un changement du référentiel.
     */
    public function propose(Adherent $adherent): AnonymousResourceCollection
    {
        $this->ensureNotStruckOff($adherent);
        $created = $this->obligations->propose($adherent);

        return $this->listing($adherent, ['created' => $created]);
    }

    public function confirmProposals(Request $request, Adherent $adherent): AnonymousResourceCollection
    {
        $this->ensureNotStruckOff($adherent);
        $confirmed = $this->obligations->confirmProposals($adherent, $request->user());

        return $this->listing($adherent, ['confirmed_now' => $confirmed]);
    }

    /**
     * Ajoute une obligation que le moteur n'a pas proposée (Q45 : « le CGA ajuste au cas par cas »).
     */
    public function store(AddObligationRequest $request, Adherent $adherent): JsonResponse
    {
        $this->ensureNotStruckOff($adherent);
        $assignment = $this->obligations->add($adherent, Obligation::findOrFail($request->validated('obligation_id')), $request->user());

        return $this->present($adherent, $assignment)->response()->setStatusCode(201);
    }

    public function update(DecideObligationRequest $request, Adherent $adherent, AdherentObligation $adherentObligation): AdherentObligationResource
    {
        $this->ensureNotStruckOff($adherent);
        $this->obligations->decide(
            $adherentObligation,
            AssignmentStatus::from($request->validated('status')),
            $request->validated('reason'),
            $request->user(),
        );

        return $this->present($adherent, $adherentObligation);
    }

    /**
     * @param  array<string, int>  $extraMeta
     */
    private function listing(Adherent $adherent, array $extraMeta = []): AnonymousResourceCollection
    {
        $profile = $this->engine->profile($adherent->loadMissing('regime'));

        $assignments = $adherent->adherentObligations()
            ->with('obligation')
            ->get()
            ->sortBy('obligation.sort_order')
            ->values()
            ->each(fn (AdherentObligation $assignment) => $this->describe($assignment, $profile));

        return AdherentObligationResource::collection($assignments)->additional(['meta' => [
            'proposed' => $assignments->where('status', AssignmentStatus::Proposed)->count(),
            'confirmed' => $assignments->where('status', AssignmentStatus::Confirmed)->count(),
            'dismissed' => $assignments->where('status', AssignmentStatus::Dismissed)->count(),
            ...$extraMeta,
        ]]);
    }

    private function present(Adherent $adherent, AdherentObligation $assignment): AdherentObligationResource
    {
        $this->describe($assignment->load('obligation'), $this->engine->profile($adherent->loadMissing('regime')));

        return new AdherentObligationResource($assignment);
    }

    /**
     * @param  array<string, string|int|bool>  $profile
     */
    private function describe(AdherentObligation $assignment, array $profile): void
    {
        $assignment->setAttribute('applicable', $this->engine->applies($assignment->obligation, $profile));
        $assignment->setAttribute('current_periodicity', $this->engine->periodicity($assignment->obligation, $profile));
    }

    private function ensureNotStruckOff(Adherent $adherent): void
    {
        if ($adherent->status === AdherentStatus::StruckOff) {
            throw ApiException::adherentStruckOff();
        }
    }
}
