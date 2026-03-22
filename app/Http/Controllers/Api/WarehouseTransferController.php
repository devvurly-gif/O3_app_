<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseTransfer;
use App\Repositories\Contracts\WarehouseTransferRepositoryInterface;
use App\Services\CacheService;
use App\Services\StockOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseTransferController extends Controller
{
    public function __construct(
        private WarehouseTransferRepositoryInterface $transfers,
        private StockOperationService $stockOps,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->transfers->paginate(
                perPage: (int) $request->input('per_page', 15),
                with: ['fromWarehouse', 'toWarehouse', 'product', 'user'],
                orderBy: $request->input('sort', 'created_at'),
                direction: $request->input('order', 'desc'),
                filters: array_filter([
                    'status' => $request->status,
                ])
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'quantity'          => ['required', 'numeric', 'min:0.01'],
            'notes'             => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status']  = 'pending';

        $transfer = $this->transfers->create($data);
        CacheService::flushProducts();

        return response()->json($transfer->load(['fromWarehouse', 'toWarehouse', 'product']), 201);
    }

    public function show(WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        return response()->json(
            $warehouseTransfer->load(['fromWarehouse', 'toWarehouse', 'product', 'user'])
        );
    }

    /**
     * Execute a pending transfer: moves stock between warehouses and logs movements.
     */
    public function execute(WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        try {
            $transfer = $this->stockOps->executeTransfer($warehouseTransfer);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        CacheService::flushProducts();

        return response()->json([
            'message' => 'Transfert exécuté avec succès.',
            'data'    => $transfer,
        ]);
    }

    /**
     * Cancel a pending transfer.
     */
    public function cancel(WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        if (!$warehouseTransfer->isPending()) {
            return response()->json([
                'message' => 'Seul un transfert en attente peut être annulé.',
            ], 422);
        }

        $this->transfers->update($warehouseTransfer, ['status' => 'cancelled']);

        return response()->json([
            'message' => 'Transfert annulé.',
            'data'    => $warehouseTransfer->fresh(['fromWarehouse', 'toWarehouse', 'product', 'user']),
        ]);
    }

    public function update(Request $request, WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        if (!$warehouseTransfer->isPending()) {
            return response()->json([
                'message' => 'Seul un transfert en attente peut être modifié.',
            ], 422);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $this->transfers->update($warehouseTransfer, $data);

        return response()->json($warehouseTransfer->fresh(['fromWarehouse', 'toWarehouse', 'product', 'user']));
    }

    public function destroy(WarehouseTransfer $warehouseTransfer): JsonResponse
    {
        if (!$warehouseTransfer->isPending()) {
            return response()->json([
                'message' => 'Seul un transfert en attente peut être supprimé.',
            ], 422);
        }

        $this->transfers->delete($warehouseTransfer);

        return response()->json(null, 204);
    }
}
