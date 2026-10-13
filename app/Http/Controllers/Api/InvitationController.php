<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Support\Invitations;
use App\Support\SessionOpener;
use App\Support\Sms\DevSms;
use Illuminate\Http\JsonResponse;

/**
 * Invitation d'une personne par SMS, puis activation de son accès (Q11, Q12).
 */
class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Invitations $invitations): JsonResponse
    {
        $invitation = $invitations->invite(
            phone: $request->validated('phone'),
            name: $request->validated('name'),
            email: $request->validated('email'),
            role: $request->role(),
            cga: $request->targetCga(),
            inviter: $request->user(),
        );

        return (new InvitationResource($invitation->load('membership.user', 'membership.cga')))
            ->additional(DevSms::payload())
            ->response()
            ->setStatusCode(201);
    }

    /**
     * La personne ouvre le lien reçu : son accès devient actif et elle est connectée dans cet espace.
     */
    public function accept(AcceptInvitationRequest $request, Invitations $invitations, SessionOpener $sessions): JsonResponse
    {
        $membership = $invitations->accept($request->validated('token'), $request->validated('password'));

        return response()->json([
            'data' => $sessions->open($membership->user, $request->validated('device_name'), $membership->load('cga')),
        ]);
    }
}
