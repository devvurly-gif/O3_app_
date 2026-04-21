<?php

namespace App\Http\Controllers\Api\Achats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\StoreDocumentAchatRequest;
use App\Models\DocumentHeader;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Services\DocumentIncrementorService;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentAchatController extends Controller
{
    public function __construct(
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentIncrementorService $incrementorService,
        private StockMouvementService $stockService,
    ) {
    }

    // ── BC Achat → BR (draft, pending stock) ─────────────────────────

    /**
     * POST /api/achats/documents/{commande}/generer-reception
     *
     * PurchaseOrder (confirmé) → ReceiptNotePurchase (draft) — stock PENDING
     */
    public function generer_reception(
        DocumentHeader $commande,
        StoreDocumentAchatRequest $request
    ): JsonResponse {
        if (!$commande->isPurchaseOrder()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Commande.'], 422);
        }

        if ($commande->status !== 'confirmed') {
            return response()->json(['message' => 'Ce bon de commande ne peut pas être converti. Statut : ' . $commande->status], 422);
        }

        $commande->loadMissing(['lignes', 'footer']);

        $br = DB::transaction(function () use ($commande) {
            $reference = $this->generateReference('ReceiptNotePurchase');
            $now = now();

            $br = DocumentHeader::create([
                'document_incrementor_id' => $commande->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'ReceiptNotePurchase',
                'document_title'          => 'Bon de Réception',
                'parent_id'               => $commande->id,
                'thirdPartner_id'         => $commande->thirdPartner_id,
                'company_role'            => $commande->company_role,
                'warehouse_id'            => $commande->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'draft',
                'issued_at'               => $now,
                'notes'                   => $commande->notes,
            ]);

            $lignesData = $commande->lignes->map(fn ($ligne) => [
                'document_header_id' => $br->id,
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

            if ($commande->footer) {
                $br->footer()->create([
                    'total_ht'       => $commande->footer->total_ht,
                    'total_discount' => $commande->footer->total_discount,
                    'total_tax'      => $commande->footer->total_tax,
                    'total_ttc'      => $commande->footer->total_ttc,
                    'amount_paid'    => 0,
                    'amount_due'     => $commande->footer->total_ttc,
                ]);
            }

            $commande->delete();

            // Create stock movements as PENDING
            $br->load('lignes');
            $this->stockService->processDocument($br, pending: true);

            return $br;
        });

        return response()->json([
            'message' => 'Bon de Réception créé (en attente de confirmation).',
            'data'    => $br->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    // ── Confirmer BR (draft → confirmed, stock applied) ──────────────

    /**
     * PUT /api/achats/documents/{br}/confirmer-br
     *
     * BR (draft) → BR (confirmed) — stock IN applied HERE
     */
    public function confirmer_br(DocumentHeader $br): JsonResponse
    {
        if (!$br->isReceiptNotePurchase()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Réception.'], 422);
        }

        if ($br->status !== 'draft') {
            return response()->json(['message' => 'Ce BR est déjà confirmé. Statut : ' . $br->status], 422);
        }

        DB::transaction(function () use ($br) {
            $this->stockService->applyDocumentMovements($br);
            $br->update(['status' => 'confirmed']);
        });

        return response()->json([
            'message' => 'Bon de Réception confirmé. Stock mis à jour.',
            'data'    => $br->fresh(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ]);
    }

    // ── Annuler BR (draft only) ──────────────────────────────────────

    /**
     * POST /api/achats/documents/{br}/annuler
     *
     * Only draft BRs can be cancelled. Confirmed → use retour fournisseur.
     */
    public function annuler_br(DocumentHeader $br): JsonResponse
    {
        if (!$br->isReceiptNotePurchase()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Réception.'], 422);
        }

        if ($br->status !== 'draft') {
            return response()->json([
                'message' => 'Seuls les BR en brouillon peuvent être annulés. '
                           . 'Pour un BR confirmé, créez un Retour Fournisseur.',
            ], 422);
        }

        DB::transaction(function () use ($br) {
            $this->stockService->cancelDocumentMovements($br);

            if ($br->parent_id) {
                DocumentHeader::withTrashed()
                    ->where('id', $br->parent_id)
                    ->whereNotNull('deleted_at')
                    ->restore();
            }

            $br->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Bon de Réception annulé.',
            'data'    => $br->fresh(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ]);
    }

    // ── BR → Facture Achat ───────────────────────────────────────────

    /**
     * PUT /api/achats/documents/{br}/confirmer-facture
     *
     * BR (confirmed) → Facture Achat. No stock impact.
     */
    public function confirmer_facture(Request $request, DocumentHeader $br): JsonResponse
    {
        if (!$br->isReceiptNotePurchase()) {
            return response()->json(['message' => 'Ce document n\'est pas un Bon de Réception.'], 422);
        }

        if ($br->status !== 'confirmed') {
            return response()->json(['message' => 'Ce BR doit être confirmé avant d\'être facturé. Statut : ' . $br->status], 422);
        }

        $paymentMethod = $request->input('payment_method', 'credit');
        $br->loadMissing(['lignes', 'footer']);

        $facture = DB::transaction(function () use ($br, $paymentMethod) {
            $br->update(['status' => 'received']);

            $reference = $this->generateReference('InvoicePurchase');
            $now = now();

            $facture = DocumentHeader::create([
                'document_incrementor_id' => $br->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'InvoicePurchase',
                'document_title'          => 'Facture Achat',
                'parent_id'               => $br->id,
                'thirdPartner_id'         => $br->thirdPartner_id,
                'company_role'            => $br->company_role,
                'warehouse_id'            => $br->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'pending',
                'issued_at'               => $now,
                'due_at'                  => $now->copy()->addDays(60),
                'notes'                   => $br->notes,
            ]);

            $lignesData = $br->lignes->map(fn ($ligne) => [
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

            if ($br->footer) {
                $facture->footer()->create([
                    'total_ht'       => $br->footer->total_ht,
                    'total_discount' => $br->footer->total_discount,
                    'total_tax'      => $br->footer->total_tax,
                    'total_ttc'      => $br->footer->total_ttc,
                    'amount_paid'    => 0,
                    'amount_due'     => $br->footer->total_ttc,
                    'payment_method' => $paymentMethod,
                ]);

                if ($paymentMethod === 'credit' && $br->thirdPartner_id && $br->footer->total_ttc > 0) {
                    $br->loadMissing('thirdPartner');
                    $br->thirdPartner?->recalculateEncours();
                }
            }

            return $facture;
        });

        return response()->json([
            'message' => 'Facture Achat ' . $facture->reference . ' créée.',
            'data'    => $facture->load(['thirdPartner', 'lignes.product', 'footer', 'user']),
        ], 201);
    }

    // ── Retour Fournisseur (BR/Facture confirmés → ReturnPurchase, stock OUT) ──

    /**
     * POST /api/achats/documents/{document}/retour-fournisseur
     *
     * Creates a ReturnPurchase from a confirmed BR or InvoicePurchase.
     * Stock moves OUT (goods sent back to supplier).
     */
    public function retour_fournisseur(Request $request, DocumentHeader $document): JsonResponse
    {
        if (!in_array($document->document_type, ['ReceiptNotePurchase', 'InvoicePurchase'])) {
            return response()->json([
                'message' => 'Un retour fournisseur ne peut être créé qu\'à partir d\'un BR ou d\'une Facture Achat.',
            ], 422);
        }

        $confirmedStatuses = ['confirmed', 'received', 'pending', 'paid', 'partial'];
        if (!in_array($document->status, $confirmedStatuses)) {
            return response()->json([
                'message' => 'Ce document ne peut pas faire l\'objet d\'un retour. Statut : ' . $document->status,
            ], 422);
        }

        $request->validate([
            'lines'              => 'nullable|array',
            'lines.*.product_id' => 'required_with:lines|exists:products,id',
            'lines.*.quantity'   => 'required_with:lines|numeric|min:0.01',
            'notes'              => 'nullable|string',
        ]);

        $document->loadMissing(['lignes', 'footer']);

        $retour = DB::transaction(function () use ($document, $request) {
            $reference = $this->generateReference('ReturnPurchase');
            $now = now();

            $retour = DocumentHeader::create([
                'document_incrementor_id' => $document->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'ReturnPurchase',
                'document_title'          => 'Bon de Retour Fournisseur',
                'parent_id'               => $document->id,
                'thirdPartner_id'         => $document->thirdPartner_id,
                'company_role'            => $document->company_role,
                'warehouse_id'            => $document->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => $request->input('notes', 'Retour fournisseur - ' . $document->reference),
            ]);

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
                $lignesData = $document->lignes->map(fn ($ligne) => [
                    'document_header_id' => $retour->id,
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

                if ($document->footer) {
                    $retour->footer()->create([
                        'total_ht'       => $document->footer->total_ht,
                        'total_discount' => $document->footer->total_discount,
                        'total_tax'      => $document->footer->total_tax,
                        'total_ttc'      => $document->footer->total_ttc,
                        'amount_paid'    => 0,
                        'amount_due'     => 0,
                    ]);
                }
            }

            // Process stock: ReturnPurchase = stock OUT (goods go back to supplier)
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
            'message' => 'Retour Fournisseur ' . $retour->reference . ' créé. Stock mis à jour.',
            'data'    => $retour->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    private function generateReference(string $documentType): string
    {
        $incrementor = $this->incrementors
            ->all(orderBy: 'di_title')
            ->firstWhere('di_model', $documentType);

        if (!$incrementor) {
            $prefix = match ($documentType) {
                'ReceiptNotePurchase' => 'BR',
                'InvoicePurchase'     => 'FA',
                'ReturnPurchase'      => 'RTR',
                default               => 'DOC',
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
