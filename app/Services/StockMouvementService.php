<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\StockMovementAlert;
use App\Repositories\Contracts\StockMouvementRepositoryInterface;
use App\Repositories\Contracts\WarehouseStockRepositoryInterface;
use Illuminate\Support\Facades\Log;

class StockMouvementService
{
    public function __construct(
        private StockMouvementRepositoryInterface $mouvements,
        private WarehouseStockRepositoryInterface $stocks,
    ) {
    }

    /**
     * Process stock movements for all product lines of a document.
     * Call this AFTER lines have been created.
     */
    public function processDocument(DocumentHeader $document): void
    {
        if (!$document->warehouse_id) {
            return;
        }

        $document->loadMissing('lignes');

        [$direction, $reason, $label] = match ($document->document_type) {
            'DeliveryNote'         => ['out', 'sale_delivery',     'Sortie auto BL '     . $document->reference],
            'ReceiptNotePurchase'  => ['in',  'purchase_receipt',  'Entrée auto BR '     . $document->reference],
            'InvoiceSale'          => ['out', 'sale',              'Sortie facture '     . $document->reference],
            'InvoicePurchase'      => ['in',  'purchase',          'Entrée facture '     . $document->reference],
            'TicketSale'           => ['out', 'pos_sale',           'Sortie POS '         . $document->reference],
            'StockEntry'           => ['in',  'stock_entry',       'Entrée stock '       . $document->reference],
            'StockExit'            => ['out', 'stock_exit',        'Sortie stock '       . $document->reference],
            default                => [null,  null,                null],
        };

        if (!$direction) {
            // StockAdjustment et StockTransfer ont une logique spéciale
            if ($document->document_type === 'StockAdjustmentNote') {
                $this->processAdjustment($document);
            } elseif ($document->document_type === 'StockTransfer') {
                $this->processTransfer($document);
            }
            return;
        }

        foreach ($document->lignes as $ligne) {
            if (!$ligne->product_id) {
                continue;
            }

            $currentStock = $this->stocks->getStockLevel($ligne->product_id, $document->warehouse_id);
            $stockAfter   = $direction === 'in'
                ? $currentStock + $ligne->quantity
                : $currentStock - $ligne->quantity;

            // Negative stock check (skip for adjustments)
            if ($direction === 'out' && $stockAfter < 0) {
                $this->guardNegativeStock($ligne, $currentStock);
            }

            $this->mouvements->create([
                'product_id'         => $ligne->product_id,
                'warehouse_id'       => $document->warehouse_id,
                'document_header_id' => $document->id,
                'document_reference' => $document->reference,
                'document_type'      => $document->document_type,
                'direction'          => $direction,
                'reason'             => $reason,
                'quantity'           => $ligne->quantity,
                'unit_cost'          => $ligne->unit_price,
                'stock_before'       => $currentStock,
                'stock_after'        => $stockAfter,
                'user_id'            => $document->user_id,
                'notes'              => $label,
            ]);

            $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => $document->user_id,
            ]);

