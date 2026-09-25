<?php

namespace App\Http\Controllers\Api\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatsAppOrderImportRequest;
use App\Models\WhatsAppOrderImport;
use App\Services\Ventes\WhatsAppOrderImportService;
use Illuminate\Http\JsonResponse;

class WhatsAppOrderController extends Controller
{
    public function __construct(private WhatsAppOrderImportService $service)
    {
    }

    /**
     * POST /api/ventes/whatsapp-import
     *  - dry_run=true  : execute tous les controles, n'ecrit rien, renvoie ce qui serait cree.
     *  - dry_run=false : memes controles ; si aucune erreur BLOQUANT -> cree le BL (brouillon, stock pending).
     * Idempotent sur external_id : un 2e envoi d'un ID deja importe renvoie le document existant.
     */
    public function store(WhatsAppOrderImportRequest $request): JsonResponse
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

    /** GET /api/ventes/whatsapp-import/{externalId} */
    public function show(string $externalId): JsonResponse
    {
        $import = WhatsAppOrderImport::where('external_id', $externalId)->first();

        if (!$import) {
            return response()->json(['status' => 'not_found', 'external_id' => $externalId], 404);
        }

        return response()->json($import->response);
    }
}
