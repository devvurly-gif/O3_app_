<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadUserPermissions
{
    /**
     * Eager-load the role and permissions for the authenticated user
     * to avoid N+1 queries during authorization checks.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('role.permissions');
        }

        return $next($request);
    }
}
