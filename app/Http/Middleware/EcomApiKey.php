<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EcomApiKey
{
    /**
     * Validate the eCom API key from the request header.
     * Expected header: X-Ecom-Api-Key
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Ecom-Api-Key');
        $validKey = config('services.ecom.api_key');

        if (!$validKey || $apiKey !== $validKey) {
            return response()->json([
                'message' => 'Invalid or missing API key.',
            ], 401);
        }

        return $next($request);
    }
}
