<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EcomApiKey
{
    /**
     * Validate the eCom API key.
     *
     * Checks against the tenant's stored ecom_api_key (in JSON data column).
     * Falls back to the global ECOM_API_KEY env var for backward compatibility.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Ecom-Api-Key');

        if (!$apiKey) {
            return response()->json([
                'message' => 'Missing API key.',
            ], 401);
        }

        // Try tenant-specific key first (Stancl tenancy)
        $tenant = tenant();
        if ($tenant) {
            $tenantKey = $tenant->ecom_api_key ?? null;
            // hash_equals : comparaison a temps constant, pour ne pas laisser
            // le temps de reponse trahir le prefixe correct de la cle.
            if ($tenantKey && hash_equals((string) $tenantKey, $apiKey)) {
                return $next($request);
            }
        }

        // Fallback to global key
        $globalKey = config('services.ecom.api_key');
        if ($globalKey && hash_equals((string) $globalKey, $apiKey)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Invalid API key.',
        ], 401);
    }
}
