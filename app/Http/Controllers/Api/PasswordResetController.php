<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\PasswordResets;
use App\Support\SessionOpener;
use App\Support\Sms\DevSms;
use Illuminate\Http\JsonResponse;

/**
 * Mot de passe oublié par code SMS (Q12).
 */
class PasswordResetController extends Controller
{
    /**
     * La réponse est la même que le numéro ait un compte ou non.
     */
    public function sendCode(ForgotPasswordRequest $request, PasswordResets $resets): JsonResponse
    {
        $resets->request($request->validated('phone'));

        return response()->json([
            'data' => ['message' => "Si ce numéro correspond à un compte, un code vient d'être envoyé par SMS."],
            ...DevSms::payload(),
        ]);
    }

    /**
     * Le nouveau mot de passe remplace l'ancien, toutes les sessions sont fermées
     * et la personne est connectée avec un nouveau jeton.
     */
    public function reset(ResetPasswordRequest $request, PasswordResets $resets, SessionOpener $sessions): JsonResponse
    {
        $user = $resets->reset(
            $request->validated('phone'),
            $request->validated('code'),
            $request->validated('password'),
        );

        return response()->json([
            'data' => $sessions->open($user, $request->validated('device_name')),
        ]);
    }
}
