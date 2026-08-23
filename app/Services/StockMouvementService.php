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
     *
     * @param bool $pending  If true, movements are recorded as 'pending'
     *                       and warehouse stock is NOT updated yet.
     */
    public function processDocument(DocumentHeader $document, bool $pending = false): void
    {
        Log::info('StockMouvementService.processDocument started', [
            'document_id' => $document->id,
            'document_ref' => $document->reference,
            'document_type' => $document->document_type,
            'warehouse_id' => $document->warehouse_id,
            'pending' => $pending,
        ]);

        if (!$document->warehouse_id) {
            Log::warning('Document has no warehouse_id, skipping processDocument', [
                'document_id' => $document->id,
            ]);
            return;
        }

        $document->loadMissing('lignes');

        Log::info('Document lignes loaded', [
            'document_id' => $document->id,
            'lignes_count' => $document->lignes->count(),
        ]);

        [$direction, $reason, $label] = match ($document->document_type) {
            'DeliveryNote'         => ['out', 'sale_delivery',     'Sortie auto BL '     . $document->reference],
            'ReceiptNotePurchase'  => ['in',  'purchase_receipt',  'Entrée auto BR '     . $document->reference],
            'InvoiceSale'          => ['out', 'sale',              'Sortie facture '     . $document->reference],
            'InvoicePurchase'      => ['in',  'purchase',          'Entrée facture '     . $document->reference],
            'TicketSale'           => ['out', 'pos_sale',           'Sortie POS '         . $document->reference],
            'StockEntry'           => ['in',  'stock_entry',       'Entrée stock '       . $document->reference],
            'StockExit'            => ['out', 'stock_exit',        'Sortie stock '       . $document->reference],
            'ReturnSale'           => ['in',  'return_in',         'Retour client '      . $document->reference],
            'ReturnPurchase'       => ['out', 'return_out',        'Retour fournisseur ' . $document->reference],
            default                => [null,  null,                null],
        };

        if (!$direction) {
            Log::info('No direction matched for document type', [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
            ]);
            if ($document->document_type === 'StockAdjustmentNote') {
                $this->processAdjustment($document);
            } elseif ($document->document_type === 'StockTransfer') {
                $this->processTransfer($document);
            }
            return;
        }

        $status = $pending ? 'pending' : 'applied';

        Log::info('Starting movement creation loop', [
            'document_id' => $document->id,
            'status' => $status,
            'direction' => $direction,
            'lignes_count' => count($document->lignes),
        ]);

        foreach ($document->lignes as $ligne) {
            Log::info('Processing ligne', [
                'ligne_id' => $ligne->id,
                'product_id' => $ligne->product_id,
                'quantity' => $ligne->quantity,
            ]);

            if (!$ligne->product_id) {
                Log::warning('Ligne has no product_id, skipping', ['ligne_id' => $ligne->id]);
                continue;
            }

            $variantId    = $ligne->variant_id ?? null;
            $currentStock = $this->stocks->lockStockLevel($ligne->product_id, $document->warehouse_id, $variantId);
            $stockAfter   = $direction === 'in'
                ? $currentStock + $ligne->quantity
                : $currentStock - $ligne->quantity;

            Log::info('Stock calculation', [
                'product_id' => $ligne->product_id,
                'current_stock' => $currentStock,
                'quantity' => $ligne->quantity,
                'stock_after' => $stockAfter,
            ]);

            // Negative stock check (only when applying immediately)
            if (!$pending && $direction === 'out' && $stockAfter < 0) {
                $this->guardNegativeStock($ligne, $currentStock);
            }

            try {
                $this->mouvements->create([
                    'product_id'         => $ligne->product_id,
                    'variant_id'         => $variantId,
                    'warehouse_id'       => $document->warehouse_id,
                    'document_header_id' => $document->id,
                    'document_reference' => $document->reference,
                    'document_type'      => $document->document_type,
                    'direction'          => $direction,
                    'reason'             => $reason,
                    'quantity'           => $ligne->quantity,
                    'unit_cost'          => $ligne->unit_price,
                    'stock_before'       => $currentStock,
                    'stock_after'        => $pending ? $currentStock : $stockAfter,
                    'user_id'            => $document->user_id,
                    'notes'              => $label,
                    'status'             => $status,
                ]);
                Log::info('Movement created successfully', ['product_id' => $ligne->product_id]);
            } catch (\Exception $e) {
                Log::error('Error creating movement', [
                    'product_id' => $ligne->product_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // Only update warehouse stock when not pending
            if (!$pending) {
                $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
                    'stockLevel'  => $stockAfter,
                    'stockAtTime' => now(),
                    'user_id'     => $document->user_id,
                ], $variantId);

                $this->checkLowStockAlert($ligne->product_id, $document->warehouse_id, $stockAfter);
            }
        }

        Log::info('Movement creation loop completed', ['document_id' => $document->id]);

        // Encours: recalculate authoritatively from source data
        // (covers InvoiceSale here; BL / return / payment paths trigger their own recalc)
        if (!$pending && $direction === 'out' && $document->thirdPartner_id
            && $document->document_type === 'InvoiceSale') {
            $document->loadMissing('footer', 'thirdPartner');
            if ($document->footer && $document->thirdPartner) {
                $document->thirdPartner->recalculateEncours();
            }
        }
    }

    /**
     * Apply pending movements of a document: update warehouse stock and mark as 'applied'.
     */
    public function applyDocumentMovements(DocumentHeader $document): void
    {
        $movements = $this->mouvements->forDocumentByStatus($document->id, 'pending');

        Log::warning('★★★ APPLY_MOVEMENTS CRITICAL ★★★', [
            'document_id' => $document->id,
            'document_ref' => $document->reference,
            'document_type' => $document->document_type,
            'pending_movements_count' => $movements->count(),
            'total_movements' => $this->mouvements->forDocument($document->id)->count(),
            'all_statuses' => $this->mouvements->forDocument($document->id)->pluck('status')->toArray(),
        ]);

        foreach ($movements as $mouvement) {
            $currentStock = $this->stocks->lockStockLevel($mouvement->product_id, $mouvement->warehouse_id);
            $stockAfter   = $mouvement->direction === 'in'
                ? $currentStock + $mouvement->quantity
                : $currentStock - $mouvement->quantity;

            // Negative stock check
            if ($mouvement->direction === 'out' && $stockAfter < 0) {
                $this->guardNegativeStockRaw($mouvement->product_id, (float) $mouvement->quantity, $currentStock);
            }

            // Update the movement record with real stock values
            $mouvement->update([
                'stock_before' => $currentStock,
                'stock_after'  => $stockAfter,
                'status'       => 'applied',
            ]);

            Log::info('Movement applied', [
                'product_id' => $mouvement->product_id,
                'quantity' => $mouvement->quantity,
                'stock_before' => $currentStock,
                'stock_after' => $stockAfter,
            ]);

            $this->stocks->upsertStock($mouvement->product_id, $mouvement->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => auth()->id(),
            ]);

            $this->checkLowStockAlert($mouvement->product_id, $mouvement->warehouse_id, $stockAfter);
        }

        Log::info('StockMouvementService.applyDocumentMovements completed', [
            'document_id' => $document->id,
            'movements_applied' => $movements->count(),
        ]);
    }

    /**
     * Cancel pending movements of a document (no stock reversal needed).
     * For already-applied movements, create reverse entries.
     */
    public function cancelDocumentMovements(DocumentHeader $document): void
    {
        // 1. Cancel any pending movements (simple — no stock was affected)
        $this->mouvements->updateStatusForDocument($document->id, 'pending', 'cancelled');

        // 2. Reverse any applied movements
        $appliedMovements = $this->mouvements->forDocumentByStatus($document->id, 'applied');

        foreach ($appliedMovements as $mouvement) {
            $reverseDirection = $mouvement->direction === 'out' ? 'in' : 'out';
            $currentStock     = $this->stocks->lockStockLevel($mouvement->product_id, $mouvement->warehouse_id);
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
                'status'             => 'applied',
            ]);

            // Mark original as cancelled
            $mouvement->update(['status' => 'cancelled']);

            $this->stocks->upsertStock($mouvement->product_id, $mouvement->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => auth()->id(),
            ]);
        }
    }

    /**
     * Reverse all stock movements of a document (legacy — kept for compatibility).
     */
    public function reverseDocument(DocumentHeader $document): void
    {
        $this->cancelDocumentMovements($document);
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

        $currentStock = $this->stocks->lockStockLevel($ligne->product_id, $document->warehouse_id);

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
            'status'               => 'applied',
        ]);

        $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
            'stockLevel'  => $stockAfter,
            'stockAtTime' => now(),
            'user_id'     => auth()->id(),
        ]);
    }

    /**
     * Process a StockAdjustment document.
     */
    private function processAdjustment(DocumentHeader $document): void
    {
        if (!$document->warehouse_id) return;

        $document->loadMissing('lignes');

        foreach ($document->lignes as $ligne) {
            if (!$ligne->product_id || $ligne->line_type !== 'product') continue;

            $variantId    = $ligne->variant_id ?? null;
            $currentStock = $this->stocks->lockStockLevel($ligne->product_id, $document->warehouse_id, $variantId);
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
                'variant_id'         => $variantId,
                'notes'              => 'Ajustement inventaire ' . $document->reference,
                'status'             => 'applied',
            ]);

            $this->stocks->upsertStock($ligne->product_id, $document->warehouse_id, [
                'stockLevel'  => $stockAfter,
                'stockAtTime' => now(),
                'user_id'     => $document->user_id,
            ], $variantId);
        }
    }

    /**
     * Process a StockTransfer document.
     */
    private function processTransfer(DocumentHeader $document): void
    {
        if (!$document->warehouse_id || !$document->warehouse_dest_id) return;

        $document->loadMissing('lignes');

        foreach ($document->lignes as $ligne) {
            if (!$ligne->product_id || $ligne->line_type !== 'product') continue;

            $stockOut = $this->stocks->lockStockLevel($ligne->product_id, $document->warehouse_id);
            $stockIn  = $this->stocks->lockStockLevel($ligne->product_id, $document->warehouse_dest_id);

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
                'status'             => 'applied',
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
                'status'             => 'applied',
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
        $stockOut = $this->stocks->lockStockLevel($productId, $fromWarehouseId);

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
            'status'       => 'applied',
        ]);

        $this->stocks->upsertStock($productId, $fromWarehouseId, [
            'stockLevel'  => $stockOut - $quantity,
            'stockAtTime' => now(),
            'user_id'     => $userId,
        ]);

        $stockIn = $this->stocks->lockStockLevel($productId, $toWarehouseId);

        $this->mouvements->create([
            'product_id'   => $productId,
            'warehouse_id' => $toWarehouseId,
            'direction'    => 'in',
            'reason'       => 'transfer_in',
            'quantity'     => $quantity,
            'stock_before' => $stockIn,
            'stock_after'  => $stockIn + $quantity,
            'user_id'      => $userId,
            'status'       => 'applied',
        ]);

        $this->stocks->upsertStock($productId, $toWarehouseId, [
            'stockLevel'  => $stockIn + $quantity,
            'stockAtTime' => now(),
            'user_id'     => $userId,
        ]);
    }

    private function guardNegativeStock(DocumentLigne $ligne, float $currentStock): void
    {
        if (Setting::get('stock', 'autoriser_stock_negatif', 'false') === 'true') {
            return;
        }

        $ligne->loadMissing('product');

        throw new InsufficientStockException(
            productName: $ligne->product?->p_title ?? 'Produit #' . $ligne->product_id,
            productId: $ligne->product_id,
            requested: (float) $ligne->quantity,
            available: $currentStock,
        );
    }

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
