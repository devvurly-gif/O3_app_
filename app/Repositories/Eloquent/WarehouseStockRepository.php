<?php

namespace App\Repositories\Eloquent;

use App\Models\WarehouseHasStock;
use App\Repositories\Contracts\WarehouseStockRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class WarehouseStockRepository extends BaseRepository implements WarehouseStockRepositoryInterface
{
    public function __construct(WarehouseHasStock $model)
    {
        parent::__construct($model);
    }

    public function updateStock(WarehouseHasStock $stock, array $data): Model
    {
        $stock->update($data);
        return $stock->fresh(['warehouse', 'product']);
    }

    public function getStockLevel(int $productId, int $warehouseId): float
    {
        return (float) ($this->model
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('stockLevel') ?? 0);
    }

    public function upsertStock(int $productId, int $warehouseId, array $data): void
    {
        $this->model->updateOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            $data
        );
    }
}
