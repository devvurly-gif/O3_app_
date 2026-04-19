<?php

namespace App\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleActive
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenantId = tenant('id');
        $moduleRecord = Module::where('tenant_id', $tenantId)
            ->where('name', $module)
            ->first();

        if (!$moduleRecord?->isEnabled()) {
            return response()->json([
                'message' => "Le module « {$module} » n'est pas activé.",
            ], 403);
        }

        return $next($request);
    }
}
