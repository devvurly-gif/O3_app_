<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportJadeverProducts extends Command
{
    protected $signature = 'import:jadever-accessories {tenant=jadeverfes : Tenant ID to import into}';
    protected $description = 'Import Jadever accessories products with images from jadevermall.com';

    private array $products = [
        [
            'sku' => 'JDASC1251',
            'title' => 'Angle grinder stand',
            'description' => 'For 100-125mm Angle Grinder, Aluminum Alloy Body, Cast Iron Base',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251125140149698/JDASC1251.jpg',
            'subcategory' => 'Supports & Fixations',
        ],
        [
            'sku' => 'JDGJ5515',
            'title' => 'Glue gun stick',
            'description' => 'Diameter: 11mm, Length: 15cm, With 7 pcs stick',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20230904140047661/JDGJ5515.jpg',
            'subcategory' => 'Colles & Adhesifs',
        ],
        [
            'sku' => 'JDDZ1A52-2',
            'title' => 'Earth auger bits',
            'description' => 'Auger bit length: 120mm x 80cm, Suitable for WDZ1A52-1',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20240116170413499/JDDZ1A52-2.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDSV0K11',
            'title' => 'Screwdriver bits PH2+SL6',
            'description' => 'PH2+SL6, 65mm, CR-V',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20260119110107981/JDSV0K11.jpg',
            'subcategory' => 'Embouts & Douilles',
        ],
        [
            'sku' => 'JDSV2K01',
            'title' => 'Screwdriver bit holder',
            'description' => '60mm, 2pcs/set, Packed by sliding card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20260119110107981/JDSV2K01.jpg',
            'subcategory' => 'Embouts & Douilles',
        ],
        [
            'sku' => 'JDSV3K01',
            'title' => 'Screwdriver bit holder with release',
            'description' => '60mm, Quick release function, 2pcs/set',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20260119110107981/JDSV3K01.jpg',
            'subcategory' => 'Embouts & Douilles',
        ],
        [
            'sku' => 'JDMJ1K07',
            'title' => 'Masonry drill bit 8x120mm',
            'description' => '8x120mm, High quality TCT tip, Cylindrical shank',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20240516160141111/JDMJ1K07.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDMJ6B08',
            'title' => '8 Pcs masonry drill bits set',
            'description' => 'Sizes: 3x60, 4x75, 5x85, 6x100, 7x100, 8x120, 9x120, 10x120mm',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251027200140374/JDMJ6B08.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDTD6B01',
            'title' => '9 Pcs drill bits set',
            'description' => '3 Pcs: 5mm, 6mm, 8mm, Plastic box packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20231010140052671/JDTD6B01.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDLZ1K06',
            'title' => 'Tile and glass drill bit 8x83mm',
            'description' => '8x83mm, High quality TCT tip, Blister card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241115160213763/JDLZ1K06.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDLZ1K03',
            'title' => 'Tile and glass drill bit 10x90mm',
            'description' => '10x90mm, High quality TCT tip, Blister card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241115160213763/JDLZ1K03.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDSJ3K01',
            'title' => '5pcs wood drill bits set',
            'description' => 'Sizes: 3x61, 4x75, 5x86, 6x93, 8x117mm, Blister card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241129110243519/JDSJ3K01.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDTD3K01',
            'title' => '7Pcs HSS twist drill bits set',
            'description' => 'Sizes: 2x49, 3x61, 4x75, 5x86, 6x93, 8x117mm, Blister card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241128180336950/JDTD3K01.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDJD1401',
            'title' => 'Step drill bit 4-12mm',
            'description' => 'Size: 4-12mm, 2mm/step, Double blister packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241128180336950/JDJD1401.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDJD1402',
            'title' => 'Step drill bit 4-20mm',
            'description' => 'Size: 4-20mm, 2mm/step, Double blister packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241128180336950/JDJD1402.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDJD1403',
            'title' => 'Step drill bit 4-32mm',
            'description' => 'Size: 4-32mm, 2mm/step, Double blister packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241128180336950/JDJD1403.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDJD3401',
            'title' => '3 Pcs step drill bit set',
            'description' => 'HSS material, Double blister packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241128180336950/JDJD3401.jpg',
            'subcategory' => 'Forets & Meches',
        ],
        [
            'sku' => 'JDYL1301',
            'title' => 'Flap disc P40',
            'description' => '115mm x 22.2mm, P40 grit, Label packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20240520110203249/JDYL1301.jpg',
            'subcategory' => 'Disques & Abrasifs',
        ],
        [
            'sku' => 'JDYL1302',
            'title' => 'Flap disc P60',
            'description' => '115mm x 22.2mm, P60 grit, Label packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20240520110203249/JDYL1302.jpg',
            'subcategory' => 'Disques & Abrasifs',
        ],
        [
            'sku' => 'JDYL1303',
            'title' => 'Flap disc P80',
            'description' => '115mm x 22.2mm, P80 grit, Label packaging',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20240520110203249/JDYL1303.jpg',
            'subcategory' => 'Disques & Abrasifs',
        ],
        [
            'sku' => 'JDCE1401',
            'title' => 'Wire cup brush 75mm',
            'description' => 'Diameter: 75mm (3"), Thread: M14x2, Color box',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251027200140374/JDCE1401.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
        [
            'sku' => 'JDCE1402',
            'title' => 'Wire cup brush 100mm',
            'description' => 'Diameter: 100mm (4"), Thread: M14x2, Color box',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251027200140374/JDCE1402.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
        [
            'sku' => 'JDCE2401',
            'title' => 'Wire cup brush twisted 75mm',
            'description' => 'Diameter: 75mm (3"), Thread: M14x2, Color box',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251027200140374/JDCE2401.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
        [
            'sku' => 'JDCE2402',
            'title' => 'Wire cup brush twisted 100mm',
            'description' => 'Diameter: 100mm (4"), Thread: M14x2, Color box',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20251027200140374/JDCE2402.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
        [
            'sku' => 'JDCE6401',
            'title' => '5 Pcs wire brush set',
            'description' => '2 circular (2", 3"), 2 cup (2", 3"), 1 pencil (1"), Sliding card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241105180244864/JDCE6401.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
        [
            'sku' => 'JDCE6402',
            'title' => '3 Pcs wire brush set',
            'description' => '1 circular (2"), 1 cup (2"), 1 pencil (1"), Sliding card',
            'image' => 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20241105180244864/JDCE6402.jpg',
            'subcategory' => 'Brosses Metalliques',
        ],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $this->info("Importing 26 Jadever accessories into tenant '{$tenant->name}'...");

        $tenant->run(function () {
            // Ensure storage link exists
            if (!is_dir(storage_path('app/public/products'))) {
                mkdir(storage_path('app/public/products'), 0755, true);
            }

            // Create brand
            $brand = Brand::firstOrCreate(
                ['br_code' => 'JADEVER'],
                ['br_title' => 'JADEVER', 'br_status' => true]
            );
            $this->info("Brand: JADEVER (ID: {$brand->id})");

            // Create parent category
            $parentCategory = Category::firstOrCreate(
                ['ctg_code' => 'ACCESSORIES'],
                ['ctg_title' => 'Accessoires Outillage', 'ctg_status' => true]
            );

            // Sub-categories map
            $subCategories = [];

            $imported = 0;
            $skipped = 0;

            foreach ($this->products as $item) {
                // Check if already exists
                if (Product::where('p_sku', $item['sku'])->exists()) {
                    $this->warn("  SKIP: {$item['sku']} already exists.");
                    $skipped++;
                    continue;
                }

                // Get or create sub-category
                $subCatCode = Str::upper(Str::slug($item['subcategory'], '_'));
                if (!isset($subCategories[$subCatCode])) {
                    $subCategories[$subCatCode] = Category::firstOrCreate(
                        ['ctg_code' => $subCatCode],
                        ['ctg_title' => $item['subcategory'], 'ctg_status' => true]
                    );
                }
                $category = $subCategories[$subCatCode];

                // Create product
                $product = Product::create([
                    'p_title'       => $item['title'],
                    'p_code'        => $item['sku'],
                    'p_sku'         => $item['sku'],
                    'p_description' => $item['description'],
                    'p_long_description' => $item['description'],
                    'p_salePrice'   => 0,
                    'p_purchasePrice' => 0,
                    'p_cost'        => 0,
                    'p_taxRate'     => 20,
                    'p_unit'        => 'pcs',
                    'p_status'      => true,
                    'p_slug'        => Str::slug($item['title'] . '-' . $item['sku']),
                    'is_ecom'       => true,
                    'category_id'   => $category->id,
                    'brand_id'      => $brand->id,
                ]);

                // Download and save image
                $imageUrl = $item['image'];
                try {
                    $response = Http::timeout(15)->get($imageUrl);
                    if ($response->successful()) {
                        $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                        $filename = "products/{$item['sku']}.{$extension}";
                        Storage::disk('public')->put($filename, $response->body());

                        ProductImage::create([
                            'product_id' => $product->id,
                            'title'      => $item['title'],
                            'altContent' => $item['title'],
                            'url'        => $filename,
                            'isPrimary'  => true,
                        ]);
                        $this->info("  OK: {$item['sku']} — {$item['title']} (image saved)");
                    } else {
                        $this->warn("  OK: {$item['sku']} — {$item['title']} (image download failed: HTTP {$response->status()})");
                    }
                } catch (\Exception $e) {
                    $this->warn("  OK: {$item['sku']} — {$item['title']} (image error: {$e->getMessage()})");
                }

                $imported++;
            }

            $this->newLine();
            $this->info("Done! Imported: {$imported}, Skipped: {$skipped}");
        });

        return 0;
    }
}
