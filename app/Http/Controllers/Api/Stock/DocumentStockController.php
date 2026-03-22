<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\DocumentHeader;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Services\CacheService;
use App\Services\DocumentIncrementorService;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DocumentStockController extends Controller
{
    public function __construct(
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentIncrementorService $incrementorService,
        private StockMouvementService $stockService,
    ) {
    }

    /**
     * POST /api/stock/documents/{document}/appliquer
     *
     * Confirme un document de stock (draft → confirmed) puis applique les
     * mouvements de stock (confirmed → applied).
     *
     * Compatible avec : StockEntry, StockExit, StockAdjustment, StockTransfer
     */
    public function appliquer(DocumentHeader $document): JsonResponse
    {
        $stockTypes = ['StockEntry', 'StockExit', 'StockAdjustmentNote', 'StockTransfer'];

        if (!in_array($document->document_type, $stockTypes)) {
            return response()->json([
                'message' => 'Ce document n\'est pas une opération de stock.',
            ], 422);
        }

        if ($document->status === 'applied') {
            return response()->json([
                'message' => 'Ce document a déjà été appliqué.',
            ], 422);
        }

        if ($document->status === 'cancelled') {
            return response()->json([
                'message' => 'Ce document est annulé et ne peut pas être appliqué.',
            ], 422);
        }

        if ($document->document_type === 'StockTransfer' && !$document->warehouse_dest_id) {
            return response()->json([
                'message' => 'Un transfert de stock nécessite un entrepôt de destination.',
            ], 422);
        }

        DB::transaction(function () use ($document) {
            $document->loadMissing('lignes');

            $this->stockService->processDocument($document);

            $document->update(['status' => 'applied']);
        });

        CacheService::flushProducts();

        return response()->json([
            'message' => 'Document de stock appliqué avec succès.',
            'data'    => $document->fresh(['lignes.product', 'warehouse', 'warehouseDest', 'user', 'stockMouvements']),
        ]);
    }

    /**
     * POST /api/stock/documents/{document}/annuler
     *
     * Annule un document de stock (draft ou confirmed → cancelled).
     * Un document déjà appliqué ne peut pas être annulé.
     */
    public function annuler(DocumentHeader $document): JsonResponse
    {
        $stockTypes = ['StockEntry', 'StockExit', 'StockAdjustmentNote', 'StockTransfer'];

        if (!in_array($document->document_type, $stockTypes)) {
            return response()->json([
                'message' => 'Ce document n\'est pas une opération de stock.',
            ], 422);
        }

        if ($document->status === 'applied') {
            return response()->json([
                'message' => 'Un document appliqué ne peut pas être annulé. Créez une opération corrective.',
            ], 422);
        }

        $document->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Document de stock annulé.',
            'data'    => $document->fresh(['warehouse', 'user']),
        ]);
    }
}
