<?php

namespace App\Repositories\Contracts;

use App\Models\WarehouseHasStock;
use Illuminate\Database\Eloquent\Model;

interface WarehouseStockRepositoryInterface extends BaseRepositoryInterface
{
    public function updateStock(WarehouseHasStock $stock, array $data): Model;

    public function getStockLevel(int $productId, int $warehouseId, ?int $variantId = null): float;

    public function upsertStock(int $productId, int $warehouseId, array $data, ?int $variantId = null): void;
}
