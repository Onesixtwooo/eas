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
    public const PRESENCE_PREFIX = 'eas.presence.';

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;
        if ($userId) Cache::put(self::PRESENCE_PREFIX.$userId, true, now()->addSeconds(30));

        $response = $next($request);

        if ($userId && $request->routeIs('logout')) Cache::forget(self::PRESENCE_PREFIX.$userId);

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
