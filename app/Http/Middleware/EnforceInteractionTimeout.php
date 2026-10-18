<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceInteractionTimeout
{
    private const SESSION_KEY = 'last_user_interaction_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $lastInteraction = $request->session()->get(self::SESSION_KEY);
        $now = now()->timestamp;

        if (is_numeric($lastInteraction) && $now - (int) $lastInteraction >= config('session.lifetime') * 60) {
            Cache::forget(TrackSystemChanges::PRESENCE_PREFIX.$request->user()->id);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired due to inactivity.',
                    'reason' => 'inactivity',
                ], 401);
            }

            return redirect()->route('login', ['expired' => 1]);
        }

        $backgroundPoll = $request->routeIs('realtime.version', 'realtime.presence', 'messages.updates');
        if ($lastInteraction === null || ! $backgroundPoll || ($request->routeIs('realtime.version') && $request->header('X-User-Activity') === '1')) {
            $request->session()->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }
}
