<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackSystemChanges
{
    public const CACHE_KEY = 'eas.system.revision';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $authenticationOnly = $request->routeIs(
            'login.attempt',
            'logout',
            'register.send-otp',
            'register.verify-email.*',
            'password.*',
            'messages.*',
        );

        if (! $request->isMethodSafe() && ! $authenticationOnly && $response->getStatusCode() < 400) {
            Cache::forever(self::CACHE_KEY, (string) Str::uuid());
        }

        return $response;
    }
}
