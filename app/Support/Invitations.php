<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\SettingKey;
use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitations (Q11, Q12) : un accès est créé « invité », la personne reçoit un lien par SMS
 * et choisit son mot de passe en l'ouvrant.
 */
class Invitations
{
    public function __construct(private Settings $settings) {}

    /**
     * La personne est retrouvée par son téléphone, ou créée sans mot de passe. Inviter de nouveau
     * une personne qui n'a pas encore accepté lui envoie un nouveau lien et rend l'ancien inutilisable.
     */
    public function invite(string $phone, string $name, ?string $email, Role $role, ?Cga $cga, User $inviter, ?Adherent $adherent = null): Invitation
    {
        return DB::transaction(function () use ($phone, $name, $email, $role, $cga, $inviter, $adherent): Invitation {
            $user = User::firstWhere('phone', $phone);

            if ($user?->isDisabled()) {
                throw ApiException::accountDisabled();
            }

            $user ??= User::create(['name' => $name, 'phone' => $phone, 'email' => $email]);

            if ($user->email === null && $email !== null) {
                $user->update(['email' => $email]);
            }

            $membership = Membership::query()
                ->where('user_id', $user->id)
                ->where('role', $role)
                ->where('cga_id', $cga?->id)
                ->where('adherent_id', $adherent?->id)
                ->first();

            if ($membership?->isActive()) {
                throw ApiException::alreadyMember();
            }

            $membership ??= new Membership;
            $membership->forceFill([
                'user_id' => $user->id,
                'cga_id' => $cga?->id,
                'adherent_id' => $adherent?->id,
                'role' => $role,
                'status' => MembershipStatus::Invited,
            ])->save();

            $membership->invitations()->whereNull('accepted_at')->delete();

            $token = Str::random(40);
            $invitation = $membership->invitations()->make()->forceFill([
                'invited_by' => $inviter->id,
                'token_hash' => Invitation::hashToken($token),
                'expires_at' => now()->addDays($this->settings->get(SettingKey::InvitationValidDays)),
            ]);
            $invitation->save();

            ActivityLog::record('invitation_sent', $membership, ['role' => $role->value], $inviter);
            $user->notify(new InvitationNotification($invitation->setRelation('membership', $membership->load('cga', 'adherent')), $token));

            return $invitation;
        });
    }

    /**
     * Active l'accès. Une personne qui n'a pas encore de compte choisit ici son mot de passe ;
     * une personne qui en a déjà un garde le sien.
     */
    public function accept(string $token, ?string $password): Membership
    {
        $invitation = Invitation::query()
            ->pending()
            ->with('membership.user')
            ->firstWhere('token_hash', Invitation::hashToken($token));

        $membership = $invitation?->membership;

        if ($membership === null || $membership->status !== MembershipStatus::Invited) {
            throw ApiException::invitationInvalid();
        }

        $user = $membership->user;

        if ($user->isDisabled()) {
            throw ApiException::accountDisabled();
        }

        if ($user->password === null && $password === null) {
            throw ValidationException::withMessages([
                'password' => 'Choisissez un mot de passe pour activer votre compte.',
            ]);
        }

        DB::transaction(function () use ($invitation, $membership, $user, $password): void {
            if ($user->password === null) {
                $user->update(['password' => $password]);
            }

            $membership->forceFill(['status' => MembershipStatus::Active])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            ActivityLog::record('invitation_accepted', $membership, causer: $user);
        });

        return $membership;
    }
}
