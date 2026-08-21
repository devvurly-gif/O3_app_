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

    /**
     * Read-only stock level. Use lockStockLevel() instead whenever the value
     * read is about to be written back.
     */
    public function getStockLevel(int $productId, int $warehouseId, ?int $variantId = null): float
    {
        return (float) ($this->scopeToStock($productId, $warehouseId, $variantId)
            ->value('stockLevel') ?? 0);
    }

    /**
     * Same read, holding the row until the surrounding transaction ends.
     *
     * Stock is maintained by read-compute-write, not by an atomic delta, so
     * two concurrent sales of the same article both read the pre-sale level
     * and the second write silently erased the first — one unit sold twice,
     * stock short by one. The negative-stock guards were escapable through
     * the same window.
     *
     * MUST be called inside a transaction, otherwise MySQL releases the lock
     * on the spot and this is just getStockLevel() with extra steps.
     *
     * Note the residual case: when no row exists yet, InnoDB takes a gap lock
     * under REPEATABLE READ, and the unique key on
     * (warehouse_id, product_id, variant_id) catches whatever slips past.
     */
    public function lockStockLevel(int $productId, int $warehouseId, ?int $variantId = null): float
    {
        return (float) ($this->scopeToStock($productId, $warehouseId, $variantId)
            ->lockForUpdate()
            ->value('stockLevel') ?? 0);
    }

    private function scopeToStock(int $productId, int $warehouseId, ?int $variantId)
    {
        $q = $this->model
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        $variantId !== null
            ? $q->where('variant_id', $variantId)
            : $q->whereNull('variant_id');

        return $q;
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
