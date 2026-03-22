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

        $brandHP     = Brand::where('br_title', 'HP')->first();
        $brandDell   = Brand::where('br_title', 'Dell')->first();
        $brandLenovo = Brand::where('br_title', 'Lenovo')->first();
        $brandCanon  = Brand::where('br_title', 'Canon')->first();
        $brandTP     = Brand::where('br_title', 'TP-Link')->first();
        $brandLogi   = Brand::where('br_title', 'Logitech')->first();

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
        ];

        foreach ($products as $item) {
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
