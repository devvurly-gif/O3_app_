<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchaseImportRequest;
use App\Models\PurchaseImport;
use App\Services\Purchases\PurchaseImportService;
use Illuminate\Http\JsonResponse;

class PurchaseImportController extends Controller
{
    public function __construct(private PurchaseImportService $service)
    {
    }

    /**
     * POST /api/achats/import
     *  - dry_run=true  : exécute tous les contrôles, n'écrit rien, renvoie ce qui serait créé.
     *  - dry_run=false : mêmes contrôles ; si aucune erreur BLOQUANT → crée le document.
     * Idempotent sur external_id : un 2e envoi d'un ID déjà importé renvoie le document existant.
     */
    public function store(PurchaseImportRequest $request): JsonResponse
    {
        $result = $this->service->handle(
            payload: $request->validated(),
            dryRun: (bool) $request->boolean('dry_run'),
            userId: $request->user()->id,
        );

        $http = match ($result['status']) {
            'created'          => 201,
            'already_imported',
            'valid'            => 200,
            default            => 422, // rejected
        };

        return response()->json($result, $http);
    }

    /** GET /api/achats/import/{externalId} */
    public function show(string $externalId): JsonResponse
    {
        $import = PurchaseImport::where('external_id', $externalId)->first();

        if (!$import) {
            return response()->json(['status' => 'not_found', 'external_id' => $externalId], 404);
        }

        return response()->json($import->response);
    }
}
