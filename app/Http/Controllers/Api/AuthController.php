<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SwitchSpaceRequest;
use App\Http\Resources\MembershipResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        $memberships = $this->activeMemberships($user);
        $defaultMembership = $memberships->count() === 1
            ? $memberships->first()
            : $memberships->firstWhere('is_primary', true);

        $token = $user->createToken($request->validated('device_name') ?? 'api');
        $token->accessToken->membership()->associate($defaultMembership)->save();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        ActivityLog::record('login', $user, causer: $user);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                ...$this->session($user, $memberships, $defaultMembership?->id),
            ],
        ]);
    }

    /**
     * Profil, accès actifs et espace actuellement choisi.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $this->activeMemberships($user);
        $activeMembershipId = $user->currentAccessToken()->membership_id;

        return response()->json([
            'data' => $this->session(
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

    /**
     * @return Collection<int, Membership>
     */
    private function activeMemberships(User $user): Collection
    {
        return $user->memberships()->active()->with('cga')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Membership>  $memberships
     * @return array{user: UserResource, memberships: mixed, active_membership_id: int|null}
     */
    private function session(User $user, Collection $memberships, ?int $activeMembershipId): array
    {
        return [
            'user' => new UserResource($user),
            'memberships' => MembershipResource::collection($memberships),
            'active_membership_id' => $activeMembershipId,
        ];
    }
}
