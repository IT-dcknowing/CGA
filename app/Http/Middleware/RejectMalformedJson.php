<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel ignore en silence un corps JSON mal formé : la requête passerait alors sans
 * aucune donnée. Ici, elle est refusée avec une erreur claire.
 */
class RejectMalformedJson
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $content = $request->getContent();

        if ($request->isJson() && trim($content) !== '' && ! json_validate($content)) {
            throw ApiException::invalidJson();
        }

        return $next($request);
    }
}
