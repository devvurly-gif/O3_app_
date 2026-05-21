<?php

namespace App\Http\Controllers\Api\Achats;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentHeaderRequest;
use App\Models\DocumentIncrementor;
use App\Services\DocumentHeaderService;
use App\Services\OcrInvoiceImportService;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OcrInvoiceController extends Controller
{
    public function __construct(
        private OcrInvoiceImportService $ocrService,
        private DocumentHeaderService $documentService,
        private StockMouvementService $stockService,
    ) {}

    /**
     * POST /api/achats/ocr/parse
     * Upload a PDF and return parsed invoice data (read-only, no DB writes).
     */
    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store('temp/ocr', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $result = $this->ocrService->process($fullPath);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse du PDF: ' . $e->getMessage(),
            ], 422);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    /**
     * POST /api/achats/ocr/confirm
     * Create missing supplier/products, then create InvoicePurchase.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_incrementor_id'  => ['nullable', 'integer', 'exists:document_incrementors,id'],
            'document_type'            => ['nullable', 'string'],
            'reference'                => ['nullable', 'string', 'max:100'],
            'thirdPartner_id'          => ['nullable', 'integer'],
            'warehouse_id'             => ['required', 'integer', 'exists:warehouses,id'],
            'issued_at'                => ['nullable', 'date'],
            'due_at'                   => ['nullable', 'date'],
            'notes'                    => ['nullable', 'string'],
            // Supplier data for auto-creation
            'supplier_data'            => ['nullable', 'array'],
            'supplier_data.name'       => ['nullable', 'string'],
            'supplier_data.ice'        => ['nullable', 'string'],
            'supplier_data.rc'         => ['nullable', 'string'],
            'supplier_data.if'         => ['nullable', 'string'],
            'supplier_data.patente'    => ['nullable', 'string'],
            // Lines
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.designation'      => ['required', 'string', 'max:255'],
            'lines.*.product_id'       => ['nullable', 'integer'],
            'lines.*.quantity'         => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_percent'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_type'        => ['nullable', 'string'],
            'lines.*.unit'             => ['nullable', 'string'],
            // Footer
            'footer'                   => ['nullable', 'array'],
            'footer.total_ht'          => ['nullable', 'numeric'],
            'footer.total_tax'         => ['nullable', 'numeric'],
            'footer.total_ttc'         => ['nullable', 'numeric'],
            'footer.payment_method'    => ['nullable', 'string'],
        ]);

        // 1. Create missing supplier if needed
        $supplierId = $validated['thirdPartner_id'] ?? null;
        $supplierCreated = false;

        if (empty($supplierId) && !empty($validated['supplier_data']['name'])) {
            $partner = $this->ocrService->createSupplier($validated['supplier_data']);
            $supplierId = $partner->id;
            $supplierCreated = true;
        }

        // 2. Create missing products per line
        $lines = $validated['lines'] ?? [];
        foreach ($lines as &$line) {
            if (empty($line['product_id']) && !empty($line['designation'])) {
                $product = $this->ocrService->createProduct($line);
                $line['product_id'] = $product->id;
            }

            $qty   = $line['quantity'] ?? 0;
            $price = $line['unit_price'] ?? 0;
            $disc  = $line['discount_percent'] ?? 0;
            $tax   = $line['tax_percent'] ?? 20;

            $lineHt  = $qty * $price * (1 - $disc / 100);
            $lineTax = $lineHt * $tax / 100;

            $line['total_ligne_ht'] = round($lineHt, 2);
            $line['total_tax']      = round($lineTax, 2);
            $line['total_ttc']      = round($lineHt + $lineTax, 2);
            $line['line_type']      = $line['line_type'] ?? 'product';
            $line['unit']           = $line['unit'] ?? 'pcs';
        }

        // 3. Build header
        $headerData = [
            'document_type'           => 'InvoicePurchase',
            'document_title'          => 'Facture Achat',
            'document_incrementor_id' => $validated['document_incrementor_id'] ?? null,
            'reference'               => $validated['reference'] ?? null,
            'thirdPartner_id'         => $supplierId,
            'warehouse_id'            => $validated['warehouse_id'] ?? null,
            'issued_at'               => $validated['issued_at'] ?? now()->toDateString(),
            'due_at'                  => $validated['due_at'] ?? null,
            'notes'                   => $validated['notes'] ?? null,
            'user_id'                 => auth()->id(),
        ];

        if (empty($headerData['document_incrementor_id'])) {
            $incrementor = DocumentIncrementor::where('di_model', 'InvoicePurchase')->first();
            if ($incrementor) {
                $headerData['document_incrementor_id'] = $incrementor->id;
            }
        }

        $footer = $validated['footer'] ?? [];
        $footer['amount_paid'] = $footer['amount_paid'] ?? 0;
        $footer['amount_due']  = $footer['amount_due']  ?? ($footer['total_ttc'] ?? 0);

        $document = DB::transaction(function () use ($headerData, $lines, $footer) {
            $doc = $this->documentService->createWithLinesAndFooter($headerData, $lines, $footer ?: null);

            $doc->update(['status' => 'confirmed']);

            $this->stockService->processDocument($doc);

            return $doc;
        });

        return response()->json([
            'success'          => true,
            'message'          => 'Facture d\'achat créée avec succès.',
            'supplier_created' => $supplierCreated,
            'data'             => $document->load(['thirdPartner', 'lignes.product', 'footer', 'user', 'warehouse']),
        ], 201);
    }
}
