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

        return response()->json([
            'enabled'  => true,
            'api_key'  => $tenant->ecom_api_key,
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
