<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class EcomConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if (!(bool) ($tenant->ecom_enabled ?? false)) {
            return response()->json([
                'enabled' => false,
                'message' => 'Boutique en ligne non activee pour ce tenant.',
            ]);
        }

        try {
            $companyName = Setting::get('general', 'company_name');
            $logo        = Setting::get('company', 'logo');
            $phone       = Setting::get('general', 'phone');
            $email       = Setting::get('general', 'email');
            $address     = Setting::get('company', 'address');

            $ecom = [
                'promo_banner'         => Setting::get('ecommerce', 'promo_banner', ''),
                'promo_banner_enabled' => Setting::get('ecommerce', 'promo_banner_enabled', 'true'),
                'primary_color'        => Setting::get('ecommerce', 'primary_color', '#f97316'),
                'default_theme'        => Setting::get('ecommerce', 'default_theme', 'light'),
                'delivery_threshold'   => Setting::get('ecommerce', 'delivery_threshold', '2000'),
                'address'              => Setting::get('ecommerce', 'address', $address ?? ''),
                'location'             => Setting::get('ecommerce', 'location', ''),
                'phone'                => Setting::get('ecommerce', 'phone', $phone ?? ''),
                'email'                => Setting::get('ecommerce', 'email', $email ?? ''),
                'shop_tagline'         => Setting::get('ecommerce', 'shop_tagline', ''),
                'instagram_url'        => Setting::get('ecommerce', 'instagram_url', ''),
                'facebook_url'         => Setting::get('ecommerce', 'facebook_url', ''),
                'whatsapp_number'      => Setting::get('ecommerce', 'whatsapp_number', ''),
            ];
        } catch (\Exception $e) {
            $companyName = null; $logo = null; $phone = null; $email = null; $address = null;
            $ecom = [];
        }

        return response()->json([
            'enabled' => true,
            'shop'    => [
                'name'    => $companyName ?? $tenant->name,
                'logo'    => $logo,
                'phone'   => $phone,
                'email'   => $email,
                'address' => $address,
            ],
            'ecommerce' => $ecom,
        ]);
    }
}
