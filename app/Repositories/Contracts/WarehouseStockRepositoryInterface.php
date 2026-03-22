<?php

namespace App\Repositories\Contracts;

use App\Models\WarehouseHasStock;
use Illuminate\Database\Eloquent\Model;

interface WarehouseStockRepositoryInterface extends BaseRepositoryInterface
{
    public function updateStock(WarehouseHasStock $stock, array $data): Model;

    public function getStockLevel(int $productId, int $warehouseId): float;

    public function upsertStock(int $productId, int $warehouseId, array $data): void;
}
