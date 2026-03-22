<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private WarehouseRepositoryInterface $warehouses)
    {
    }

    public function index(): JsonResponse
    {
        $data = CacheService::remember(
            CacheService::warehousesKey(),
            CacheService::TTL_LONG,
            fn () => $this->warehouses->all(orderBy: 'wh_title')
        );

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wh_title'  => ['required', 'string', 'max:255'],
            'wh_status' => ['boolean'],
        ]);

        $warehouse = $this->warehouses->create($data);
        CacheService::flushWarehouses();

        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json($warehouse->load(['stocks.product']));
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'wh_title'  => ['sometimes', 'string', 'max:255'],
            'wh_status' => ['sometimes', 'boolean'],
        ]);

        $this->warehouses->update($warehouse, $data);
        CacheService::flushWarehouses();

        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouses->delete($warehouse);
        CacheService::flushWarehouses();

        return response()->json(null, 204);
    }
}
