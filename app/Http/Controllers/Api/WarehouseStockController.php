<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseHasStock;
use App\Repositories\Contracts\WarehouseStockRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseStockController extends Controller
{
    public function __construct(private WarehouseStockRepositoryInterface $stocks)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->stocks->paginate(
                perPage: (int) $request->input('per_page', 20),
                with: ['warehouse', 'product', 'lastUpdatedBy'],
                orderBy: $request->input('sort', 'id'),
                direction: $request->input('order', 'asc'),
                filters: array_filter([
                    'warehouse_id' => $request->warehouse_id,
                    'product_id'   => $request->product_id,
                ])
            )
        );
    }

    public function show(WarehouseHasStock $warehouseHasStock): JsonResponse
    {
        return response()->json(
            $warehouseHasStock->load(['warehouse', 'product', 'lastUpdatedBy'])
        );
    }

    public function update(Request $request, WarehouseHasStock $warehouseHasStock): JsonResponse
    {
        $data = $request->validate([
            'stockLevel' => ['required', 'numeric', 'min:0'],
            'notes'      => ['nullable', 'string'],
        ]);

        $updated = $this->stocks->updateStock($warehouseHasStock, [
            'stockLevel'  => $data['stockLevel'],
            'stockAtTime' => now(),
            'user_id'     => $request->user()->id,
        ]);

        return response()->json($updated);
    }
}
