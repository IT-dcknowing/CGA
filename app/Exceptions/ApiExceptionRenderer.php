<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Donne à toutes les erreurs de l'API le même format : { message, code, errors? }.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(
                'validation_failed',
                'Les données envoyées ne sont pas valides.',
                422,
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => $this->respond('unauthenticated', 'Vous devez être connecté.', 401),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            (bool) config('app.debug') => null,
            default => $this->respond('server_error', 'Une erreur interne est survenue.', 500),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        [$code, $message] = match ($e->getStatusCode()) {
            401 => ['unauthenticated', 'Vous devez être connecté.'],
            403 => ['forbidden', 'Accès refusé.'],
            404 => ['not_found', 'Ressource introuvable.'],
            405 => ['method_not_allowed', 'Méthode non autorisée pour cette route.'],
            429 => ['too_many_requests', 'Trop de tentatives. Réessayez dans quelques instants.'],
            default => ['http_error', $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Erreur HTTP.')],
        };

        return $this->respond($code, $message, $e->getStatusCode(), headers: $e->getHeaders());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function respond(string $code, string $message, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, ...$extra], $status, $headers);
    }
}
