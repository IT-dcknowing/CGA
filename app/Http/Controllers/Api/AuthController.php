<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchSpaceRequest;
use App\Http\Resources\MembershipResource;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use App\Support\SessionOpener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private SessionOpener $sessions) {}

    /**
     * Connexion par téléphone et mot de passe (Q12). L'espace est choisi d'office
     * quand la personne n'a qu'un accès actif, ou un accès marqué principal.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('phone', $request->validated('phone'))->first();

        if ($user?->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Numéro de téléphone ou mot de passe incorrect.',
            ]);
        }

        if ($user->isDisabled()) {
            throw ApiException::accountDisabled();
        }

        return response()->json([
            'data' => $this->sessions->open($user, $request->validated('device_name')),
        ]);
    }

    /**
     * Profil, accès actifs et espace actuellement choisi.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $this->sessions->activeMemberships($user);
        $activeMembershipId = $user->currentAccessToken()->membership_id;

        return response()->json([
            'data' => $this->sessions->describe(
                $user,
                $memberships,
                $memberships->contains('id', $activeMembershipId) ? $activeMembershipId : null,
            ),
        ]);
    }

    /**
     * Rattache l'espace choisi au jeton en cours : les appels suivants s'y limitent.
     */
    public function switchSpace(SwitchSpaceRequest $request): JsonResponse
    {
        $membership = Membership::with('cga')->findOrFail($request->validated('membership_id'));

        $request->user()->currentAccessToken()->membership()->associate($membership)->save();
        ActivityLog::record('space_switched', $membership);

        return response()->json([
            'data' => [
                'active_membership' => new MembershipResource($membership),
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        ActivityLog::record('logout', $request->user());
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
