<?php

namespace App\Http\Controllers\Api\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventes\StoreDocumentVenteRequest;
use App\Models\DocumentHeader;
use App\Models\Payment;
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
     * Convert an accepted Quote into a Delivery Note (BL).
     *
     * POST /api/ventes/documents/{devis}/generer-bl
     *
     * Steps:
     * 1. Verify the quote status is 'confirmed' (accepté)
     * 2. Credit check happens in StoreDocumentVenteRequest
     * 3. Create BL in a DB transaction with lines copied from quote
     * 4. DocumentVenteObserver fires automatically → stock exit + encours++
     */
    public function generer_bl(
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

        $bl = DB::transaction(function () use ($devis) {
            $reference = $this->generateReference('DeliveryNote');

            $bl = DocumentHeader::create([
                'document_incrementor_id' => $devis->document_incrementor_id,
                'reference'               => $reference,
                'document_type'           => 'DeliveryNote',
                'document_title'          => 'Bon de Livraison',
                'parent_id'               => $devis->id,
                'thirdPartner_id'         => $devis->thirdPartner_id,
                'company_role'            => $devis->company_role,
                'warehouse_id'            => $devis->warehouse_id,
                'user_id'                 => auth()->id(),
                'status'                  => 'confirmed',
                'issued_at'               => now(),
                'notes'                   => $devis->notes,
            ]);

            foreach ($devis->lignes as $ligne) {
                $bl->lignes()->create([
                    'product_id'       => $ligne->product_id,
                    'sort_order'       => $ligne->sort_order,
                    'line_type'        => $ligne->line_type,
                    'designation'      => $ligne->designation,
                    'reference'        => $ligne->reference,
                    'quantity'         => $ligne->quantity,
                    'unit'             => $ligne->unit,
                    'unit_price'       => $ligne->unit_price,
                    'discount_percent' => $ligne->discount_percent,
                    'tax_percent'      => $ligne->tax_percent,
                ]);
            }

            if ($devis->footer) {
                $bl->footer()->create([
                    'total_ht'       => $devis->footer->total_ht,
                    'total_discount' => $devis->footer->total_discount,
                    'total_tax'      => $devis->footer->total_tax,
                    'total_ttc'      => $devis->footer->total_ttc,
                    'amount_paid'    => 0,
                    'amount_due'     => $devis->footer->total_ttc,
                ]);
            }

            $devis->update(['status' => 'converted']);

            $this->stockService->processDocument($bl);

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

        if ($bl->status !== 'confirmed') {
            return response()->json([
                'message' => 'Ce BL ne peut plus être confirmé. Statut actuel : ' . $bl->status,
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
                \App\Models\DocumentLine::insert($lignesData);
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

                // If payment is on credit, add total_ttc to the customer's encours_actuel
                if ($paymentMethod === 'credit' && $bl->thirdPartner_id && $bl->footer->total_ttc > 0) {
                    \App\Models\ThirdPartner::where('id', $bl->thirdPartner_id)
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

    private function generateReference(string $documentType): string
    {
        $incrementor = $this->incrementors
            ->all(orderBy: 'di_title')
            ->firstWhere('di_model', $documentType);

        if (!$incrementor) {
            $prefix = match ($documentType) {
                'DeliveryNote' => 'BL',
                'InvoiceSale'  => 'FAC',
                default        => 'DOC',
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
