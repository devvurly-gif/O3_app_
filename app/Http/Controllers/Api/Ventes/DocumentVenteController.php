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

    // ── Devis → BC ───────────────────────────────────────────────────

    /**
     * POST /api/ventes/documents/{devis}/generer-bc
     *
     * Devis (confirmé) → BC Client (confirmé) — NO stock impact
     */
    public function generer_bc(
        DocumentHeader $devis,
        StoreDocumentVenteRequest $request
    ): JsonResponse {
        if (!$devis->isQuoteSale()) {
            return response()->json(['message' => 'Ce document n\'est pas un Devis.'], 422);
        }

        if ($devis->status !== 'confirmed') {
            return response()->json(['message' => 'Ce devis ne peut pas être converti. Statut actuel : ' . $devis->status], 422);
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
            $devis->delete();

            return $bc;
        });

        return response()->json([
            'message' => 'Bon de Commande Client créé avec succès.',
            'data'    => $bc->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    // ── BC → BL (draft, pending stock) ───────────────────────────────

    /**
     * POST /api/ventes/documents/{bc}/generer-bl
     *
     * BC Client (confirmé) → BL (draft) — stock movements PENDING
     */
    public function generer_bl(
        DocumentHeader $bc,
        StoreDocumentVenteRequest $request
    ): JsonResponse {
        if (!$bc->isCustomerOrder()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Commande Client.'], 422);
        }

        if ($bc->status !== 'confirmed') {
            return response()->json(['message' => 'Ce BC ne peut pas être converti. Statut actuel : ' . $bc->status], 422);
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
                'status'                  => 'draft',
                'issued_at'               => $now,
                'notes'                   => $bc->notes,
            ]);

            $this->bulkCopyLines($bc, $bl, $now);
            $this->copyFooter($bc, $bl);
            $bc->delete();

            $bl->load('lignes');
            $this->stockService->processDocument($bl, pending: true);

            return $bl;
        });

        return response()->json([
            'message' => 'Bon de Livraison créé (en attente de confirmation).',
            'data'    => $bl->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    // ── Confirmer BL (draft → confirmed, stock applied) ──────────────

    /**
     * PUT /api/ventes/documents/{bl}/confirmer-bl
     *
     * BL (draft) → BL (confirmed) — stock exit applied HERE
     */
    public function confirmer_bl(DocumentHeader $bl): JsonResponse
    {
        if (!$bl->isDeliveryNote()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Livraison.'], 422);
        }

        if ($bl->status !== 'draft') {
            return response()->json(['message' => 'Ce BL est déjà confirmé. Statut : ' . $bl->status], 422);
        }

        DB::transaction(function () use ($bl) {
            $this->stockService->applyDocumentMovements($bl);
            $bl->update(['status' => 'confirmed']);

            if (Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true' && $bl->thirdPartner_id) {
                $bl->loadMissing('footer', 'thirdPartner');
                if ($bl->thirdPartner && $bl->footer?->total_ttc > 0) {
                    $bl->thirdPartner->recalculateEncours();
                }
            }
        });

        return response()->json([
            'message' => 'Bon de Livraison confirmé. Stock mis à jour.',
            'data'    => $bl->fresh(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ]);
    }

    // ── Annuler BL (draft only — cancel pending movements) ──────────

    /**
     * POST /api/ventes/documents/{bl}/annuler
     *
     * Only draft BLs can be cancelled. Confirmed → use retour client.
     */
    public function annuler_bl(DocumentHeader $bl): JsonResponse
    {
        if (!$bl->isDeliveryNote()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Livraison.'], 422);
        }

        if ($bl->status !== 'draft') {
            return response()->json([
                'message' => 'Seuls les BL en brouillon peuvent être annulés. '
                           . 'Pour un BL confirmé, créez un Retour Client.',
            ], 422);
        }

        DB::transaction(function () use ($bl) {
            $this->stockService->cancelDocumentMovements($bl);

            if ($bl->parent_id) {
                DocumentHeader::withTrashed()
                    ->where('id', $bl->parent_id)
                    ->whereNotNull('deleted_at')
                    ->restore();
            }

            $bl->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Bon de Livraison annulé.',
            'data'    => $bl->fresh(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ]);
    }

    // ── BL → Facture ─────────────────────────────────────────────────

    /**
     * PUT /api/ventes/documents/{bl}/confirmer
     *
     * BL (confirmed) → Facture (pending). No stock impact.
     */
    public function confirmer_reception(Request $request, DocumentHeader $bl): JsonResponse
    {
        if (!$bl->isDeliveryNote()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Livraison.'], 422);
        }

        if (!in_array($bl->status, ['confirmed', 'delivered'])) {
            return response()->json(['message' => 'Ce BL doit être confirmé avant d\'être facturé. Statut : ' . $bl->status], 422);
        }

        $paymentMethod = $request->input('payment_method', 'credit');
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

            $this->bulkCopyLines($bl, $facture, $now);

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

                if ($paymentMethod === 'credit' && $bl->thirdPartner_id && $bl->footer->total_ttc > 0) {
                    // Recalculate: BL is now invoiced (counts as invoice, BL no longer counts)
                    $bl->loadMissing('thirdPartner');
                    $bl->thirdPartner?->recalculateEncours();
                }
            }

            if ($bl->payments->isNotEmpty()) {
                Payment::$skipNotification = true;
                try {
                    Payment::whereIn('id', $bl->payments->pluck('id'))
                        ->update(['document_header_id' => $facture->id]);
                } finally {
                    Payment::$skipNotification = false;
                }

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
            'message' => 'Facture ' . $facture->reference . ' créée.',
            'data'    => $facture->load(['thirdPartner', 'lignes.product', 'footer', 'user']),
        ], 201);
    }

    // ── Retour Client (BL/Facture confirmés → ReturnSale, stock IN) ──

    /**
     * POST /api/ventes/documents/{document}/retour-client
     *
     * Creates a ReturnSale from a confirmed BL or InvoiceSale.
     * Stock moves back IN. Encours decremented.
     */
    public function retour_client(Request $request, DocumentHeader $document): JsonResponse
    {
        if (!in_array($document->document_type, ['DeliveryNote', 'InvoiceSale'])) {
            return response()->json([
                'message' => 'Un retour client ne peut être créé qu\'à partir d\'un BL ou d\'une Facture.',
            ], 422);
        }

        $confirmedStatuses = ['confirmed', 'delivered', 'pending', 'paid', 'partial'];
        if (!in_array($document->status, $confirmedStatuses)) {
            return response()->json([
                'message' => 'Ce document ne peut pas faire l\'objet d\'un retour. Statut : ' . $document->status,
            ], 422);
        }

        // Optional: partial return with specific lines/quantities
        $request->validate([
            'lines'            => 'nullable|array',
            'lines.*.product_id' => 'required_with:lines|exists:products,id',
            'lines.*.quantity'   => 'required_with:lines|numeric|min:0.01',
            'notes'            => 'nullable|string',
        ]);

        $document->loadMissing(['lignes', 'footer']);

        $retour = DB::transaction(function () use ($document, $request) {
            $reference = $this->generateReference('ReturnSale');
            $now = now();

            $retour = DocumentHeader::create([
                'document_incrementor_id' => $document->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'ReturnSale',
                'document_title'          => 'Bon de Retour Client',
                'parent_id'               => $document->id,
                'thirdPartner_id'         => $document->thirdPartner_id,
                'company_role'            => $document->company_role,
                'warehouse_id'            => $document->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => $request->input('notes', 'Retour client - ' . $document->reference),
            ]);

            // If specific lines provided → partial return; else → full return
            $requestedLines = $request->input('lines');

            if ($requestedLines) {
                $lineMap = collect($requestedLines)->keyBy('product_id');
                $lignesData = [];
                $totalHt = 0;
                $totalTax = 0;

                foreach ($document->lignes as $ligne) {
                    if (!$lineMap->has($ligne->product_id)) continue;

                    $qty = min($lineMap[$ligne->product_id]['quantity'], $ligne->quantity);
                    $lineHt = $qty * $ligne->unit_price * (1 - ($ligne->discount_percent / 100));
                    $lineTax = $lineHt * ($ligne->tax_percent / 100);
                    $totalHt += $lineHt;
                    $totalTax += $lineTax;

                    $lignesData[] = [
                        'document_header_id' => $retour->id,
                        'product_id'         => $ligne->product_id,
                        'sort_order'         => $ligne->sort_order,
                        'line_type'          => $ligne->line_type,
                        'designation'        => $ligne->designation,
                        'reference'          => $ligne->reference,
                        'quantity'           => $qty,
                        'unit'               => $ligne->unit,
                        'unit_price'         => $ligne->unit_price,
                        'discount_percent'   => $ligne->discount_percent,
                        'tax_percent'        => $ligne->tax_percent,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }

                if (!empty($lignesData)) {
                    \App\Models\DocumentLigne::insert($lignesData);
                }

                $retour->footer()->create([
                    'total_ht'       => $totalHt,
                    'total_discount' => 0,
                    'total_tax'      => $totalTax,
                    'total_ttc'      => $totalHt + $totalTax,
                    'amount_paid'    => 0,
                    'amount_due'     => 0,
                ]);
            } else {
                // Full return — copy all lines
                $this->bulkCopyLines($document, $retour, $now);
                $this->copyFooter($document, $retour);
            }

            // Process stock: ReturnSale = stock IN (goods come back)
            $retour->load('lignes');
            $this->stockService->processDocument($retour);

            // Recalculate encours authoritatively
            $retour->loadMissing('footer');
            if ($document->thirdPartner_id && $retour->footer?->total_ttc > 0) {
                $document->loadMissing('thirdPartner');
                $document->thirdPartner?->recalculateEncours();
            }

            return $retour;
        });

        return response()->json([
            'message' => 'Retour Client ' . $retour->reference . ' créé. Stock restauré.',
            'data'    => $retour->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────

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
                'ReturnSale'    => 'BRC',
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
