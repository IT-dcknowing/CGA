<?php

namespace App\Support;

use App\Http\Resources\MembershipResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ouvre une session d'API : connexion, acceptation d'invitation ou nouveau mot de passe.
 */
class SessionOpener
{
    /**
     * Crée un jeton et choisit l'espace : celui demandé, sinon l'unique accès actif,
     * sinon l'accès marqué principal.
     *
     * @return array{token: string, user: UserResource, memberships: mixed, active_membership_id: int|null}
     */
    public function open(User $user, ?string $deviceName = null, ?Membership $preferred = null): array
    {
        $memberships = $this->activeMemberships($user);
        $activeMembership = $preferred ?? ($memberships->count() === 1
            ? $memberships->first()
            : $memberships->firstWhere('is_primary', true));

        $token = $user->createToken($deviceName ?? 'api');
        $token->accessToken->membership()->associate($activeMembership)->save();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        ActivityLog::record('login', $user, causer: $user);

        return [
            'token' => $token->plainTextToken,
            ...$this->describe($user, $memberships, $activeMembership?->id),
        ];
    }

    /**
     * @param  Collection<int, Membership>  $memberships
     * @return array{user: UserResource, memberships: mixed, active_membership_id: int|null}
     */
    public function describe(User $user, Collection $memberships, ?int $activeMembershipId): array
    {
        return [
            'user' => new UserResource($user),
            'memberships' => MembershipResource::collection($memberships),
            'active_membership_id' => $activeMembershipId,
        ];
    }

    /**
     * @return Collection<int, Membership>
     */
    public function activeMemberships(User $user): Collection
    {
        return $user->memberships()->active()->with('cga')->orderBy('id')->get();
    }
}
