<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StructureIncrementor;
use App\Models\ThirdPartner;
use Illuminate\Support\Str;

class OcrInvoiceImportService
{
    public function __construct(
        private OcrExtractorService $extractor,
        private InvoiceParserService $parser,
    ) {}

    /**
     * Parse a PDF file: extract text, parse invoice, match supplier/products (read-only).
     */
    public function process(string $filePath): array
    {
        $extraction = $this->extractor->extract($filePath);
        $parsed = $this->parser->parse($extraction['text']);

        $supplierResult = $this->matchSupplier($parsed['supplier']);

        $lines = array_map(function ($line) {
            $productResult = $this->matchProduct($line);
            return array_merge($line, [
                'product_id'    => $productResult['id'],
                'product_match' => $productResult['name'],
                'match_score'   => $productResult['score'],
            ]);
        }, $parsed['lines']);

        return [
            'extraction_method' => $extraction['method'],
            'text_length'       => $extraction['length'],
            'supplier'          => array_merge($parsed['supplier'], [
                'matched_id'    => $supplierResult['id'],
                'matched_name'  => $supplierResult['name'],
                'match_score'   => $supplierResult['score'],
            ]),
            'invoice_number'    => $parsed['invoice_number'],
            'invoice_date'      => $parsed['invoice_date'],
            'due_date'          => $parsed['due_date'],
            'lines'             => $lines,
            'totals'            => $parsed['totals'],
            'price_type'        => $parsed['price_type'],
            'confidence'        => $parsed['confidence'],
            'raw_text'          => $parsed['raw_text'],
        ];
    }

    /**
     * Create missing supplier and products before confirm (write step).
     */
    public function createMissing(array $ocrPayload): array
    {
        $supplierId = $ocrPayload['supplier_id'] ?? null;
        $supplierCreated = false;

        // Create supplier if not matched
        if (empty($supplierId) && !empty($ocrPayload['supplier_data'])) {
            $partner = $this->createSupplier($ocrPayload['supplier_data']);
            $supplierId = $partner->id;
            $supplierCreated = true;
        }

        $lines = $ocrPayload['lines'] ?? [];
        foreach ($lines as &$line) {
            if (empty($line['product_id']) && !empty($line['designation'])) {
                $product = $this->createProduct($line);
                $line['product_id'] = $product->id;
                $line['product_created'] = true;
            }
        }

        return [
            'supplier_id'      => $supplierId,
            'supplier_created' => $supplierCreated,
            'lines'            => $lines,
        ];
    }

    /**
     * Match supplier against existing ThirdPartner records (read-only).
     */
    private function matchSupplier(array $supplierData): array
    {
        if (!empty($supplierData['ice'])) {
            $partner = ThirdPartner::where('tp_Ice_Number', $supplierData['ice'])
                ->whereIn('tp_Role', ['supplier', 'both'])
                ->first();

            if ($partner) {
                return ['id' => $partner->id, 'name' => $partner->tp_title, 'score' => 1.0];
            }
        }

        if (!empty($supplierData['name'])) {
            $name = $supplierData['name'];

            $partner = ThirdPartner::where('tp_title', $name)
                ->whereIn('tp_Role', ['supplier', 'both'])
                ->first();

            if ($partner) {
                return ['id' => $partner->id, 'name' => $partner->tp_title, 'score' => 1.0];
            }

            $escapedName = str_replace(['%', '_'], ['\\%', '\\_'], $name);
            $partner = ThirdPartner::where('tp_title', 'like', "%{$escapedName}%")
                ->whereIn('tp_Role', ['supplier', 'both'])
                ->first();

            if ($partner) {
                return ['id' => $partner->id, 'name' => $partner->tp_title, 'score' => 0.7];
            }

            $words = array_filter(explode(' ', $name), fn($w) => mb_strlen($w) > 3);
            foreach ($words as $word) {
                $escapedWord = str_replace(['%', '_'], ['\\%', '\\_'], $word);
                $partner = ThirdPartner::where('tp_title', 'like', "%{$escapedWord}%")
                    ->whereIn('tp_Role', ['supplier', 'both'])
                    ->first();

                if ($partner) {
                    return ['id' => $partner->id, 'name' => $partner->tp_title, 'score' => 0.4];
                }
            }
        }

        return ['id' => null, 'name' => null, 'score' => 0];
    }

    /**
     * Match a line item against existing Product records (read-only).
     */
    private function matchProduct(array $lineData): array
    {
        $designation = $lineData['designation'] ?? '';
        if (empty($designation)) {
            return ['id' => null, 'name' => null, 'score' => 0];
        }

        $refCode = null;
        $titlePart = $designation;
        if (preg_match('/^([A-Z0-9][A-Z0-9_\-]{2,})\s+(.+)$/i', $designation, $m)) {
            $refCode = $m[1];
            $titlePart = $m[2];
        }

        if ($refCode) {
            $product = Product::where('p_sku', $refCode)->orWhere('p_code', $refCode)->first();
            if ($product) {
                return ['id' => $product->id, 'name' => $product->p_title, 'score' => 1.0];
            }
        }

        $product = Product::where('p_sku', $designation)->first();
        if ($product) {
            return ['id' => $product->id, 'name' => $product->p_title, 'score' => 1.0];
        }

        $product = Product::where('p_title', $designation)->first();
        if ($product) {
            return ['id' => $product->id, 'name' => $product->p_title, 'score' => 1.0];
        }

        if ($titlePart !== $designation) {
            $product = Product::where('p_title', $titlePart)->first();
            if ($product) {
                return ['id' => $product->id, 'name' => $product->p_title, 'score' => 0.9];
            }
        }

        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $designation);
        $product = Product::where('p_title', 'like', "%{$escaped}%")->first();
        if ($product) {
            return ['id' => $product->id, 'name' => $product->p_title, 'score' => 0.7];
        }

