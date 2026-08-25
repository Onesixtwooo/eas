<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyActiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $assigned = $user->assignedRoles();
            $active = $request->session()->get('active_role');
            if (! in_array($active, $assigned, true)) {
                $active = in_array($user->getRawOriginal('role'), $assigned, true)
                    ? $user->getRawOriginal('role')
                    : $assigned[0];
                $request->session()->put('active_role', $active);
            }
            $user->setAttribute('role', $active);
            $user->syncOriginalAttribute('role');
        }

        return $next($request);
    }
}
