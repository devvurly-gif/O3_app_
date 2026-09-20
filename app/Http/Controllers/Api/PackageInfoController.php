<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;

/**
 * Formule du tenant et capacités qui en découlent.
 *
 * Répondait auparavant depuis PackageService, avec trois défauts qui se
 * cumulaient : la formule était lue dans un réglage de la base du tenant
 * jamais écrit (tout le monde « basic »), `available_packages` était interrogé
 * en majuscules alors que les clés sont en minuscules (donc toujours vide), et
 * `features` sortait sous forme de map associative là où le frontend fait un
 * `.includes()` — qui ne répond jamais vrai sur un objet.
 *
 * Les clés de la réponse sont conservées telles quelles : le composable
 * useFeaturesSettings.ts s'en sert déjà.
 */
class PackageInfoController extends Controller
{
    public function __construct(private readonly PlanService $plans)
    {
    }

    public function getPackageInfo(): JsonResponse
    {
        $tenant   = $this->tenant();
        $features = $tenant ? $this->plans->featuresForTenant($tenant) : [];

        return response()->json([
            'current_package'       => $tenant?->plan,
            'features'              => $features,
            'available_packages'    => $this->plans->all(),
            'is_ocr_import_enabled' => in_array('ocr_import', $features, true),
            'variants_enabled'      => in_array('variants', $features, true),
        ]);
    }

    public function isFeatureEnabled(string $feature): JsonResponse
    {
        $tenant = $this->tenant();

        return response()->json([
            'feature' => $feature,
            'enabled' => $tenant !== null && $this->plans->tenantHasFeature($tenant, $feature),
        ]);
    }

    private function tenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
