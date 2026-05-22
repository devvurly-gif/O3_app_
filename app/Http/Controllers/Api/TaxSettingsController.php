<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;

class TaxSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'tva_active' => TaxService::isTaxActive(),
            'default_tax_rate' => TaxService::getDefaultTaxRate(),
        ]);
    }
}
