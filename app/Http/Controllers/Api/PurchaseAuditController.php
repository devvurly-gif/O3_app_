<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Purchases\PurchaseAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseAuditController extends Controller
{
    /**
     * GET /api/achats/audit — contrôle en lecture seule des bons de réception et factures
     * d'achat présents dans O3. Jeton dédié avec l'ability "achats:audit" (indépendante de
     * "achats:import" : un jeton d'import ne peut pas lire la liste, et inversement).
     */
    public function index(Request $request, PurchaseAuditService $service): JsonResponse
    {
        abort_unless($request->user()?->tokenCan('achats:audit'), 403, 'Ability achats:audit requise.');

        $filters = $request->validate([
            'from'        => ['nullable', 'date_format:Y-m-d'],
            'to'          => ['nullable', 'date_format:Y-m-d'],
            'type'        => ['nullable', Rule::in(PurchaseAuditService::TYPES)],
            'limit'       => ['nullable', 'integer', 'between:1,500'],
            'only_issues' => ['nullable', 'boolean'],
        ]);
        $filters['only_issues'] = $request->boolean('only_issues');

        return response()->json($service->audit($filters));
    }
}
