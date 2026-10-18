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
    private const PRESENCE_REFRESH_SECONDS = 60;
    private const PRESENCE_TTL_SECONDS = 90;

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;
        if ($userId && ! $request->routeIs('realtime.presence', 'messages.updates')
            && now()->timestamp - (int) $request->session()->get('last_presence_refresh_at', 0) >= self::PRESENCE_REFRESH_SECONDS) {
            Cache::put(self::PRESENCE_PREFIX.$userId, true, now()->addSeconds(self::PRESENCE_TTL_SECONDS));
            $request->session()->put('last_presence_refresh_at', now()->timestamp);
        }

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
