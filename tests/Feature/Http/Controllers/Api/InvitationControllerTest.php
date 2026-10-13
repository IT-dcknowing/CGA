<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\Space;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_gerant_invites_a_team_member_who_receives_an_sms(): void
    {
        Notification::fake();
        $cga = Cga::factory()->create(['name' => 'CGA Lagune']);

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson('/api/v1/invitations', [
            'phone' => '05 00 00 00 01',
            'name' => 'Awa Koné',
            'role' => 'comptable',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.phone', '+2250500000001')
            ->assertJsonPath('data.membership.role', 'comptable')
            ->assertJsonPath('data.membership.status', 'invited')
            ->assertJsonPath('data.membership.cga.id', $cga->id);
        $user = User::firstWhere('phone', '+2250500000001');
        $this->assertNull($user->password);
        Notification::assertSentTo($user, InvitationNotification::class, fn (InvitationNotification $notification, array $channels): bool => $channels === [SmsChannel::class]
            && str_contains($notification->toSms($user), 'CGA Lagune vous invite comme comptable')
            && str_contains($notification->toSms($user), '/activation?token='));
    }

    public function test_invitation_is_also_sent_by_email_when_the_address_is_given(): void
    {
        Notification::fake();

        $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Awa Koné',
            'email' => 'awa@example.ci',
            'role' => 'secretaire',
        ])->assertCreated();

        Notification::assertSentTo(
            User::firstWhere('phone', '+2250500000001'),
            InvitationNotification::class,
            fn (InvitationNotification $notification, array $channels): bool => $channels === [SmsChannel::class, 'mail'],
        );
    }

    public function test_person_with_an_account_gets_a_new_access_and_keeps_their_password(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['phone' => '0500000001']);

        $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => '05 00 00 00 01',
            'name' => 'Autre nom',
            'role' => 'expert_comptable',
        ])->assertCreated();

        $this->assertSame(1, User::where('phone', '+2250500000001')->count());
        $this->assertTrue(Hash::check('password', $existing->fresh()->password));
        $this->assertSame(MembershipStatus::Invited, $existing->memberships()->sole()->status);
    }

    public function test_inviting_again_sends_a_new_link_and_disables_the_previous_one(): void
    {
        Notification::fake();
        $token = $this->tokenForRole(Role::Gerant);
        $payload = ['phone' => '0500000001', 'name' => 'Awa Koné', 'role' => 'comptable'];

        $this->withToken($token)->postJson('/api/v1/invitations', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/invitations', $payload)->assertCreated();

        $sentTokens = Notification::sent(User::firstWhere('phone', '+2250500000001'), InvitationNotification::class)
            ->map(fn (InvitationNotification $notification): string => $notification->token);
        $this->assertCount(2, $sentTokens);
        $this->assertSame(1, Invitation::count());
        $this->assertSame(Invitation::hashToken($sentTokens->last()), Invitation::sole()->token_hash);
    }

    public function test_inviting_someone_who_already_has_the_access_returns_409(): void
    {
        $cga = Cga::factory()->create();
        $member = Membership::factory()->for($cga)->role(Role::Comptable)->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant, $cga))->postJson('/api/v1/invitations', [
            'phone' => $member->user->phone,
            'name' => 'Déjà membre',
            'role' => 'comptable',
        ]);

        $response->assertConflict()->assertJsonPath('code', 'already_member');
    }

    public function test_returns_422_for_a_role_the_inviter_cannot_give(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Awa Koné',
            'role' => 'presidente',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'Vous ne pouvez pas inviter une personne avec ce rôle.']);
        $this->assertNull(User::firstWhere('phone', '+2250500000001'));
    }

    public function test_returns_422_with_french_field_names_when_fields_are_missing(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations');

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'phone' => 'Le champ téléphone est obligatoire.',
            'name' => 'Le champ nom est obligatoire.',
            'role' => 'Le champ rôle est obligatoire.',
        ]);
    }

    public function test_accountant_can_only_invite_adherents(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Comptable))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Awa Koné',
            'role' => 'comptable',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'Vous ne pouvez pas inviter une personne avec ce rôle.']);
    }

    public function test_presidente_invites_the_gerant_of_a_chosen_cga(): void
    {
        Notification::fake();
        $cga = Cga::factory()->create();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Nouveau gérant',
            'role' => 'gerant',
            'cga_id' => $cga->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.membership.cga.id', $cga->id);
    }

    public function test_cncga_must_choose_the_cga_of_an_invited_gerant(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Nouveau gérant',
            'role' => 'gerant',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cga_id' => 'Choisissez le CGA dont cette personne sera le gérant.']);
    }

    public function test_presidente_invites_a_cncga_agent_without_any_cga(): void
    {
        Notification::fake();

        $response = $this->withToken($this->tokenForRole(Role::Presidente))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Nouvel agent',
            'role' => 'agent_cncga',
            'cga_id' => Cga::factory()->create()->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.membership.space', Space::Cncga->value)
            ->assertJsonPath('data.membership.cga', null);
        $invited = User::firstWhere('phone', '+2250500000001');
        Notification::assertSentTo($invited, InvitationNotification::class, fn (InvitationNotification $notification): bool => str_contains($notification->toSms($invited), 'Le CNCGA vous invite comme agent CNCGA.'));
    }

    public function test_agent_cncga_can_only_invite_gerants(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::AgentCncga))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Nouvel agent',
            'role' => 'agent_cncga',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_accountant_invites_a_person_of_an_active_adherent_company(): void
    {
        Notification::fake();
        $cga = Cga::factory()->create();
        $adherent = Adherent::factory()->active()->for($cga)->create(['company_name' => 'Boulangerie du Port']);

        $response = $this->withToken($this->tokenForRole(Role::Comptable, $cga))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Dirigeant de la boulangerie',
            'role' => 'adherent',
            'adherent_id' => $adherent->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.membership.space', 'adherent')
            ->assertJsonPath('data.membership.adherent_id', $adherent->id);
        $invited = User::firstWhere('phone', '+2250500000001');
        Notification::assertSentTo($invited, InvitationNotification::class, fn (InvitationNotification $notification): bool => str_contains($notification->toSms($invited), 'comme adhérent pour Boulangerie du Port'));
    }

    public function test_adherent_access_needs_an_active_dossier_of_the_same_cga(): void
    {
        $cga = Cga::factory()->create();
        $token = $this->tokenForRole(Role::Gerant, $cga);
        $draft = Adherent::factory()->for($cga)->create();
        $otherCgaAdherent = Adherent::factory()->active()->create();

        foreach ([$draft, $otherCgaAdherent] as $adherent) {
            $this->withToken($token)->postJson('/api/v1/invitations', [
                'phone' => '0500000001',
                'name' => 'Dirigeant',
                'role' => 'adherent',
                'adherent_id' => $adherent->id,
            ])->assertUnprocessable()->assertJsonValidationErrors(['adherent_id' => "Choisissez le dossier d'un adhérent actif de votre CGA."]);
        }
        $this->assertNull(User::firstWhere('phone', '+2250500000001'));
    }

    public function test_juriste_cannot_invite_anyone(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Juriste))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Dirigeant',
            'role' => 'adherent',
        ]);

        $response->assertForbidden();
    }

    public function test_disabled_account_cannot_be_invited(): void
    {
        $disabled = User::factory()->disabled()->create();

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => $disabled->phone,
            'name' => 'Compte désactivé',
            'role' => 'comptable',
        ]);

        $response->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $this->assertSame(0, $disabled->memberships()->count());
    }

    public function test_local_environment_copies_the_sms_into_the_response(): void
    {
        $this->app['env'] = 'local';

        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Awa Koné',
            'role' => 'comptable',
        ]);

        $response->assertCreated()->assertJsonPath('dev_sms.to', '+2250500000001');
        $this->assertStringContainsString('/activation?token=', $response->json('dev_sms.message'));
    }

    public function test_sms_is_never_copied_into_the_response_outside_the_local_environment(): void
    {
        $response = $this->withToken($this->tokenForRole(Role::Gerant))->postJson('/api/v1/invitations', [
            'phone' => '0500000001',
            'name' => 'Awa Koné',
            'role' => 'comptable',
        ]);

        $response->assertCreated()->assertJsonMissingPath('dev_sms');
    }

    public function test_new_person_chooses_a_password_and_is_logged_into_the_new_space(): void
    {
        $membership = Membership::factory()->invited()->for(User::factory()->withoutPassword())->create();
        $invitation = Invitation::factory()->for($membership)->withToken('jeton-recu-par-sms')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', [
            'token' => 'jeton-recu-par-sms',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertOk()->assertJsonPath('data.active_membership_id', $membership->id);
        $this->assertIsString($response->json('data.token'));
        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $membership->user->fresh()->password));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_person_with_an_account_activates_the_access_without_a_new_password(): void
    {
        $membership = Membership::factory()->invited()->create();
        Invitation::factory()->for($membership)->withToken('jeton-recu-par-sms')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', ['token' => 'jeton-recu-par-sms']);

        $response->assertOk();
        $this->assertTrue(Hash::check('password', $membership->user->fresh()->password));
    }

    public function test_new_person_must_choose_a_password(): void
    {
        $membership = Membership::factory()->invited()->for(User::factory()->withoutPassword())->create();
        Invitation::factory()->for($membership)->withToken('jeton-recu-par-sms')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', ['token' => 'jeton-recu-par-sms']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Choisissez un mot de passe pour activer votre compte.']);
        $this->assertSame(MembershipStatus::Invited, $membership->fresh()->status);
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->postJson('/api/v1/auth/accept-invitation', [
            'token' => 'jeton',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'autre-chose',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'La confirmation du champ mot de passe ne correspond pas.']);
    }

    public function test_expired_link_is_refused(): void
    {
        Invitation::factory()->expired()->withToken('jeton-expire')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', ['token' => 'jeton-expire', 'password' => 'mot-de-passe', 'password_confirmation' => 'mot-de-passe']);

        $response->assertUnprocessable()->assertJsonPath('code', 'invitation_invalid');
    }

    public function test_link_cannot_be_used_twice(): void
    {
        Invitation::factory()->accepted()->withToken('jeton-utilise')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', ['token' => 'jeton-utilise']);

        $response->assertUnprocessable()->assertJsonPath('code', 'invitation_invalid');
    }

    public function test_disabled_account_cannot_accept_an_invitation(): void
    {
        $membership = Membership::factory()->invited()->for(User::factory()->disabled())->create();
        Invitation::factory()->for($membership)->withToken('jeton-recu-par-sms')->create();

        $response = $this->postJson('/api/v1/auth/accept-invitation', ['token' => 'jeton-recu-par-sms']);

        $response->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $this->assertSame(MembershipStatus::Invited, $membership->fresh()->status);
    }
}
