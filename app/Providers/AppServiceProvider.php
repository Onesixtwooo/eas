<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('verification-resend', function (Request $request) {
            $response = function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);
                $message = "Please wait {$seconds} seconds before requesting another verification code.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                $redirect = redirect()->route('register.verify-email')->with('error', $message);
                $redirect->headers->add($headers);

                return $redirect;
            };

            return [
                Limit::perMinute(2)
                    ->by('student:'.($request->session()->get('email_verification_user_id') ?: $request->ip()))
                    ->response($response),
                Limit::perMinute(20)->by('ip:'.$request->ip())->response($response),
            ];
        });
    }
}
