<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentHeaderRequest;
use App\Http\Requests\UpdateDocumentHeaderRequest;
use App\Models\DocumentHeader;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Services\CacheService;
use App\Services\DocumentHeaderService;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentHeaderController extends Controller
{
    public function __construct(
        private DocumentHeaderRepositoryInterface $documents,
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
    ) {
    }

    private const DOMAIN_TYPES = [
        'stock'     => ['StockEntry', 'StockExit', 'StockAdjustmentNote', 'StockTransfer'],
        'purchases' => ['PurchaseOrder', 'InvoicePurchase', 'ReceiptNotePurchase', 'CreditNotePurchase', 'ReturnPurchase'],
        'sales'     => ['QuoteSale', 'CustomerOrder', 'DeliveryNote', 'InvoiceSale', 'TicketSale', 'CreditNoteSale', 'ReturnSale'],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = DocumentHeader::with(['thirdPartner', 'user', 'warehouse', 'footer']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('document_title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('domain') && isset(self::DOMAIN_TYPES[$request->domain])) {
            $query->whereIn('document_type', self::DOMAIN_TYPES[$request->domain]);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        // Filtrer par tiers : l'ecran des achats s'en sert pour ne montrer que
        // les bons d'un fournisseur avant de les facturer ensemble.
        if ($request->filled('thirdPartner_id')) {
            $query->where('thirdPartner_id', $request->thirdPartner_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->orderBy(
                $request->input('sort', 'issued_at'),
                $request->input('order', 'desc')
            )->paginate((int) $request->input('per_page', 15))
        );
    }

    public function store(StoreDocumentHeaderRequest $request): JsonResponse
    {
        $headerData = $request->headerData();
        $headerData['user_id'] = $request->user()->id;

        $document = $this->documentService->createWithLinesAndFooter(
            $headerData,
            $request->linesData(),
            $request->footerData()
        );

        // Stock movements for BL / BR: pending if draft, applied if confirmed
        if (in_array($document->document_type, ['DeliveryNote', 'ReceiptNotePurchase']) && $document->warehouse_id) {
            $isPending = in_array($document->status, ['draft', 'pending']);
            $this->stockService->processDocument($document, pending: $isPending);
        }

        // Invoices created directly (not from BL/BR) → impact stock
        $isDirectInvoice = in_array($document->document_type, ['InvoiceSale', 'InvoicePurchase'])
            && !$document->parent_id;

        if ($isDirectInvoice && $document->warehouse_id) {
            $this->stockService->processDocument($document);
        }

        CacheService::flushDocuments();

        return response()->json(
            $document->load(['thirdPartner', 'user', 'warehouse', 'lignes.product', 'footer']),
            201
        );
    }

    public function show(DocumentHeader $documentHeader): JsonResponse
    {
        return response()->json(
            $documentHeader->load(['thirdPartner', 'user', 'warehouse', 'lignes.product', 'footer', 'payments', 'parent:id,reference,document_type', 'children:id,reference,document_type,parent_id'])
        );
    }

    /**
     * Statuses past which a document has left the draft stage: its stock has
     * been applied and its numbering is committed. Mirrors destroy().
     */
    private const COMMITTED_STATUSES = ['confirmed', 'delivered', 'received', 'pending', 'paid', 'partial'];

    public function update(UpdateDocumentHeaderRequest $request, DocumentHeader $documentHeader): JsonResponse
    {
        $statusBefore = $documentHeader->status;

        // A committed document is not cancelled, it is returned: the goods
        // physically moved and the reversal belongs on its own document, with
        // its own reference and trail. Same rule destroy() already applies.
        if ($request->input('status') === 'cancelled' && in_array($statusBefore, self::COMMITTED_STATUSES, true)) {
            return response()->json([
                'message' => 'Un document confirmé ne peut pas être annulé. ' . $this->reversalHint($documentHeader),
            ], 422);
        }

        $this->documents->update($documentHeader, $request->headerData());

        $linesData = $request->linesData();
        if ($linesData !== null) {
            $documentHeader->lignes()->delete();
            foreach ($linesData as $i => $lineData) {
                $documentHeader->lignes()->create(array_merge($lineData, ['sort_order' => $i + 1]));
            }
        }

        $footerData = $request->footerData();
        if ($footerData) {
            $existingFooter = $documentHeader->footer;
            if ($existingFooter) {
                $existingFooter->update($footerData);
            } else {
                $documentHeader->footer()->create($footerData);
            }
        }

        // Cancelling has to give the stock back. Every "Annuler" button in the
        // app comes through here (PATCH /documents/{id} with a status), and
        // this used to flip the flag only: a cancelled BR kept crediting its
        // received quantity to the warehouse, a cancelled BL kept its exit.
        // cancelDocumentMovements() cancels pending movements and writes
        // compensating entries for applied ones; it is a no-op for a document
        // that never moved stock, and idempotent on re-cancellation.
        if ($statusBefore !== 'cancelled' && $documentHeader->fresh()->status === 'cancelled') {
            $this->stockService->cancelDocumentMovements($documentHeader);
        }

        CacheService::flushDocuments();

        return response()->json(
            $documentHeader->fresh(['thirdPartner', 'user', 'warehouse', 'lignes.product', 'footer', 'payments'])
        );
    }

    /**
     * How to undo this document now that it is committed.
     */
    private function reversalHint(DocumentHeader $documentHeader): string
    {
        return match (true) {
            in_array($documentHeader->document_type, ['DeliveryNote', 'InvoiceSale'])
                => 'Créez un Retour Client à la place.',
            in_array($documentHeader->document_type, ['ReceiptNotePurchase', 'InvoicePurchase'])
                => 'Créez un Retour Fournisseur à la place.',
            default => '',
        };
    }

    public function destroy(DocumentHeader $documentHeader): JsonResponse
    {
        // Confirmed documents cannot be deleted — must use return documents
        if (in_array($documentHeader->status, self::COMMITTED_STATUSES)) {
            return response()->json([
                'message' => 'Un document confirmé ne peut pas être supprimé. ' . $this->reversalHint($documentHeader),
            ], 422);
        }

        // Draft/cancelled documents: cancel any pending stock movements first
        if (in_array($documentHeader->document_type, ['DeliveryNote', 'ReceiptNotePurchase'])) {
            $this->stockService->cancelDocumentMovements($documentHeader);
        }

        $this->documents->delete($documentHeader);
        CacheService::flushDocuments();

        return response()->json(null, 204);
    }
}
