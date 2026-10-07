<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme les routes des agents IA (orchestrateur, ordres, activité) quand le super-administrateur les a désactivés
 * pour ce tenant, depuis la gestion des tenants.
 */
class CheckAgentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && !$tenant->agentsEnabled()) {
            return response()->json(['message' => "Les agents IA ne sont pas activés pour ce compte. Contactez O3App pour les activer."], 403);
        }

        return $next($request);
    }
}