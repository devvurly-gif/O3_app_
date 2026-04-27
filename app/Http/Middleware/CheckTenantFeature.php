<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on a tenant feature flag stored on the central tenants table
 * (e.g. `pos_enabled`, `ecom_enabled`). Replaces the legacy CheckModuleActive
 * middleware which used the now-removed `modules` table.
 *
 * Usage in routes/api.php:
 *   Route::middleware('feature:pos')->prefix('pos')->group(...)
 */
class CheckTenantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();
        $flag   = "{$feature}_enabled";

        if (!$tenant || !$tenant->{$flag}) {
            return response()->json([
                'message' => "La fonctionnalité « {$feature} » n'est pas activée pour ce compte.",
            ], 403);
        }

        return $next($request);
    }
}
