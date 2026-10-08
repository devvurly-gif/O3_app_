<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme les routes des agents IA (orchestrateur, ordres, activité) tant que l'option n'a pas été allumée pour ce tenant
 * depuis la gestion des tenants, ou quand sa formule / son paiement n'y donnent plus droit.
 */
class CheckAgentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant && !$tenant->agentsEnabled()) {
            return response()->json(['message' => 'Les agents IA ne sont pas activés pour ce compte. Contactez O3App pour les activer.'], 403);
        }
        if ($tenant && !$tenant->agentsAvailable()) {
            return response()->json(['message' => "Les agents IA sont réservés aux formules Pro et Business à jour de paiement. Contactez O3App."], 403);
        }

        return $next($request);
    }
}