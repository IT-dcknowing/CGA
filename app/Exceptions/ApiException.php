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
