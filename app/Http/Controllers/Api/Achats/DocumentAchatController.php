<?php

namespace App\Http\Controllers\Api\Achats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Achats\StoreDocumentAchatRequest;
use App\Models\DocumentHeader;
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

    /**
     * Convert a confirmed PurchaseOrder into a ReceiptNote (Bon de Réception).
     *
     * POST /api/achats/documents/{commande}/generer-reception
     *
     * Steps:
     * 1. Verify the PurchaseOrder status is 'confirmed'
     * 2. Validation happens in StoreDocumentAchatRequest
     * 3. Create ReceiptNote in a DB transaction with lines copied from PurchaseOrder
     * 4. DocumentAchatObserver fires automatically → stock entry (IN)
     */
    public function generer_reception(
        DocumentHeader $commande,
        StoreDocumentAchatRequest $request
    ): JsonResponse {
        if (!$commande->isPurchaseOrder()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Bon de Commande.',
            ], 422);
        }

        if ($commande->status !== 'confirmed') {
            return response()->json([
                'message' => 'Ce bon de commande ne peut pas être converti. Statut actuel : ' . $commande->status,
            ], 422);
        }

        // Eager-load to avoid N+1
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
                'status'                  => 'confirmed',
                'issued_at'               => $now,
                'notes'                   => $commande->notes,
            ]);

            // Bulk insert lines
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

            $commande->delete(); // soft-delete — BR keeps parent_id for traceability

            // Reload lignes from DB for stock processing
            $br->load('lignes');
            $this->stockService->processDocument($br);

            return $br;
        });

        return response()->json([
            'message' => 'Bon de Réception créé avec succès.',
            'data'    => $br->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }

    /**
     * Confirm receipt and create the Purchase Invoice (Facture Achat).
     *
     * PUT /api/achats/documents/{br}/confirmer-facture
     *
     * The invoice does NOT impact stock — stock was already entered
     * when the ReceiptNote was created.
     */
    public function confirmer_facture(Request $request, DocumentHeader $br): JsonResponse
    {
        if (!$br->isReceiptNotePurchase()) {
            return response()->json([
                'message' => 'Ce document n\'est pas un Bon de Réception.',
            ], 422);
        }

        if ($br->status !== 'confirmed') {
            return response()->json([
                'message' => 'Ce BR ne peut plus être confirmé. Statut actuel : ' . $br->status,
            ], 422);
        }

        $paymentMethod = $request->input('payment_method', 'credit');

        // Eager-load relations upfront to avoid N+1 queries
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

            // Bulk insert lines from BR to Invoice
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

                // If payment is on credit, add total_ttc to the supplier's encours_actuel
                if ($paymentMethod === 'credit' && $br->thirdPartner_id && $br->footer->total_ttc > 0) {
                    \App\Models\ThirdPartner::where('id', $br->thirdPartner_id)
                        ->increment('encours_actuel', $br->footer->total_ttc);
                }
            }

            return $facture;
        });

        return response()->json([
            'message' => 'Réception confirmée. Facture Achat ' . $facture->reference . ' créée.',
            'data'    => $facture->load(['thirdPartner', 'lignes.product', 'footer', 'user']),
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
