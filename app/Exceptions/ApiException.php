<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * Erreur métier renvoyée au client avec un code stable que le front peut tester.
 */
class ApiException extends Exception implements ShouldntReport
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public static function spaceRequired(): self
    {
        return new self('space_required', 'Choisissez un espace de travail.', 403);
    }

    public static function spaceForbidden(): self
    {
        return new self('space_forbidden', "Cette action n'est pas disponible dans votre espace.", 403);
    }

    public static function roleForbidden(): self
    {
        return new self('forbidden', 'Votre rôle ne permet pas cette action.', 403);
    }

    public static function accountDisabled(): self
    {
        return new self('account_disabled', 'Ce compte est désactivé.', 403);
    }

    public static function cgaSuspended(): self
    {
        return new self('cga_suspended', 'Ce CGA est suspendu : seule la consultation est possible.', 403);
    }

    public static function cgaAlreadySuspended(): self
    {
        return new self('cga_already_suspended', 'Ce CGA est déjà suspendu.', 409);
    }

    public static function cgaNotSuspended(): self
    {
        return new self('cga_not_suspended', "Ce CGA n'est pas suspendu.", 409);
    }

    public static function alreadyMember(): self
    {
        return new self('already_member', 'Cette personne a déjà cet accès.', 409);
    }

    public static function invitationInvalid(): self
    {
        return new self('invitation_invalid', "Ce lien d'invitation n'est plus valable. Demandez une nouvelle invitation.", 422);
    }

    public static function resetCodeInvalid(): self
    {
        return new self('reset_code_invalid', 'Code invalide ou expiré. Demandez un nouveau code.', 422);
    }

    public static function settingNotEditable(): self
    {
        return new self('setting_not_editable', 'Ce paramètre est fixé par le CNCGA pour tout le réseau.', 403);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->status);
    }
}
