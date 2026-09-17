<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ApplyActiveRole::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
            \App\Http\Middleware\TrackSystemChanges::class,
            \App\Http\Middleware\PreventAuthenticatedPageCaching::class,
        ]);
        $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $exception, Request $request) {
            if (! $request->expectsJson()) {
                $hasReferer = $request->headers->has('referer');
                $hasPreviousSession = $request->hasSession() && $request->session()->has('_previous.url');

                $fallback = auth()->check()
                    ? (auth()->user()->role === 'student' ? route('requests.index') : route('dashboard'))
                    : route('login');

                $previous = url()->previous();
                $current = $request->fullUrl();

                $destination = ($hasReferer || $hasPreviousSession) && ($previous && $previous !== $current && $previous !== url('/'))
                    ? $previous
                    : $fallback;

                return redirect()->to($destination)
                    ->with('error', 'The page or request you were looking for could not be found.');
            }
        });
    })->create();
