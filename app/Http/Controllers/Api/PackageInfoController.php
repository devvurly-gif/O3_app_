<?php

namespace App\Http\Controllers\Api;

use App\Services\PackageService;
use Illuminate\Http\JsonResponse;

class PackageInfoController
{
    public function getPackageInfo(): JsonResponse
    {
        $packageService = new PackageService();
        $currentPackage = $packageService->getCurrentPackage();
        $features = $packageService->getCurrentPackageFeatures();

        return response()->json([
            'current_package' => $currentPackage,
            'features' => $features,
            'available_packages' => [
                'BASIC' => $packageService->getPackageInfo('BASIC'),
                'PROFESSIONAL' => $packageService->getPackageInfo('PROFESSIONAL'),
                'BUSINESS' => $packageService->getPackageInfo('BUSINESS'),
            ],
            'is_ocr_import_enabled' => $packageService->isOcrImportEnabled(),
        ]);
    }

    public function isFeatureEnabled(string $feature): JsonResponse
    {
        $packageService = new PackageService();
        
        return response()->json([
            'feature' => $feature,
            'enabled' => $packageService->isFeatureEnabled($feature),
        ]);
    }
}
