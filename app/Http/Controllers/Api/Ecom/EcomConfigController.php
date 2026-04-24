<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class EcomConfigController extends Controller
{
    /**
     * Return public ecom config for the current tenant.
     * Called by O3_ecom frontend on boot (no API key required).
     */
    public function __invoke(): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $ecomEnabled = (bool) ($tenant->ecom_enabled ?? false);

        if (!$ecomEnabled) {
            return response()->json([
                'enabled' => false,
                'message' => 'Boutique en ligne non activee pour ce tenant.',
            ]);
        }

        // Public shop info from tenant settings
        $companyName = null;
        $logo = null;
        $phone = null;
        $email = null;
        $address = null;

        try {
            $companyName = Setting::get('general', 'company_name');
            $logo = Setting::get('company', 'logo');
            $phone = Setting::get('general', 'phone');
            $email = Setting::get('general', 'email');
            $address = Setting::get('company', 'address');
        } catch (\Exception $e) {
            // Settings table may not exist yet
        }

        // SECURITY: `ecom_api_key` is NO LONGER returned here.
        // This endpoint is unauthenticated (needs to be, since the shop
        // frontend boots without any auth context), so exposing the
        // tenant's API key turned the entire `ecom.key` middleware into
        // security theater — anyone could fetch it and bypass all
        // protected `/api/ecom/*` endpoints.
        //
        // The O3_ecom frontend must now receive its API key at build
        // time via `VITE_ECOM_API_KEY`. On the VPS, set `ECOM_API_KEY`
        // in the Laravel `.env` to the same value (middleware also
        // accepts that global fallback via `config('services.ecom.api_key')`).
        return response()->json([
            'enabled'  => true,
            'shop'     => [
                'name'    => $companyName ?? $tenant->name,
                'logo'    => $logo,
                'phone'   => $phone,
                'email'   => $email,
                'address' => $address,
            ],
        ]);
    }
}
