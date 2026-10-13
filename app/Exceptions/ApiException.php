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

    public static function invalidJson(): self
    {
        return new self(
            'invalid_json',
            "Le corps de la requête n'est pas un JSON valide : vérifiez les virgules, les guillemets et les variables vides.",
            400,
        );
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

    public static function adherentNotDraft(): self
    {
        return new self('adherent_not_draft', 'Ce dossier a déjà été validé.', 409);
    }

    public static function adherentNotActive(): self
    {
        return new self('adherent_not_active', "Cette action n'est possible que sur un dossier actif.", 409);
    }

    public static function adherentStruckOff(): self
    {
        return new self('adherent_struck_off', 'Un dossier radié ne peut plus être modifié.', 409);
    }

    public static function adherentNotStruckOff(): self
    {
        return new self('adherent_not_struck_off', "Ce dossier n'est pas radié.", 409);
    }

    public static function strikeOffCancellationExpired(): self
    {
        return new self(
            'strike_off_cancellation_expired',
            "Le délai d'annulation de la radiation est dépassé : un retour de cet adhérent est une nouvelle adhésion.",
            409,
        );
    }

    public static function nccAlreadyOpen(): self
    {
        return new self('ncc_already_open', 'Un autre dossier ouvert porte déjà ce NCC dans votre CGA.', 409);
    }

    public static function cgaNotApproved(): self
    {
        return new self('cga_not_approved', "Le CGA n'a pas encore son agrément : il ne peut pas inscrire d'adhérent.", 409);
    }

    public static function cgaAbbreviationMissing(): self
    {
        return new self('cga_abbreviation_missing', "Renseignez l'abréviation du CGA dans sa fiche avant de valider un dossier.", 409);
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
