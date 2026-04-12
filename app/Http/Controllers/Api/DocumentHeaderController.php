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
        'sales'     => ['QuoteSale', 'CustomerOrder', 'DeliveryNote', 'InvoiceSale', 'CreditNoteSale', 'ReturnSale'],
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

        // Stock movements for BL: create as pending (applied on confirmation)
        if ($document->document_type === 'DeliveryNote' && $document->warehouse_id) {
            $isPending = in_array($document->status, ['draft', 'pending']);
            $this->stockService->processDocument($document, pending: $isPending);
        }

        // Stock movements for BR (always impact stock immediately)
        if ($document->document_type === 'ReceiptNotePurchase' && $document->warehouse_id) {
            $this->stockService->processDocument($document);
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

    public function update(UpdateDocumentHeaderRequest $request, DocumentHeader $documentHeader): JsonResponse
    {
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

        CacheService::flushDocuments();

        return response()->json(
            $documentHeader->fresh(['thirdPartner', 'user', 'warehouse', 'lignes.product', 'footer', 'payments'])
        );
    }

    public function destroy(DocumentHeader $documentHeader): JsonResponse
    {
        $this->documents->delete($documentHeader);
        CacheService::flushDocuments();

        return response()->json(null, 204);
    }
}
