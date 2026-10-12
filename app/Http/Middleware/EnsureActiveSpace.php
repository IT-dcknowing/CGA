<?php

namespace App\Http\Middleware;

use App\Enums\Space;
use App\Exceptions\ApiException;
use App\Models\PersonalAccessToken;
use App\Support\ActiveSpace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSpace
{
    public function __construct(private ActiveSpace $activeSpace) {}

    /**
     * Exige l'espace choisi avec /auth/switch-space et le rend disponible pour la suite
     * de la requête. « espace.actif:cga,adherent » limite la route à ces espaces.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$spaces): Response
    {
        $token = $request->user()?->currentAccessToken();
        $membership = $token instanceof PersonalAccessToken ? $token->membership : null;

        if ($membership === null || $membership->user_id !== $request->user()->getKey() || ! $membership->isActive()) {
            throw ApiException::spaceRequired();
        }

        if ($spaces !== [] && ! in_array($membership->space->value, $spaces, true)) {
            throw ApiException::spaceForbidden();
        }

        // Q19 : les agents d'un CGA suspendu ne font plus que consulter ; ses adhérents gardent leur accès.
        if ($membership->space === Space::Cga && ! $request->isMethodSafe() && $membership->cga->isSuspended()) {
            throw ApiException::cgaSuspended();
        }

        $this->activeSpace->set($membership);

        return $next($request);
    }
}
