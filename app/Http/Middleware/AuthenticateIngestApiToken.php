<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIngestApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('ingest.api_token');

        if (! is_string($configuredToken) || $configuredToken === '') {
            return response()->json(['message' => 'Не авторизован.'], Response::HTTP_UNAUTHORIZED);
        }

        $providedToken = $request->bearerToken();

        if (! is_string($providedToken) || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Не авторизован.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
