<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account is disabled. Please contact the IT office.',
                    'reason' => 'account_disabled',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is disabled, please report to the IT office.',
            ]);
        }

        return $next($request);
    }
}
