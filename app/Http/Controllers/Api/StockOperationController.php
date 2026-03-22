<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOperationController extends Controller
{
    public function __construct(private StockOperationService $stockOps)
    {
    }

    /**
     * POST /api/stock/entree — Manual stock entry.
     */
    public function entree(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity'     => ['required', 'numeric', 'min:0.01'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $mouvement = $this->stockOps->manualEntry(
            $data['product_id'],
            $data['warehouse_id'],
            $data['quantity'],
            $data['unit_cost'] ?? null,
            $request->user()->id,
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Entrée de stock enregistrée.',
            'data'    => $mouvement->load(['product', 'warehouse', 'user']),
        ], 201);
    }

    /**
     * POST /api/stock/sortie — Manual stock exit.
     */
    public function sortie(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity'     => ['required', 'numeric', 'min:0.01'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $mouvement = $this->stockOps->manualExit(
            $data['product_id'],
            $data['warehouse_id'],
            $data['quantity'],
            $data['unit_cost'] ?? null,
            $request->user()->id,
            $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Sortie de stock enregistrée.',
            'data'    => $mouvement->load(['product', 'warehouse', 'user']),
        ], 201);
    }

    /**
     * POST /api/stock/ajustement — Inventory adjustment.
     */
    public function ajustement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'    => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'  => ['required', 'integer', 'exists:warehouses,id'],
            'new_quantity'  => ['required', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $mouvement = $this->stockOps->adjustInventory(
                $data['product_id'],
                $data['warehouse_id'],
                $data['new_quantity'],
                $request->user()->id,
                $data['notes'] ?? null
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Ajustement d\'inventaire enregistré.',
            'data'    => $mouvement->load(['product', 'warehouse', 'user']),
        ], 201);
    }
}