        $words = array_filter(explode(' ', $designation), fn($w) => mb_strlen($w) > 3);
        if (!empty($words)) {
            $query = Product::query();
            foreach ($words as $word) {
                $escapedWord = str_replace(['%', '_'], ['\\%', '\\_'], $word);
                $query->where('p_title', 'like', "%{$escapedWord}%");
            }
            $product = $query->first();
            if ($product) {
                return ['id' => $product->id, 'name' => $product->p_title, 'score' => 0.4];
            }
        }

        return ['id' => null, 'name' => null, 'score' => 0];
    }

    /**
     * Create a new supplier from OCR data.
     */
    public function createSupplier(array $data): ThirdPartner
    {
        $structure = StructureIncrementor::where('si_model', 'ThirdPartner')->first();

        return ThirdPartner::create([
            'tp_title'          => $data['name'],
            'tp_code'           => $structure?->generateCode() ?? ('FRS-' . Str::random(6)),
            'tp_Ice_Number'     => $data['ice'] ?? null,
            'tp_Rc_Number'      => $data['rc'] ?? null,
            'tp_IdenFiscal'     => $data['if'] ?? null,
            'tp_patente_Number' => $data['patente'] ?? null,
            'tp_Role'           => 'supplier',
            'tp_status'         => true,
            'structure_id'      => $structure?->id,
        ]);
    }

    /**
     * Create a new product from OCR line data.
     */
    public function createProduct(array $lineData): Product
    {
        $structure = StructureIncrementor::where('si_model', 'Product')->first();

        $designation = $lineData['designation'] ?? '';

        $defaultCategory = Category::firstOrCreate(
            ['ctg_title' => 'Général'],
            ['ctg_code' => 'GEN', 'ctg_status' => true, 'structure_id' => $structure?->id],
        );
        $defaultBrand = Brand::firstOrCreate(
            ['br_title' => 'Générique'],
            ['br_code' => 'GEN', 'br_status' => true, 'structure_id' => $structure?->id],
        );

        $category = $this->guessCategory($designation) ?? $defaultCategory;

        $refCode = null;
        $titlePart = $designation;
        if (preg_match('/^([A-Z0-9][A-Z0-9_\-]{2,})\s+(.+)$/i', $designation, $m)) {
            $refCode = $m[1];
            $titlePart = $m[2];
        }

        return Product::create([
            'p_title'         => $titlePart,
            'p_code'          => $structure?->generateCode() ?? ('PRD-' . Str::random(6)),
            'p_sku'           => $refCode,
            'p_purchasePrice' => $lineData['unit_price'] ?? 0,
            'p_salePrice'     => round(($lineData['unit_price'] ?? 0) * 1.3, 2),
            'p_cost'          => $lineData['unit_price'] ?? 0,
            'p_taxRate'       => $lineData['tax_percent'] ?? 20,
            'p_unit'          => 'pièce',
            'p_status'        => true,
            'category_id'     => $category->id,
            'brand_id'        => $defaultBrand->id,
            'structure_id'    => $structure?->id,
        ]);
    }

    private function guessCategory(string $designation): ?Category
    {
        $keywordMap = [
            'cable|fil\b|câble|fibre|rouleau|gaine|tube\b|conduit|disjoncteur|interrupteur|prise\b|tableau' => 'Électronique',
            'switch|routeur|router|modem|rj45|patch|baie|rack' => 'Réseau & Télécom',
            'encre|toner|cartouche|papier|ramette|stylo|classeur|chemise|enveloppe|agrafeuse' => 'Fournitures de Bureau',
            'imprimante|scanner|photocopieur|copie' => 'Bureautique',
            'pc\b|ordinateur|laptop|écran|souris|clavier|disque|ssd\b|ram\b|processeur|usb|chargeur' => 'Informatique',
            'bureau|chaise|armoire|étagère|caisson|meuble' => 'Mobilier',
            'téléphone|smartphone|iphone|samsung|coque|protection' => 'Téléphones',
            'camera|caméra|alarme|detecteur|détecteur|video|vidéo|surveillance' => 'Sécurité',
        ];

        foreach ($keywordMap as $pattern => $catTitle) {
            if (preg_match('/(?:' . $pattern . ')/iu', $designation)) {
                $matched = Category::where('ctg_title', $catTitle)->where('ctg_status', true)->first();
                if ($matched) {
                    return $matched;
                }
            }
        }

        return null;
    }
}
