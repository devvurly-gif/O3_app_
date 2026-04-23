<?php

namespace App\Services;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\DocumentFooterRepositoryInterface;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use App\Repositories\Contracts\PosSessionRepositoryInterface;
use App\Repositories\Contracts\WarehouseStockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(
        private PosSessionRepositoryInterface $sessions,
        private DocumentHeaderRepositoryInterface $documents,
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentLigneRepositoryInterface $lignes,
        private DocumentFooterRepositoryInterface $footers,
        private DocumentIncrementorService $incrementorService,
        private StockMouvementService $stockService,
        private WarehouseStockRepositoryInterface $stocks,
    ) {
    }

    /**
     * Open a new POS session.
     */
    public function openSession(int $terminalId, int $userId, float $openingCash): PosSession
    {
        $terminal = PosTerminal::findOrFail($terminalId);

        if (!$terminal->is_active) {
            abort(422, 'Ce terminal est désactivé.');
        }

        // Check no open session on this terminal
        $existing = PosSession::where('pos_terminal_id', $terminalId)
            ->whereNull('closed_at')
            ->first();

        if ($existing) {
            // Same user → let them resume
            if ($existing->user_id === $userId) {
                return $existing->load('terminal.warehouse');
            }
            abort(422, 'Une session est déjà ouverte sur ce terminal par un autre utilisateur.');
        }

        return PosSession::create([
            'pos_terminal_id' => $terminalId,
            'user_id'         => $userId,
            'opened_at'       => now(),
            'opening_cash'    => $openingCash,
        ]);
    }

    /**
     * Close an existing session.
     */
    public function closeSession(PosSession $session, float $closingCash, ?string $notes = null): PosSession
    {
        if (!$session->isOpen()) {
            abort(422, 'Cette session est déjà fermée.');
        }

        // Calculate expected cash: opening + cash payments during session
        $cashPayments = Payment::whereHas('document', function ($q) use ($session) {
            $q->where('pos_session_id', $session->id);
        })->where('method', 'cash')->sum('amount');

        $expectedCash = $session->opening_cash + $cashPayments;

        $session->update([
            'closed_at'       => now(),
            'closing_cash'    => $closingCash,
            'expected_cash'   => $expectedCash,
            'cash_difference' => $closingCash - $expectedCash,
            'notes'           => $notes,
        ]);

        return $session->fresh();
    }

    /**
     * Create a POS ticket (TicketSale document + lines + footer + payments + stock).
     */
    public function createTicket(
        PosSession $session,
        array $items,
        array $payments,
        ?int $customerId = null
    ): DocumentHeader {
        return DB::transaction(function () use ($session, $items, $payments, $customerId) {
            $session->loadMissing('terminal');
            $warehouseId = $session->terminal->warehouse_id;

            // Default to "Client Comptoir" if no customer selected
            if (!$customerId) {
                $comptoir = ThirdPartner::where('tp_code', 'CLIENT-COMPTOIR')->first();
                $customerId = $comptoir?->id;
            }

            // Determine if this is a credit/encours sale.
            // Credit sales are stored as DeliveryNote (BL is just the French
            // label); using 'DeliveryNote' lets us reuse the existing
            // DeliveryNote incrementor and stays consistent with the rest
            // of the codebase (voidTicket queries document_type = 'DeliveryNote',
            // and DocumentVenteController maps DeliveryNote => 'BL' for UI).
            $isCreditSale = collect($payments)->some(fn ($p) => $p['method'] === 'credit');
            $documentType = $isCreditSale ? 'DeliveryNote' : 'TicketSale';
            $documentTitle = $isCreditSale ? 'Bon de Livraison (POS)' : 'Ticket POS';

            // Find the appropriate incrementor
            $incrementor = $this->incrementors->findByModel($documentType);
            if (!$incrementor) {
                $label = $isCreditSale ? 'BL' : $documentType;
                abort(422, "Aucun numéroteur configuré pour les documents POS ({$label}).");
            }

            $reference = $this->incrementorService->formatReference(
                $incrementor->template,
                $incrementor->nextTrick
            );
            $incrementor->increment('nextTrick');

            // Create document header
            /** @var DocumentHeader $document */
            $document = $this->documents->create([
                'document_incrementor_id' => $incrementor->id,
                'reference'               => $reference,
                'document_type'           => $documentType,
                'document_title'          => $documentTitle,
                'thirdPartner_id'         => $customerId,
                'company_role'            => 'seller',
                'user_id'                 => $session->user_id,
                'warehouse_id'            => $warehouseId,
                'pos_session_id'          => $session->id,
                'status'                  => 'confirmed',
                'issued_at'               => now(),
            ]);

            // Create lines
            $totalHt = 0;
            $totalTax = 0;

            foreach ($items as $i => $item) {
                $lineHt  = $item['quantity'] * $item['unit_price'] * (1 - ($item['discount_percent'] ?? 0) / 100);
                $lineTax = $lineHt * ($item['tax_percent'] ?? 0) / 100;
                $totalHt  += $lineHt;
                $totalTax += $lineTax;

                $this->lignes->createForDocument($document, [
                    'sort_order'       => $i + 1,
                    'product_id'       => $item['product_id'],
                    'line_type'        => 'product',
                    'designation'      => $item['designation'] ?? '',
                    'reference'        => $item['reference'] ?? null,
                    'quantity'         => $item['quantity'],
                    'unit'             => $item['unit'] ?? 'pcs',
                    'unit_price'       => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'tax_percent'      => $item['tax_percent'] ?? 0,
                ]);
            }

            $totalTtc = $totalHt + $totalTax;

            // ── Credit payment logic ─────────────────────────────────
            $paidAmount = 0;
            $creditAmount = 0;

            foreach ($payments as $payment) {
                if ($payment['method'] === 'credit') {
                    $creditAmount += $payment['amount'];
                } else {
                    $paidAmount += $payment['amount'];
                }
            }

            // Validate credit payment requirements
            if ($creditAmount > 0) {
                if (!$customerId) {
                    abort(422, 'Un client doit être sélectionné pour un paiement en compte.');
                }

                $customer = ThirdPartner::findOrFail($customerId);

                if (!$customer->isEnCompte()) {
                    abort(422, 'Ce client n\'a pas de compte "en compte". Seuls les clients en compte peuvent payer à crédit.');
                }

                if ($customer->seuil_credit > 0 && ($customer->encours_actuel + $creditAmount) > $customer->seuil_credit) {
                    abort(422, sprintf(
                        'Plafond crédit dépassé. Encours actuel: %.2f MAD, Plafond: %.2f MAD, Montant crédit demandé: %.2f MAD.',
                        $customer->encours_actuel,
                        $customer->seuil_credit,
                        $creditAmount
                    ));
                }
            }

            // Create footer
            $this->footers->upsertForDocument($document, [
                'total_ht'       => $totalHt,
                'total_discount' => 0,
                'total_tax'      => $totalTax,
                'total_ttc'      => $totalTtc,
                'amount_paid'    => $paidAmount,
                'amount_due'     => $creditAmount,
            ]);

            // Process stock movements
            $document->load('lignes');
            $this->stockService->processDocument($document);

            // Create payments (skip notifications for POS)
            $oldSkip = Payment::$skipNotification;
            Payment::$skipNotification = true;

            try {
                foreach ($payments as $payment) {
                    Payment::create([
                        'payment_code'       => 'POS-' . strtoupper(uniqid()),
                        'document_header_id' => $document->id,
                        'amount'             => $payment['amount'],
                        'method'             => $payment['method'],
                        'paid_at'            => now(),
                        'reference'          => $payment['reference'] ?? null,
                        'user_id'            => $session->user_id,
                        'notes'              => $payment['method'] === 'credit'
                            ? 'Paiement en compte (crédit)'
                            : 'Paiement POS',
                    ]);
                }
            } finally {
                Payment::$skipNotification = $oldSkip;
            }

            // Update customer encours if credit was used (recalculate from source data)
            if ($creditAmount > 0 && $customerId) {
                ThirdPartner::find($customerId)?->recalculateEncours();
            }

            // Set document status
            $status = $creditAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
            $document->update(['status' => $status]);

            return $document->load(['lignes', 'footer', 'payments']);
        });
    }

    /**
     * Return (retour) a POS document. Dispatches by document type:
     *   - TicketSale   → reverse stock & cancel (voidTicket).
     *   - DeliveryNote → create a ReturnSale (BR) linked to the BL,
     *     stock moves IN, customer encours is recalculated.
     * Any other type is rejected so the POS UI can't accidentally
     * mutate sales-module documents.
     */
    public function returnTicket(DocumentHeader $document): DocumentHeader
    {
        return match ($document->document_type) {
            'TicketSale'   => $this->voidTicket($document),
            'DeliveryNote' => $this->createReturnSaleFromDeliveryNote($document),
            default        => abort(422, 'Type de document non pris en charge pour un retour POS.'),
        };
    }

    /**
     * Void a ticket — cancel document, reverse stock, delete payments.
     */
    public function voidTicket(DocumentHeader $ticket): DocumentHeader
    {
        if ($ticket->document_type !== 'TicketSale') {
            abort(422, 'Ce document n\'est pas un ticket POS.');
        }

        if ($ticket->status === 'cancelled') {
            abort(422, 'Ce ticket est déjà annulé.');
        }

        return DB::transaction(function () use ($ticket) {
            // Reverse stock
            $ticket->loadMissing(['lignes', 'stockMouvements']);

            foreach ($ticket->stockMouvements as $mouvement) {
                // Create reverse movement
                $currentStock = $this->stocks->getStockLevel(
                    $mouvement->product_id,
                    $mouvement->warehouse_id
                );

                $reverseDirection = $mouvement->direction === 'out' ? 'in' : 'out';
                $stockAfter = $reverseDirection === 'in'
                    ? $currentStock + $mouvement->quantity
                    : $currentStock - $mouvement->quantity;

                \App\Models\StockMouvement::create([
                    'product_id'         => $mouvement->product_id,
                    'warehouse_id'       => $mouvement->warehouse_id,
                    'document_header_id' => $ticket->id,
                    'document_reference' => $ticket->reference,
                    'document_type'      => 'TicketSale',
                    'direction'          => $reverseDirection,
                    'reason'             => 'pos_void',
                    'quantity'           => $mouvement->quantity,
                    'unit_cost'          => $mouvement->unit_cost,
                    'stock_before'       => $currentStock,
                    'stock_after'        => $stockAfter,
                    'user_id'            => auth()->id(),
                    'notes'              => 'Annulation ticket ' . $ticket->reference,
                ]);

                $this->stocks->upsertStock($mouvement->product_id, $mouvement->warehouse_id, [
                    'stockLevel'  => $stockAfter,
                    'stockAtTime' => now(),
                    'user_id'     => auth()->id(),
                ]);
            }

            // Delete payments (soft: skip notification reversal, just delete)
            $oldSkip = Payment::$skipNotification;
            Payment::$skipNotification = true;
            try {
                $ticket->payments()->delete();
            } finally {
                Payment::$skipNotification = $oldSkip;
            }

            // Update footer
            if ($ticket->footer) {
                $ticket->footer->update([
                    'amount_paid' => 0,
                    'amount_due'  => 0,
                ]);
            }

            // Reverse customer encours: ticket is about to be cancelled, recalc
            // after the status change below. Track whether we need to recalc.
            $needsEncoursRecalc = $ticket->thirdPartner_id
                && $ticket->payments()->where('method', 'credit')->exists();

            // Cancel the document
            $ticket->update(['status' => 'cancelled']);

            // Cancel associated BL (DeliveryNote) if exists
            $associatedBl = DocumentHeader::where('parent_id', $ticket->id)
                ->where('document_type', 'DeliveryNote')
                ->where('status', '!=', 'cancelled')
                ->where('status', '!=', 'converted')
                ->first();

            if ($associatedBl) {
                $associatedBl->update(['status' => 'cancelled']);
                if ($associatedBl->footer) {
                    $associatedBl->footer->update(['amount_paid' => 0, 'amount_due' => 0]);
                }
            }

            if ($needsEncoursRecalc) {
                ThirdPartner::find($ticket->thirdPartner_id)?->recalculateEncours();
            }

            return $ticket->fresh(['lignes', 'footer']);
        });
    }

    /**
     * Retour client (BL → BR): creates a ReturnSale linked to the given
     * DeliveryNote, restores stock (direction IN via StockMouvementService)
     * and refreshes the customer's encours so credit is released.
     *
     * The parent BL stays as-is; the BR is the counter-document that
     * offsets it (matching the sales-module retour_client behaviour).
     */
    private function createReturnSaleFromDeliveryNote(DocumentHeader $bl): DocumentHeader
    {
        if ($bl->document_type !== 'DeliveryNote') {
            abort(422, 'Seul un BL peut être converti en BR.');
        }

        if ($bl->status === 'cancelled') {
            abort(422, 'Ce BL est annulé et ne peut pas faire l\'objet d\'un retour.');
        }

        $brIncrementor = $this->incrementors->findByModel('ReturnSale');
        if (!$brIncrementor) {
            abort(422, 'Aucun numéroteur configuré pour les Bons de Retour (BR).');
        }

        $bl->loadMissing(['lignes', 'footer']);

        return DB::transaction(function () use ($bl, $brIncrementor) {
            $now = now();

            $reference = $this->incrementorService->formatReference(
                $brIncrementor->template,
                $brIncrementor->nextTrick
            );
            $brIncrementor->increment('nextTrick');

            /** @var DocumentHeader $br */
            $br = $this->documents->create([
                'document_incrementor_id' => $brIncrementor->id,
                'reference'               => $reference,
                'document_type'           => 'ReturnSale',
                'document_title'          => 'Bon de Retour Client (POS)',
                'parent_id'               => $bl->id,
                'thirdPartner_id'         => $bl->thirdPartner_id,
                'company_role'            => $bl->company_role,
                'user_id'                 => auth()->id() ?? $bl->user_id,
                'warehouse_id'            => $bl->warehouse_id,
                'pos_session_id'          => $bl->pos_session_id,
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => 'Retour POS du BL ' . $bl->reference,
            ]);

            // Copy all lines from the BL onto the BR (full return).
            $totalHt = 0;
            $totalTax = 0;
            foreach ($bl->lignes as $i => $ligne) {
                $lineHt  = $ligne->quantity * $ligne->unit_price * (1 - ($ligne->discount_percent ?? 0) / 100);
                $lineTax = $lineHt * (($ligne->tax_percent ?? 0) / 100);
                $totalHt  += $lineHt;
                $totalTax += $lineTax;

                $this->lignes->createForDocument($br, [
                    'sort_order'       => $i + 1,
                    'product_id'       => $ligne->product_id,
                    'line_type'        => $ligne->line_type ?? 'product',
                    'designation'      => $ligne->designation,
                    'reference'        => $ligne->reference,
                    'quantity'         => $ligne->quantity,
                    'unit'             => $ligne->unit ?? 'pcs',
                    'unit_price'       => $ligne->unit_price,
                    'discount_percent' => $ligne->discount_percent ?? 0,
                    'tax_percent'      => $ligne->tax_percent ?? 0,
                ]);
            }

            $this->footers->upsertForDocument($br, [
                'total_ht'       => $totalHt,
                'total_discount' => 0,
                'total_tax'      => $totalTax,
                'total_ttc'      => $totalHt + $totalTax,
                'amount_paid'    => 0,
                'amount_due'     => 0,
            ]);

            // Stock: ReturnSale direction = IN (goods come back).
            $br->load('lignes');
            $this->stockService->processDocument($br);

            // Refresh the customer's encours so the BL amount is released.
            if ($bl->thirdPartner_id) {
                ThirdPartner::find($bl->thirdPartner_id)?->recalculateEncours();
            }

            return $br->fresh(['lignes', 'footer']);
        });
    }

    /**
     * Search products for POS with stock info.
     */
    public function searchProducts(string $query, int $warehouseId, ?int $categoryId = null, int $limit = 50, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $q = Product::where('p_status', true)
            ->where(function ($builder) use ($query) {
                $builder->where('p_title', 'like', "%{$query}%")
                    ->orWhere('p_sku', 'like', "%{$query}%")
                    ->orWhere('p_ean13', $query)
                    ->orWhere('p_code', 'like', "%{$query}%");
            });

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }

        if ($minPrice !== null || $maxPrice !== null) {
            $min = $minPrice ?? 0;
            $max = $maxPrice ?? PHP_FLOAT_MAX;
            $q->whereBetween('p_salePrice', [$min, $max]);
        }

        $products = $q->with(['category', 'brand', 'primaryImage'])
            ->limit($limit)
            ->get();

        // Attach stock levels
        return $products->map(function (Product $product) use ($warehouseId) {
            $arr = $product->toArray();
            $arr['stock'] = $this->stocks->getStockLevel($product->id, $warehouseId);
            return $arr;
        })->toArray();
    }

    /**
     * Create a DeliveryNote (BL) linked to an "en compte" ticket.
     * The BL can later be converted to an Invoice at end of month.
     * Stock is NOT deducted again (already handled by TicketSale).
     */
    private function createDeliveryNoteFromTicket(
        DocumentHeader $ticket,
        PosSession $session,
        int $warehouseId,
        int $customerId,
        array $items,
        float $totalHt,
        float $totalTax,
        float $totalTtc,
        float $creditAmount,
    ): DocumentHeader {
        // Find the DeliveryNote incrementor
        $blIncrementor = $this->incrementors->findByModel('DeliveryNote');
        if (!$blIncrementor) {
            // If no BL incrementor configured, skip silently
            return $ticket;
        }

        $blReference = $this->incrementorService->formatReference(
            $blIncrementor->template,
            $blIncrementor->nextTrick
        );
        $blIncrementor->increment('nextTrick');

        /** @var DocumentHeader $bl */
        $bl = $this->documents->create([
            'document_incrementor_id' => $blIncrementor->id,
            'reference'               => $blReference,
            'document_type'           => 'DeliveryNote',
            'document_title'          => 'Bon de Livraison',
            'parent_id'               => $ticket->id,
            'thirdPartner_id'         => $customerId,
            'company_role'            => 'seller',
            'user_id'                 => $session->user_id,
            'warehouse_id'            => $warehouseId,
            'pos_session_id'          => $session->id,
            'status'                  => 'confirmed',
            'issued_at'               => now(),
            'notes'                   => 'Généré automatiquement depuis ticket POS ' . $ticket->reference,
        ]);

        // Copy lines from ticket
        foreach ($items as $i => $item) {
            $this->lignes->createForDocument($bl, [
                'sort_order'       => $i + 1,
                'product_id'       => $item['product_id'],
                'line_type'        => 'product',
                'designation'      => $item['designation'] ?? '',
                'reference'        => $item['reference'] ?? null,
                'quantity'         => $item['quantity'],
                'unit'             => $item['unit'] ?? 'pcs',
                'unit_price'       => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_percent'      => $item['tax_percent'] ?? 0,
            ]);
        }

        // Create footer — amount_due = credit amount (to be invoiced)
        $this->footers->upsertForDocument($bl, [
            'total_ht'       => $totalHt,
            'total_discount' => 0,
            'total_tax'      => $totalTax,
            'total_ttc'      => $totalTtc,
            'amount_paid'    => 0,
            'amount_due'     => $creditAmount,
        ]);

        return $bl;
    }
}
