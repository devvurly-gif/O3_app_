<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\DocumentHeaderRepositoryInterface;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DocumentImportService
{
    public function __construct(
        private DocumentHeaderRepositoryInterface $documents,
        private DocumentLigneRepositoryInterface $lignes,
    ) {
    }

    /**
     * Import document lines from XLS file.
     * Expected columns: [SKU|EAN13|IMEI, Quantity, Unit Price (optional)]
     */
    public function importLines(string $filePath, int $documentId): array
    {
        $document = $this->documents->find($documentId);
        if (!$document) {
            abort(404, 'Document not found');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $created = [];
        $errors = [];
        $sortOrder = $document->lignes()->max('sort_order') ?? 0;

        foreach ($rows as $idx => $row) {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            $identifier = trim($row[0] ?? '');
            $quantity = (float) ($row[1] ?? 0);
            $unitPrice = (float) ($row[2] ?? 0);

            if (!$identifier || $quantity <= 0) {
                $errors[] = [
                    'row' => $idx + 1,
                    'error' => 'Missing identifier (SKU/EAN13/IMEI) or invalid quantity',
                ];
                continue;
            }

            // Search product by identifier
            $product = Product::where(function ($q) use ($identifier) {
                $q->where('p_sku', $identifier)
                    ->orWhere('p_ean13', $identifier)
                    ->orWhere('p_imei', $identifier);
            })->first();

            if (!$product) {
                $errors[] = [
                    'row' => $idx + 1,
                    'identifier' => $identifier,
                    'error' => 'Product not found',
                ];
                continue;
            }

            // Use provided price or default to product sale price
            $price = $unitPrice > 0 ? $unitPrice : $product->p_salePrice;

            try {
                $ligne = $this->lignes->createForDocument($document, [
                    'sort_order' => ++$sortOrder,
                    'product_id' => $product->id,
                    'line_type' => 'product',
                    'designation' => $product->p_title,
                    'reference' => $product->p_code,
                    'quantity' => $quantity,
                    'unit' => $product->p_unit ?? 'pcs',
                    'unit_price' => $price,
                    'tax_percent' => $product->p_taxRate ?? 20,
                    'discount_percent' => 0,
                ]);

                $created[] = [
                    'row' => $idx + 1,
                    'product_id' => $product->id,
                    'designation' => $product->p_title,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'ligne_id' => $ligne->id,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $idx + 1,
                    'identifier' => $identifier,
                    'error' => 'Failed to create line: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'created_count' => count($created),
            'error_count' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ];
    }
}