            $this->checkLowStockAlert($ligne->product_id, $document->warehouse_id, $stockAfter);
        }

        // Encours: only increment for direct invoices (not BL — BL doesn't create debt)
        if ($direction === 'out' && $document->thirdPartner_id
            && $document->document_type === 'InvoiceSale') {
            $document->loadMissing('footer', 'thirdPartner');
            if ($document->footer && $document->thirdPartner) {
                $document->thirdPartner->increment('encours_actuel', $document->footer->total_ttc);
            }
        }
    }

    public function record(
        DocumentHeader $document,
        DocumentLigne  $ligne,
        string         $reason
    ): void {
        if (!$ligne->product_id || $ligne->line_type !== 'product') return;

        $direction = in_array($reason, [
            'purchase', 'return_in', 'transfer_in', 'adjustment_in', 'initial'
        ]) ? 'in' : 'out';

        $currentStock = $this->stocks->getStockLevel($ligne->product_id, $document->warehouse_id);

        $stockAfter = $direction === 'in'
            ? $currentStock + $ligne->quantity
            : $currentStock - $ligne->quantity;

        // Negative stock check
        if ($direction === 'out' && $stockAfter < 0) {
            $this->guardNegativeStock($ligne, $currentStock);
        }

        $this->mouvements->create([
            'product_id'           => $ligne->product_id,
            'warehouse_id'         => $document->warehouse_id,
            'document_header_id'   => $document->id,
            'document_reference'   => $document->reference,
            'document_type'        => $document->document_type,
            'direction'            => $direction,
            'reason'               => $reason,
            'quantity'             => $ligne->quantity,
            'unit_cost'            => $ligne->unit_price,
            'stock_before'         => $currentStock,
            'stock_after'          => $stockAfter,
            'user_id'              => auth()->id(),
        ]);

        $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
            'stockLevel'  => $stockAfter,
            'stockAtTime' => now(),
            'user_id'     => auth()->id(),
        ]);
    }

    /**
     * Process a StockAdjustment document:
     * each line's quantity is the new target stock → delta = new - current.
     */
    private function processAdjustment(DocumentHeader $document): void
    {
        if (!$document->warehouse_id) return;

        $document->loadMissing('lignes');

        foreach ($document->lignes as $ligne) {
            if (!$ligne->product_id || $ligne->line_type !== 'product') continue;

            $currentStock = $this->stocks->getStockLevel($ligne->product_id, $document->warehouse_id);
            $targetStock  = (float) $ligne->quantity;
            $delta        = $targetStock - $currentStock;

            if ($delta == 0) continue;

            $direction = $delta > 0 ? 'in' : 'out';
            $absQty    = abs($delta);
            $stockAfter = $targetStock;

            $this->mouvements->create([
                'product_id'         => $ligne->product_id,
                'warehouse_id'       => $document->warehouse_id,
                'document_header_id' => $document->id,
                'document_reference' => $document->reference,
                'document_type'      => $document->document_type,
                'direction'          => $direction,
                'reason'             => 'stock_adjustment',
                'quantity'           => $absQty,
                'unit_cost'          => $ligne->unit_price,
                'stock_before'       => $currentStock,
                'stock_after'        => $stockAfter,
                'user_id'            => $document->user_id,
                'notes'              => 'Ajustement inventaire ' . $document->reference,
            ]);

            $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => $document->user_id,
            ]);
        }
    }

    /**
     * Process a StockTransfer document:
     * OUT from warehouse_id, IN to warehouse_dest_id.
     */
    private function processTransfer(DocumentHeader $document): void
    {
        if (!$document->warehouse_id || !$document->warehouse_dest_id) return;

        $document->loadMissing('lignes');

        foreach ($document->lignes as $ligne) {
            if (!$ligne->product_id || $ligne->line_type !== 'product') continue;

            $stockOut = $this->stocks->getStockLevel($ligne->product_id, $document->warehouse_id);
            $stockIn  = $this->stocks->getStockLevel($ligne->product_id, $document->warehouse_dest_id);

            // Negative stock check on source warehouse
            if ($stockOut - $ligne->quantity < 0) {
                $this->guardNegativeStock($ligne, $stockOut);
            }

            $this->mouvements->create([
                'product_id'         => $ligne->product_id,
                'warehouse_id'       => $document->warehouse_id,
                'document_header_id' => $document->id,
                'document_reference' => $document->reference,
                'document_type'      => $document->document_type,
                'direction'          => 'out',
                'reason'             => 'stock_transfer_out',
                'quantity'           => $ligne->quantity,
                'unit_cost'          => $ligne->unit_price,
                'stock_before'       => $stockOut,
                'stock_after'        => $stockOut - $ligne->quantity,
                'user_id'            => $document->user_id,
                'notes'              => 'Transfert sortie ' . $document->reference,
            ]);

            $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
                'stockLevel'  => $stockOut - $ligne->quantity,
                'stockAtTime' => now(),
                'user_id'     => $document->user_id,
            ]);

            $this->mouvements->create([
                'product_id'         => $ligne->product_id,
                'warehouse_id'       => $document->warehouse_dest_id,
                'document_header_id' => $document->id,
                'document_reference' => $document->reference,
                'document_type'      => $document->document_type,
                'direction'          => 'in',
                'reason'             => 'stock_transfer_in',
                'quantity'           => $ligne->quantity,
                'unit_cost'          => $ligne->unit_price,
                'stock_before'       => $stockIn,
                'stock_after'        => $stockIn + $ligne->quantity,
                'user_id'            => $document->user_id,
                'notes'              => 'Transfert entrée ' . $document->reference,
            ]);

            $this->stocks->upsertStock($ligne->product_id, $document->warehouse_dest_id, [
                'stockLevel'  => $stockIn + $ligne->quantity,
                'stockAtTime' => now(),
                'user_id'     => $document->user_id,
            ]);
        }
    }

    public function recordTransfer(
        int    $fromWarehouseId,
        int    $toWarehouseId,
        int    $productId,
        float  $quantity,
        int    $userId
    ): void {
        $stockOut = $this->stocks->getStockLevel($productId, $fromWarehouseId);

        // Negative stock check on source warehouse
        if ($stockOut - $quantity < 0) {
            $this->guardNegativeStockRaw($productId, $quantity, $stockOut);
        }

        $this->mouvements->create([
            'product_id'   => $productId,
            'warehouse_id' => $fromWarehouseId,
            'direction'    => 'out',
            'reason'       => 'transfer_out',
            'quantity'     => $quantity,
            'stock_before' => $stockOut,
            'stock_after'  => $stockOut - $quantity,
            'user_id'      => $userId,
        ]);

        $this->stocks->upsertStock($productId, $fromWarehouseId, [
            'stockLevel'  => $stockOut - $quantity,
            'stockAtTime' => now(),
            'user_id'     => $userId,
        ]);

        $stockIn = $this->stocks->getStockLevel($productId, $toWarehouseId);

        $this->mouvements->create([
            'product_id'   => $productId,
            'warehouse_id' => $toWarehouseId,
            'direction'    => 'in',
            'reason'       => 'transfer_in',
            'quantity'     => $quantity,
            'stock_before' => $stockIn,
            'stock_after'  => $stockIn + $quantity,
            'user_id'      => $userId,
        ]);

        $this->stocks->upsertStock($productId, $toWarehouseId, [
            'stockLevel'  => $stockIn + $quantity,
            'stockAtTime' => now(),
            'user_id'     => $userId,
        ]);
    }

    /**
     * Reverse all stock movements of a document (used on BL cancellation).
     * Creates inverse movements so the audit trail is preserved.
     */
    public function reverseDocument(DocumentHeader $document): void
    {
        if (!$document->warehouse_id) {
            return;
        }

        $movements = $this->mouvements->forDocument($document->id);

        foreach ($movements as $mouvement) {
            $reverseDirection = $mouvement->direction === 'out' ? 'in' : 'out';
            $currentStock     = $this->stocks->getStockLevel($mouvement->product_id, $mouvement->warehouse_id);
            $stockAfter       = $reverseDirection === 'in'
                ? $currentStock + $mouvement->quantity
                : $currentStock - $mouvement->quantity;

            $this->mouvements->create([
                'product_id'         => $mouvement->product_id,
                'warehouse_id'       => $mouvement->warehouse_id,
                'document_header_id' => $document->id,
                'document_reference' => $document->reference,
                'document_type'      => $document->document_type,
                'direction'          => $reverseDirection,
                'reason'             => 'cancellation',
                'quantity'           => $mouvement->quantity,
                'unit_cost'          => $mouvement->unit_cost,
                'stock_before'       => $currentStock,
                'stock_after'        => $stockAfter,
                'user_id'            => auth()->id(),
                'notes'              => 'Annulation ' . $document->reference,
            ]);

            $this->stocks->upsertStock($mouvement->product_id, $mouvement->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => auth()->id(),
            ]);
        }
    }

    /**
     * Throw if negative stock is not allowed (uses a DocumentLigne for context).
     */
    private function guardNegativeStock(DocumentLigne $ligne, float $currentStock): void
    {
        if (Setting::get('stock', 'autoriser_stock_negatif', 'false') === 'true') {
            return;
        }

        $ligne->loadMissing('product');

        throw new InsufficientStockException(
            productName: $ligne->product?->p_title ?? 'Produit #' . $ligne->product_id,
            productId: $ligne->product_id,
            requested: $ligne->quantity,
            available: $currentStock,
        );
    }

    /**
     * Throw if negative stock is not allowed (raw values, no ligne context).
     */
    private function guardNegativeStockRaw(int $productId, float $requested, float $available): void
    {
        if (Setting::get('stock', 'autoriser_stock_negatif', 'false') === 'true') {
            return;
        }

        $product = \App\Models\Product::find($productId);

        throw new InsufficientStockException(
            productName: $product?->p_title ?? 'Produit #' . $productId,
            productId: $productId,
            requested: $requested,
            available: $available,
        );
    }

    /**
     * Send a StockMovementAlert when stock drops to 5 or below.
     */
    private function checkLowStockAlert(int $productId, int $warehouseId, float $stockAfter): void
    {
        $threshold = (int) Setting::get('stock', 'seuil_alerte_stock', '5');

        if ($stockAfter > $threshold || $stockAfter < 0) {
            return;
        }

        try {
            $product   = Product::find($productId);
            $warehouse = Warehouse::find($warehouseId);

            if (!$product || !$warehouse) {
                return;
            }

            $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager', 'warehouse']))
                ->where('is_active', true)
                ->get();

            $notification = new StockMovementAlert($product, $warehouse, $stockAfter);

            foreach ($recipients as $user) {
                $user->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::warning("StockMovementAlert failed: {$e->getMessage()}");
        }
    }
}
