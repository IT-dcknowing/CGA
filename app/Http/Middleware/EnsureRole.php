<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\ActiveSpace;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function __construct(private ActiveSpace $activeSpace) {}

    /**
     * Réserve la route aux rôles listés, par exemple « role:gerant,expert_comptable ».
     * Se place après « espace.actif », qui fixe le rôle de la requête.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $this->activeSpace->role()
            ?? throw new LogicException('Le middleware « role » doit suivre « espace.actif ».');

        if (! in_array($role->value, $roles, true)) {
            throw ApiException::roleForbidden();
        }

        return $next($request);
    }
}
