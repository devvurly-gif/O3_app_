<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StructureIncrementor;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $structure  = StructureIncrementor::where('si_model', 'Product')->first();
        $catInfo    = Category::where('ctg_title', 'Informatique')->first();
        $catBureau  = Category::where('ctg_title', 'Bureautique')->first();
        $catConsomm = Category::where('ctg_title', 'Consommables')->first();
        $catReseau  = Category::where('ctg_title', 'Réseau & Télécom')->first();

        // Create/get phone categories
        $catPhone = Category::firstOrCreate(
            ['ctg_title' => 'Téléphones']
        );
        $catAccess = Category::firstOrCreate(
            ['ctg_title' => 'Accessoires Téléphones']
        );

        $brandHP     = Brand::where('br_title', 'HP')->first();
        $brandDell   = Brand::where('br_title', 'Dell')->first();
        $brandLenovo = Brand::where('br_title', 'Lenovo')->first();
        $brandCanon  = Brand::where('br_title', 'Canon')->first();
        $brandTP     = Brand::where('br_title', 'TP-Link')->first();
        $brandLogi   = Brand::where('br_title', 'Logitech')->first();

        // Create/get phone brands
        $brandApple = Brand::firstOrCreate(['br_title' => 'Apple']);
        $brandSamsung = Brand::firstOrCreate(['br_title' => 'Samsung']);
        $brandXiaomi = Brand::firstOrCreate(['br_title' => 'Xiaomi']);
        $brandHuawei = Brand::firstOrCreate(['br_title' => 'Huawei']);

        $products = [
            // Smartphones
            [
                'product' => [
                    'p_title'         => 'iPhone 15 Pro',
                    'p_description'   => 'Écran OLED 6.1" ProMotion 120Hz, A17 Pro, caméra 48MP',
                    'p_sku'           => null, // Will be auto-generated
                    'p_ean13'         => '194252092850',
                    'p_imei'          => '356649104847490',
                    'p_purchasePrice' => 850.00,
                    'p_salePrice'     => 1199.00,
                    'p_cost'          => 850.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'En stock, couleur titanium',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandApple->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'iPhone 15',
                    'p_description'   => 'Écran OLED 6.1", A16 Bionic, caméra 48MP',
                    'p_sku'           => null,
                    'p_ean13'         => '194252092935',
                    'p_imei'          => '356649104847491',
                    'p_purchasePrice' => 650.00,
                    'p_salePrice'     => 899.00,
                    'p_cost'          => 650.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Best-seller, stock important',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandApple->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Samsung Galaxy S24 Ultra',
                    'p_description'   => 'Écran AMOLED 6.8", Snapdragon 8 Gen 3, caméra 200MP',
                    'p_sku'           => null,
                    'p_ean13'         => '8806095695897',
                    'p_imei'          => '356649104847492',
                    'p_purchasePrice' => 900.00,
                    'p_salePrice'     => 1299.00,
                    'p_cost'          => 900.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Modèle flagship, couleur noire',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandSamsung->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Samsung Galaxy A54',
                    'p_description'   => 'Écran AMOLED 6.4", Exynos 1280, 50MP caméra',
                    'p_sku'           => null,
                    'p_ean13'         => '8806095694692',
                    'p_imei'          => '356649104847493',
                    'p_purchasePrice' => 250.00,
                    'p_salePrice'     => 399.00,
                    'p_cost'          => 250.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Bon rapport qualité-prix',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandSamsung->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Xiaomi 14',
                    'p_description'   => 'Écran AMOLED 6.36", Snapdragon 8 Gen 3, caméra Leica',
                    'p_sku'           => null,
                    'p_ean13'         => '6934177722893',
                    'p_imei'          => '356649104847494',
                    'p_purchasePrice' => 500.00,
                    'p_salePrice'     => 749.00,
                    'p_cost'          => 500.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Nouveau modèle 2024',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandXiaomi->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Xiaomi Redmi Note 13',
                    'p_description'   => 'Écran AMOLED 6.67", MediaTek Helio G99, 50MP',
                    'p_sku'           => null,
                    'p_ean13'         => '6934177722886',
                    'p_imei'          => '356649104847495',
                    'p_purchasePrice' => 150.00,
                    'p_salePrice'     => 249.00,
                    'p_cost'          => 150.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Très populaire, gros stock',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandXiaomi->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Huawei P60 Pro',
                    'p_description'   => 'Écran OLED 6.34", Kirin 9000S, caméra 48MP',
                    'p_sku'           => null,
                    'p_ean13'         => '6901443357532',
                    'p_imei'          => '356649104847496',
                    'p_purchasePrice' => 600.00,
                    'p_salePrice'     => 899.00,
                    'p_cost'          => 600.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Sans services Google',
                    'p_status'        => true,
                    'category_id'     => $catPhone->id,
                    'brand_id'        => $brandHuawei->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            // Accessories
            [
                'product' => [
                    'p_title'         => 'Câble USB-C 2m',
                    'p_description'   => 'Câble de charge rapide USB-C vers USB-C',
                    'p_sku'           => null,
                    'p_ean13'         => '5901234123456',
                    'p_imei'          => null,
                    'p_purchasePrice' => 5.00,
                    'p_salePrice'     => 12.99,
                    'p_cost'          => 5.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Stock: 200 unités',
                    'p_status'        => true,
                    'category_id'     => $catAccess->id,
                    'brand_id'        => null,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Chargeur 65W USB-C',
                    'p_description'   => 'Chargeur rapide 65W pour téléphones et laptops',
                    'p_sku'           => null,
                    'p_ean13'         => '5901234123457',
                    'p_imei'          => null,
                    'p_purchasePrice' => 15.00,
                    'p_salePrice'     => 34.99,
                    'p_cost'          => 15.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Universel, compatible tous appareils',
                    'p_status'        => true,
                    'category_id'     => $catAccess->id,
                    'brand_id'        => null,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Étui de protection TPU',
                    'p_description'   => 'Étui transparent avec absorption des chocs',
                    'p_sku'           => null,
                    'p_ean13'         => '5901234123458',
                    'p_imei'          => null,
                    'p_purchasePrice' => 3.00,
                    'p_salePrice'     => 8.99,
                    'p_cost'          => 3.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Disponible en plusieurs couleurs',
                    'p_status'        => true,
                    'category_id'     => $catAccess->id,
                    'brand_id'        => null,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Verre trempé protecteur',
                    'p_description'   => 'Protection écran verre trempé 9H',
                    'p_sku'           => null,
                    'p_ean13'         => '5901234123459',
                    'p_imei'          => null,
                    'p_purchasePrice' => 2.00,
                    'p_salePrice'     => 5.99,
                    'p_cost'          => 2.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_notes'         => 'Haute transparence, anti-empreintes',
                    'p_status'        => true,
                    'category_id'     => $catAccess->id,
                    'brand_id'        => null,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
        ];

        foreach ($products as $item) {
            // Skip products with null category_id or null structure
            if ($item['product']['category_id'] === null || $item['product']['structure_id'] === null) {
                continue;
            }

            $exists = Product::where('p_sku', $item['product']['p_sku'])->exists();

            if (! $exists) {
                $product = Product::create(array_merge($item['product'], [
                    'p_code' => $structure?->generateCode(),
                ]));

                foreach ($item['images'] as $image) {
                    ProductImage::firstOrCreate(
                        ['product_id' => $product->id, 'url' => $image['url']],
                        $image
                    );
                }

                $structure?->refresh();
            }
        }
    }
}
