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
            ['ctg_title' => 'Téléphones'],
            ['ctg_description' => 'Smartphones et téléphones mobiles']
        );
        $catAccess = Category::firstOrCreate(
            ['ctg_title' => 'Accessoires Téléphones'],
            ['ctg_description' => 'Câbles, chargeurs, étuis pour téléphones']
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
            [
                'product' => [
                    'p_title'         => 'Ordinateur Portable HP ProBook 450',
                    'p_description'   => 'Intel Core i5, 8GB RAM, 256GB SSD, 15.6" FHD',
                    'p_sku'           => 'HP-PB450-I5',
                    'p_ean13'         => '0194850834456',
                    'p_purchasePrice' => 4200.00,
                    'p_salePrice'     => 5500.00,
                    'p_cost'          => 4200.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catInfo?->id,
                    'brand_id'        => $brandHP?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [
                    ['title' => 'HP ProBook 450 - Face', 'altContent' => 'HP ProBook 450 G9', 'url' => 'products/hp-probook-450-front.jpg', 'isPrimary' => true],
                ],
            ],
            [
                'product' => [
                    'p_title'         => 'Ordinateur Portable Dell Latitude 5530',
                    'p_description'   => 'Intel Core i7, 16GB RAM, 512GB SSD, 15.6"',
                    'p_sku'           => 'DELL-LAT5530-I7',
                    'p_ean13'         => '0884116374498',
                    'p_purchasePrice' => 6500.00,
                    'p_salePrice'     => 8200.00,
                    'p_cost'          => 6500.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catInfo?->id,
                    'brand_id'        => $brandDell?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [
                    ['title' => 'Dell Latitude 5530', 'altContent' => 'Dell Latitude 5530', 'url' => 'products/dell-lat5530.jpg', 'isPrimary' => true],
                ],
            ],
            [
                'product' => [
                    'p_title'         => 'Imprimante Canon PIXMA G3420',
                    'p_description'   => "Imprimante jet d'encre multifonction, WiFi, Réservoir rechargeable",
                    'p_sku'           => 'CAN-PIXMA-G3420',
                    'p_ean13'         => '4549292179736',
                    'p_purchasePrice' => 1100.00,
                    'p_salePrice'     => 1450.00,
                    'p_cost'          => 1100.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catBureau?->id,
                    'brand_id'        => $brandCanon?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [
                    ['title' => 'Canon PIXMA G3420', 'altContent' => 'Canon PIXMA G3420', 'url' => 'products/canon-g3420.jpg', 'isPrimary' => true],
                ],
            ],
            [
                'product' => [
                    'p_title'         => 'Cartouche Canon PG-545',
                    'p_description'   => "Cartouche d'encre noire originale Canon",
                    'p_sku'           => 'CAN-INK-PG545',
                    'p_ean13'         => '4960999971698',
                    'p_purchasePrice' => 65.00,
                    'p_salePrice'     => 95.00,
                    'p_cost'          => 65.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catConsomm?->id,
                    'brand_id'        => $brandCanon?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Switch TP-Link TL-SG108 8 Ports',
                    'p_description'   => 'Switch Gigabit non géré 8 ports 10/100/1000 Mbps',
                    'p_sku'           => 'TPL-SG108',
                    'p_ean13'         => '6935364052836',
                    'p_purchasePrice' => 180.00,
                    'p_salePrice'     => 250.00,
                    'p_cost'          => 180.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catReseau?->id,
                    'brand_id'        => $brandTP?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Souris Logitech MX Master 3',
                    'p_description'   => 'Souris sans fil ergonomique, Bluetooth, 7 boutons',
                    'p_sku'           => 'LOG-MXM3',
                    'p_ean13'         => '5099206082731',
                    'p_purchasePrice' => 450.00,
                    'p_salePrice'     => 620.00,
                    'p_cost'          => 450.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catInfo?->id,
                    'brand_id'        => $brandLogi?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'Ramette Papier A4 80g',
                    'p_description'   => 'Papier blanc A4 80g/m², 500 feuilles',
                    'p_sku'           => 'PAP-A4-80G',
                    'p_ean13'         => '3130630016009',
                    'p_purchasePrice' => 35.00,
                    'p_salePrice'     => 55.00,
                    'p_cost'          => 35.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'ramette',
                    'p_status'        => true,
                    'category_id'     => $catConsomm?->id,
                    'brand_id'        => null,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
            [
                'product' => [
                    'p_title'         => 'ThinkPad Lenovo L15 Gen 3',
                    'p_description'   => 'AMD Ryzen 5, 16GB RAM, 512GB SSD, 15.6" FHD',
                    'p_sku'           => 'LEN-L15G3-R5',
                    'p_ean13'         => '0196035862336',
                    'p_purchasePrice' => 5800.00,
                    'p_salePrice'     => 7500.00,
                    'p_cost'          => 5800.00,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'category_id'     => $catInfo?->id,
                    'brand_id'        => $brandLenovo?->id,
                    'structure_id'    => $structure?->id,
                ],
                'images' => [],
            ],
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
