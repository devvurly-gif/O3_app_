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

    public function getStockLevel(int $productId, int $warehouseId, ?int $variantId = null): float
    {
        $q = $this->model
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        $variantId !== null
            ? $q->where('variant_id', $variantId)
            : $q->whereNull('variant_id');

        return (float) ($q->value('stockLevel') ?? 0);
    }

    public function upsertStock(int $productId, int $warehouseId, array $data, ?int $variantId = null): void
    {
        $q = $this->model
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        $variantId !== null
            ? $q->where('variant_id', $variantId)
            : $q->whereNull('variant_id');

        $record = $q->first();

        if ($record) {
            $record->update($data);
        } else {
            $this->model->create(array_merge($data, [
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'variant_id'   => $variantId,
            ]));
        }
    }
}
