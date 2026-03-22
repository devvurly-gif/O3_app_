<?php

namespace App\Services;

use App\Models\StockMouvement;
use App\Models\WarehouseHasStock;
use App\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;

class StockOperationService
{
    // ── Transfer ────────────────────────────────────────────────────────

    /**
     * Execute a pending warehouse transfer: decrement source, increment target,
     * log two stock movements (out + in).
     */
    public function executeTransfer(WarehouseTransfer $transfer): WarehouseTransfer
    {
        if (!$transfer->isPending()) {
            throw new \DomainException('Ce transfert ne peut pas être exécuté. Statut : ' . $transfer->status);
        }

        return DB::transaction(function () use ($transfer) {
            $stockOut = $this->getStockLevel($transfer->product_id, $transfer->from_warehouse_id);
            $stockIn  = $this->getStockLevel($transfer->product_id, $transfer->to_warehouse_id);

            $this->upsertStock($transfer->product_id, $transfer->from_warehouse_id, $stockOut - $transfer->quantity, $transfer->user_id);

            StockMouvement::create([
                'product_id'   => $transfer->product_id,
                'warehouse_id' => $transfer->from_warehouse_id,
                'direction'    => 'out',
                'reason'       => 'transfer_out',
                'quantity'     => $transfer->quantity,
                'stock_before' => $stockOut,
                'stock_after'  => $stockOut - $transfer->quantity,
                'user_id'      => $transfer->user_id,
                'notes'        => 'Transfert vers dépôt #' . $transfer->to_warehouse_id,
            ]);

            $this->upsertStock($transfer->product_id, $transfer->to_warehouse_id, $stockIn + $transfer->quantity, $transfer->user_id);

            StockMouvement::create([
                'product_id'   => $transfer->product_id,
                'warehouse_id' => $transfer->to_warehouse_id,
                'direction'    => 'in',
                'reason'       => 'transfer_in',
                'quantity'     => $transfer->quantity,
                'stock_before' => $stockIn,
                'stock_after'  => $stockIn + $transfer->quantity,
                'user_id'      => $transfer->user_id,
                'notes'        => 'Transfert depuis dépôt #' . $transfer->from_warehouse_id,
            ]);

            $transfer->update([
                'status'         => 'completed',
                'transferred_at' => now(),
            ]);

            return $transfer->fresh(['fromWarehouse', 'toWarehouse', 'product', 'user']);
        });
    }

    // ── Manual entry ────────────────────────────────────────────────────

    /**
     * Record a manual stock entry (entrée manuelle).
     */
    public function manualEntry(
        int    $productId,
        int    $warehouseId,
        float  $quantity,
        ?float $unitCost,
        int    $userId,
        ?string $notes = null
    ): StockMouvement {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $userId, $notes) {
            $stockBefore = $this->getStockLevel($productId, $warehouseId);
            $stockAfter  = $stockBefore + $quantity;

            $this->upsertStock($productId, $warehouseId, $stockAfter, $userId);

            return StockMouvement::create([
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'direction'    => 'in',
                'reason'       => 'manual_entry',
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost ?? 0,
                'stock_before' => $stockBefore,
                'stock_after'  => $stockAfter,
                'user_id'      => $userId,
                'notes'        => $notes,
            ]);
        });
    }

    // ── Manual exit ─────────────────────────────────────────────────────

    /**
     * Record a manual stock exit (sortie manuelle).
     */
    public function manualExit(
        int    $productId,
        int    $warehouseId,
        float  $quantity,
        ?float $unitCost,
        int    $userId,
        ?string $notes = null
    ): StockMouvement {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $userId, $notes) {
            $stockBefore = $this->getStockLevel($productId, $warehouseId);

            if ($quantity > $stockBefore) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => "Stock insuffisant ({$stockBefore} disponible, {$quantity} demandé).",
                ]);
            }

            $stockAfter = $stockBefore - $quantity;

            $this->upsertStock($productId, $warehouseId, $stockAfter, $userId);

            return StockMouvement::create([
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'direction'    => 'out',
                'reason'       => 'manual_exit',
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost ?? 0,
                'stock_before' => $stockBefore,
                'stock_after'  => $stockAfter,
                'user_id'      => $userId,
                'notes'        => $notes,
            ]);
        });
    }

    // ── Inventory adjustment ────────────────────────────────────────────

    /**
     * Adjust inventory to a specific target quantity.
     * Automatically determines direction (in/out) based on diff.
     */
    public function adjustInventory(
        int    $productId,
        int    $warehouseId,
        float  $newQuantity,
        int    $userId,
        ?string $notes = null
    ): StockMouvement {
        return DB::transaction(function () use ($productId, $warehouseId, $newQuantity, $userId, $notes) {
            $stockBefore = $this->getStockLevel($productId, $warehouseId);
            $diff        = $newQuantity - $stockBefore;

            if ($diff == 0) {
                throw new \DomainException('Le stock est déjà à cette quantité.');
            }

            $this->upsertStock($productId, $warehouseId, $newQuantity, $userId);

            return StockMouvement::create([
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'direction'    => $diff > 0 ? 'in' : 'out',
                'reason'       => 'inventory_adjustment',
                'quantity'     => abs($diff),
                'stock_before' => $stockBefore,
                'stock_after'  => $newQuantity,
                'user_id'      => $userId,
                'notes'        => $notes ?? sprintf(
                    'Ajustement inventaire : %.2f → %.2f (écart : %+.2f)',
                    $stockBefore,
                    $newQuantity,
                    $diff
                ),
            ]);
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function getStockLevel(int $productId, int $warehouseId): float
    {
        return (float) (WarehouseHasStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('stockLevel') ?? 0);
    }

    private function upsertStock(int $productId, int $warehouseId, float $level, int $userId): void
    {
        WarehouseHasStock::updateOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['stockLevel' => $level, 'stockAtTime' => now(), 'user_id' => $userId]
        );
    }
}
