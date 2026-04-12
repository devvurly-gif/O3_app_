<?php

namespace App\Http\Controllers\Api\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventes\StoreDocumentVenteRequest;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Services\DocumentIncrementorService;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentVenteController extends Controller
{
    public function __construct(
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentIncrementorService $incrementorService,
        private StockMouvementService $stockService,
    ) {
    }

    /**
     * Convert a confirmed Quote into a Customer Order (BC Client).
     *
     * POST /api/ventes/documents/{devis}/generer-bc
     *
     * Devis (confirmé) → BC Client (confirmé) — NO stock impact
     */
    public function generer_bc(
        DocumentHeader $devis,
        StoreDocumentVenteRequest $request
    ): JsonResponse {
        if (!$devis->isQuoteSale()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Devis.',
            ], 422);
        }

        if ($devis->status !== 'confirmed') {
            return response()->json([
                'message' => 'Ce devis ne peut pas être converti. Statut actuel : ' . $devis->status,
            ], 422);
        }

        $devis->loadMissing(['lignes', 'footer']);

        $bc = DB::transaction(function () use ($devis) {
            $reference = $this->generateReference('CustomerOrder');
            $now = now();

            $bc = DocumentHeader::create([
                'document_incrementor_id' => $devis->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'CustomerOrder',
                'document_title'          => 'Bon de Commande Client',
                'parent_id'               => $devis->id,
                'thirdPartner_id'         => $devis->thirdPartner_id,
                'company_role'            => $devis->company_role,
                'warehouse_id'            => $devis->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => $devis->notes,
            ]);

            $this->bulkCopyLines($devis, $bc, $now);
            $this->copyFooter($devis, $bc);

            $devis->delete(); // soft-delete — BC keeps parent_id for traceability

            return $bc;
        });

        return response()->json([
            'message' => 'Bon de Commande Client créé avec succès.',
            'data'    => $bc->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    /**
     * Convert a confirmed Customer Order into a Delivery Note (BL).
     *
     * POST /api/ventes/documents/{bc}/generer-bl
     *
     * BC Client (confirmé) → BL (confirmé) — stock exit happens here
     */
    public function generer_bl(
        DocumentHeader $bc,
        StoreDocumentVenteRequest $request
    ): JsonResponse {
        if (!$bc->isCustomerOrder()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Bon de Commande Client.',
            ], 422);
        }

        if ($bc->status !== 'confirmed') {
            return response()->json([
                'message' => 'Ce BC ne peut pas être converti. Statut actuel : ' . $bc->status,
            ], 422);
        }

        $bc->loadMissing(['lignes', 'footer']);

        $bl = DB::transaction(function () use ($bc) {
            $reference = $this->generateReference('DeliveryNote');
            $now = now();

            $bl = DocumentHeader::create([
                'document_incrementor_id' => $bc->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'DeliveryNote',
                'document_title'          => 'Bon de Livraison',
                'parent_id'               => $bc->id,
                'thirdPartner_id'         => $bc->thirdPartner_id,
                'company_role'            => $bc->company_role,
                'warehouse_id'            => $bc->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => $bc->notes,
            ]);

            $this->bulkCopyLines($bc, $bl, $now);
            $this->copyFooter($bc, $bl);

            $bc->delete(); // soft-delete — BL keeps parent_id for traceability

            $bl->load('lignes');
            $this->stockService->processDocument($bl);

            // When paiement_sur_bl is active, the BL itself counts against encours
            if (Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true'
                && $bc->thirdPartner_id
                && $bc->footer?->total_ttc > 0
            ) {
                ThirdPartner::where('id', $bc->thirdPartner_id)
                    ->increment('encours_actuel', $bc->footer->total_ttc);
            }

            return $bl;
        });

        return response()->json([
            'message' => 'Bon de Livraison créé avec succès.',
            'data'    => $bl->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    /**
     * Confirm delivery reception and create the Invoice.
     *
     * PUT /api/ventes/documents/{bl}/confirmer
     *
     * The invoice does NOT impact stock -- stock was already exited
     * when the BL was created.
     */
    public function confirmer_reception(Request $request, DocumentHeader $bl): JsonResponse
    {
        if (!$bl->isDeliveryNote()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Bon de Livraison.',
            ], 422);
        }

        if (!in_array($bl->status, ['confirmed', 'delivered'])) {
            return response()->json([
                'message' => 'Ce BL ne peut plus être converti en facture. Statut actuel : ' . $bl->status,
            ], 422);
        }

        $paymentMethod = $request->input('payment_method', 'credit');

        // Eager-load relations upfront to avoid N+1 queries
        $bl->loadMissing(['lignes', 'footer', 'payments']);

        $facture = DB::transaction(function () use ($bl, $paymentMethod) {
            $bl->update(['status' => 'delivered']);

            $reference = $this->generateReference('InvoiceSale');
            $now = now();

            $facture = DocumentHeader::create([
                'document_incrementor_id' => $bl->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'InvoiceSale',
                'document_title'          => 'Facture',
                'parent_id'               => $bl->id,
                'thirdPartner_id'         => $bl->thirdPartner_id,
                'company_role'            => $bl->company_role,
                'warehouse_id'            => $bl->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'pending',
                'issued_at'               => $now,
                'due_at'                  => $now->copy()->addDays(60),
                'notes'                   => $bl->notes,
            ]);

            // Bulk insert lines from BL to Invoice
            $lignesData = $bl->lignes->map(fn ($ligne) => [
                'document_header_id' => $facture->id,
                'product_id'         => $ligne->product_id,
                'sort_order'         => $ligne->sort_order,
                'line_type'          => $ligne->line_type,
                'designation'        => $ligne->designation,
                'reference'          => $ligne->reference,
                'quantity'           => $ligne->quantity,
                'unit'               => $ligne->unit,
                'unit_price'         => $ligne->unit_price,
                'discount_percent'   => $ligne->discount_percent,
                'tax_percent'        => $ligne->tax_percent,
                'created_at'         => $now,
                'updated_at'         => $now,
            ])->toArray();

            if (!empty($lignesData)) {
                \App\Models\DocumentLigne::insert($lignesData);
            }

            if ($bl->footer) {
                $facture->footer()->create([
                    'total_ht'       => $bl->footer->total_ht,
                    'total_discount' => $bl->footer->total_discount,
                    'total_tax'      => $bl->footer->total_tax,
                    'total_ttc'      => $bl->footer->total_ttc,
                    'amount_paid'    => 0,
                    'amount_due'     => $bl->footer->total_ttc,
                    'payment_method' => $paymentMethod,
                ]);

                // If payment is on credit, add total_ttc to encours_actuel.
                // Exception: when paiement_sur_bl is active and the BL came from a
                // conversion chain (parent_id set), encours was already incremented
                // at BL creation — don't double-count.
                $paiementSurBl = Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true';
                $alreadyCounted = $paiementSurBl && $bl->parent_id !== null;

                if ($paymentMethod === 'credit' && $bl->thirdPartner_id && $bl->footer->total_ttc > 0 && !$alreadyCounted) {
                    ThirdPartner::where('id', $bl->thirdPartner_id)
                        ->increment('encours_actuel', $bl->footer->total_ttc);
                }
            }

            // Transfer existing BL payments to the new invoice in bulk
            if ($bl->payments->isNotEmpty()) {
                Payment::$skipNotification = true;
                try {
                    Payment::whereIn('id', $bl->payments->pluck('id'))
                        ->update(['document_header_id' => $facture->id]);
                } finally {
                    Payment::$skipNotification = false;
                }

                // Recalculate invoice footer after payment transfer
                $totalPaid = $bl->payments->sum('amount');
                $facture->loadMissing('footer');
                if ($facture->footer) {
                    $facture->footer->update([
                        'amount_paid' => $totalPaid,
                        'amount_due'  => max(0, $facture->footer->total_ttc - $totalPaid),
                    ]);

                    if ($totalPaid >= $facture->footer->total_ttc) {
                        $facture->update(['status' => 'paid']);
                    } elseif ($totalPaid > 0) {
                        $facture->update(['status' => 'partial']);
                    }
                }
            }

            return $facture;
        });

        return response()->json([
            'message' => 'Réception confirmée. Facture ' . $facture->reference . ' créée.',
            'data'    => $facture->load(['thirdPartner', 'lignes.product', 'footer', 'user']),
        ], 201);
    }

    /**
     * Cancel a Delivery Note (BL) and reverse its stock movements.
     *
     * POST /api/ventes/documents/{bl}/annuler
     *
     * - Reverses stock movements created at BL generation
     * - Decrements encours_actuel when paiement_sur_bl is active
     * - Restores the parent BC (soft-deleted) so it can be re-used
     */
    public function annuler_bl(DocumentHeader $bl): JsonResponse
    {
        if (!$bl->isDeliveryNote()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Bon de Livraison.',
            ], 422);
        }

        if (!in_array($bl->status, ['confirmed', 'delivered'])) {
            return response()->json([
                'message' => 'Ce BL ne peut pas être annulé. Statut actuel : ' . $bl->status,
            ], 422);
        }

        // Cannot cancel a BL that already has a child invoice
        if ($bl->children()->where('document_type', 'InvoiceSale')->exists()) {
            return response()->json([
                'message' => 'Ce BL a déjà été facturé et ne peut pas être annulé.',
            ], 422);
        }

        DB::transaction(function () use ($bl) {
            // 1. Reverse stock movements
            $this->stockService->reverseDocument($bl);

            // 2. Reverse encours if paiement_sur_bl was active and BL came from a chain
            if (Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true'
                && $bl->parent_id !== null
                && $bl->thirdPartner_id
            ) {
                $bl->loadMissing('footer');
                if ($bl->footer?->total_ttc > 0) {
                    ThirdPartner::where('id', $bl->thirdPartner_id)
                        ->decrement('encours_actuel', $bl->footer->total_ttc);
                    // Prevent negative encours
                    ThirdPartner::where('id', $bl->thirdPartner_id)
                        ->where('encours_actuel', '<', 0)
                        ->update(['encours_actuel' => 0]);
                }
            }

            // 3. Restore the parent BC (soft-deleted when BL was created)
            if ($bl->parent_id) {
                DocumentHeader::withTrashed()
                    ->where('id', $bl->parent_id)
                    ->whereNotNull('deleted_at')
                    ->restore();
            }

            // 4. Mark BL as cancelled
            $bl->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Bon de Livraison annulé. Stocks restaurés.',
            'data'    => $bl->fresh(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ]);
    }

    /**
     * Bulk copy lines from source document to target document.
     */
    private function bulkCopyLines(DocumentHeader $source, DocumentHeader $target, $now): void
    {
        $lignesData = $source->lignes->map(fn ($ligne) => [
            'document_header_id' => $target->id,
            'product_id'         => $ligne->product_id,
            'sort_order'         => $ligne->sort_order,
            'line_type'          => $ligne->line_type,
            'designation'        => $ligne->designation,
            'reference'          => $ligne->reference,
            'quantity'           => $ligne->quantity,
            'unit'               => $ligne->unit,
            'unit_price'         => $ligne->unit_price,
            'discount_percent'   => $ligne->discount_percent,
            'tax_percent'        => $ligne->tax_percent,
            'created_at'         => $now,
            'updated_at'         => $now,
        ])->toArray();

        if (!empty($lignesData)) {
            \App\Models\DocumentLigne::insert($lignesData);
        }
    }

    /**
     * Copy footer from source document to target document.
     */
    private function copyFooter(DocumentHeader $source, DocumentHeader $target): void
    {
        if ($source->footer) {
            $target->footer()->create([
                'total_ht'       => $source->footer->total_ht,
                'total_discount' => $source->footer->total_discount,
                'total_tax'      => $source->footer->total_tax,
                'total_ttc'      => $source->footer->total_ttc,
                'amount_paid'    => 0,
                'amount_due'     => $source->footer->total_ttc,
            ]);
        }
    }

    /**
     * Duplicate a document for multiple clients.
     *
     * POST /api/ventes/documents/{document}/duplicate-for-clients
     * Body: { "client_ids": [1, 2, 3] }
     *
     * Creates a draft copy of the document for each selected client.
     */
    public function duplicateForClients(Request $request, DocumentHeader $document): JsonResponse
    {
        $request->validate([
            'client_ids'   => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:third_partners,id',
        ]);

        $document->loadMissing(['lignes', 'footer']);

        $created = DB::transaction(function () use ($document, $request) {
            $copies = [];
            $now = now();

            foreach ($request->client_ids as $clientId) {
                $reference = $this->generateReference($document->document_type);

                $copy = DocumentHeader::create([
                    'document_incrementor_id' => $document->document_incrementor_id,
                    'reference'               => $reference,
                    'document_type'           => $document->document_type,
                    'document_title'          => $document->document_title,
                    'thirdPartner_id'         => $clientId,
                    'company_role'            => $document->company_role,
                    'warehouse_id'            => $document->warehouse_id,
                    'user_id'                 => auth()->id(),
                    'status'                  => 'draft',
                    'issued_at'               => $now,
                    'notes'                   => $document->notes,
                ]);

                $this->bulkCopyLines($document, $copy, $now);
                $this->copyFooter($document, $copy);

                $copies[] = $copy->load(['thirdPartner', 'lignes.product', 'footer']);
            }

            return $copies;
        });

        return response()->json([
            'message' => count($created) . ' copie(s) créée(s) avec succès.',
            'data'    => $created,
        ], 201);
    }

    private function generateReference(string $documentType): string
    {
        $incrementor = $this->incrementors
            ->all(orderBy: 'di_title')
            ->firstWhere('di_model', $documentType);

        if (!$incrementor) {
            $prefix = match ($documentType) {
                'CustomerOrder' => 'BC',
                'DeliveryNote'  => 'BL',
                'InvoiceSale'   => 'FAC',
                default         => 'DOC',
            };
            return sprintf('%s-%d-%04d', $prefix, now()->year, rand(1, 9999));
        }

        $reference = $this->incrementorService->formatReference(
            $incrementor->template,
            $incrementor->nextTrick
        );

        $incrementor->increment('nextTrick');

        return $reference;
    }
}
