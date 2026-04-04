<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportElectrosalamProducts extends Command
{
    protected $signature = 'tenant:import-electrosalam {tenant_id} {--dry-run}';
    protected $description = 'Import smartphones from electrosalam.ma into a tenant database';

    private array $products = [
        ['name' => 'Samsung Galaxy A07 (4/64)', 'price' => 999, 'old_price' => 1500, 'brand' => 'Samsung', 'specs' => '4GB/64GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ccojet_7.png?v=1767608090', 'condition' => 'Neuf'],
        ['name' => 'Redmi A5 (3/64)', 'price' => 1049, 'old_price' => 1299, 'brand' => 'Xiaomi', 'specs' => '3GB/64GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/vvet_4.png?v=1767607804', 'condition' => 'Neuf'],
        ['name' => 'Tecno Spark 30C (4/128)', 'price' => 1149, 'old_price' => 1599, 'brand' => 'Tecno', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/Nouveau_prxxxxt.png?v=1767627710', 'condition' => 'Neuf'],
        ['name' => 'HONOR X5b Plus (4/128)', 'price' => 1179, 'old_price' => 1599, 'brand' => 'Honor', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/9jet.png?v=1767610737', 'condition' => 'Neuf'],
        ['name' => 'Redmi A5 (4/128)', 'price' => 1199, 'old_price' => 1299, 'brand' => 'Xiaomi', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ccet_3.png?v=1767610421', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A07 (4/128)', 'price' => 1249, 'old_price' => 1899, 'brand' => 'Samsung', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/Design_sans_titre_27.png?v=1770310542', 'condition' => 'Neuf'],
        ['name' => 'Redmi 15C (4/128)', 'price' => 1299, 'old_price' => 1899, 'brand' => 'Xiaomi', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ccet_4.png?v=1767611621', 'condition' => 'Neuf'],
        ['name' => 'HONOR X6C (6/128)', 'price' => 1399, 'old_price' => 1599, 'brand' => 'Honor', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/VVjet_1.png?v=1767611983', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A07 (6/128)', 'price' => 1449, 'old_price' => 1899, 'brand' => 'Samsung', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/Design_sans_titre_30.png?v=1770312401', 'condition' => 'Neuf'],
        ['name' => 'HONOR X6C (6/256)', 'price' => 1499, 'old_price' => 1599, 'brand' => 'Honor', 'specs' => '6GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/xxojet.png?v=1767612437', 'condition' => 'Neuf'],
        ['name' => 'Redmi 15C (8/256)', 'price' => 1599, 'old_price' => 2199, 'brand' => 'Xiaomi', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ffjet.png?v=1767615259', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A17 (4/128)', 'price' => 1679, 'old_price' => 2399, 'brand' => 'Samsung', 'specs' => '4GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/vvvojet.png?v=1767614696', 'condition' => 'Neuf'],
        ['name' => 'Redmi 15 5G (6/128)', 'price' => 1689, 'old_price' => 2299, 'brand' => 'Xiaomi', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ccojet_9.png?v=1767615946', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A16 (6/128)', 'price' => 1829, 'old_price' => 2200, 'brand' => 'Samsung', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/vt_1.png?v=1767616194', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A17 5G (4/128)', 'price' => 1879, 'old_price' => 2599, 'brand' => 'Samsung', 'specs' => '4GB/128GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/Design_sans_titre_29.png?v=1770311780', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A17 (6/128)', 'price' => 1889, 'old_price' => 2899, 'brand' => 'Samsung', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/Nccet.png?v=1767616579', 'condition' => 'Neuf'],
        ['name' => 'HONOR X7d (8/256)', 'price' => 1979, 'old_price' => 2499, 'brand' => 'Honor', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/120_ojet_6.png?v=1767446025', 'condition' => 'Neuf'],
        ['name' => 'Xiaomi Redmi Note 15 (6/128)', 'price' => 1979, 'old_price' => 2599, 'brand' => 'Xiaomi', 'specs' => '6GB/128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/46262624.png?v=1769255423', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A16 (8/256)', 'price' => 2099, 'old_price' => 2599, 'brand' => 'Samsung', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/vvet_6.png?v=1767618684', 'condition' => 'Neuf'],
        ['name' => 'Xiaomi Redmi Note 15 (8/256)', 'price' => 2249, 'old_price' => 2899, 'brand' => 'Xiaomi', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/346346.png?v=1769255091', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A17 (8/256)', 'price' => 2349, 'old_price' => 3049, 'brand' => 'Samsung', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/Nocc.png?v=1767619232', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A17 5G (8/256)', 'price' => 2449, 'old_price' => 3199, 'brand' => 'Samsung', 'specs' => '8GB/256GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/Design_sans_titre_29.png?v=1770311780', 'condition' => 'Neuf'],
        ['name' => 'Xiaomi Redmi Note 15 Pro (8/256)', 'price' => 2889, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/22425523_c636a524-1ceb-444b-bcef-24151bd14e63.png?v=1769255812', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A26 5G (6/128)', 'price' => 2979, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '6GB/128GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/vvjet_3.png?v=1767619864', 'condition' => 'Neuf'],
        ['name' => 'Redmi Note 14 Pro (12/512)', 'price' => 3199, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '12GB/512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ojet_e01be41b-63da-443e-b783-305814248361.png?v=1767620377', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A26 5G (8/256)', 'price' => 3199, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '8GB/256GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/ccrojet_8.png?v=1767620141', 'condition' => 'Neuf'],
        ['name' => 'iPhone 13 128GB', 'price' => 3449, 'old_price' => null, 'brand' => 'Apple', 'specs' => '128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/7jet.png?v=1767290741', 'condition' => 'Occasion'],
        ['name' => 'Xiaomi Redmi Note 15 Pro (12/512)', 'price' => 3449, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '12GB/512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/232324_f50dfe10-f111-4e87-826c-9999cbd243cd.png?v=1769256925', 'condition' => 'Neuf'],
        ['name' => 'Redmi Note 14 Pro+ (8/256)', 'price' => 3749, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '8GB/256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/11_-_2025-03-11T113834.246.png?v=1741693194', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A36 5G (8/256)', 'price' => 3779, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '8GB/256GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/CCjet_7.png?v=1767620800', 'condition' => 'Neuf'],
        ['name' => 'iPhone 14 128GB', 'price' => 3799, 'old_price' => null, 'brand' => 'Apple', 'specs' => '128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ld0005977210_1_3.jpg?v=1748347078', 'condition' => 'Occasion'],
        ['name' => 'Samsung Galaxy A56 5G (8/128)', 'price' => 3999, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '8GB/128GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/Vet.png?v=1767621229', 'condition' => 'Neuf'],
        ['name' => 'Redmi Note 14 Pro+ (12/512)', 'price' => 4249, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '12GB/512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/CCt_2.png?v=1767627010', 'condition' => 'Neuf'],
        ['name' => 'iPhone 13 Pro 128GB', 'price' => 4499, 'old_price' => null, 'brand' => 'Apple', 'specs' => '128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/10et_54.webp?v=1765303383', 'condition' => 'Occasion'],
        ['name' => 'iPhone 14 Plus 128GB', 'price' => 4599, 'old_price' => null, 'brand' => 'Apple', 'specs' => '128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ld0005977366_3b8ffc69-a25e-4ec8-98cf-8ed74d9b3295.jpg?v=1756310332', 'condition' => 'Occasion'],
        ['name' => 'Redmi Note 15 Pro+ 5G (12/512)', 'price' => 4599, 'old_price' => null, 'brand' => 'Xiaomi', 'specs' => '12GB/512GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/Design_sans_titre_32.png?v=1770808289', 'condition' => 'Neuf'],
        ['name' => 'Samsung Galaxy A56 5G (8/256)', 'price' => 4649, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '8GB/256GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/DDt.png?v=1767621675', 'condition' => 'Neuf'],
        ['name' => 'iPhone 13 Pro 256GB', 'price' => 4749, 'old_price' => null, 'brand' => 'Apple', 'specs' => '256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ld0005930760_1_1.jpg?v=1772621887', 'condition' => 'Occasion'],
        ['name' => 'Samsung Galaxy S25 (12/128)', 'price' => 4899, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '12GB/128GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/10t_-_2025-11-15T143726.228.png?v=1763213933', 'condition' => 'Comme Neuf'],
        ['name' => 'iPhone 13 Pro 512GB', 'price' => 4949, 'old_price' => null, 'brand' => 'Apple', 'specs' => '512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/xxNouveau_p_jet.png?v=1765203249', 'condition' => 'Occasion'],
        ['name' => 'iPhone 15 128GB', 'price' => 5399, 'old_price' => null, 'brand' => 'Apple', 'specs' => '128GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/No_120et_4.png?v=1762442789', 'condition' => 'Comme Neuf'],
        ['name' => 'iPhone 13 Pro Max 512GB', 'price' => 5899, 'old_price' => null, 'brand' => 'Apple', 'specs' => '512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/ld0005930760_1_1.jpg?v=1772621887', 'condition' => 'Occasion'],
        ['name' => 'Samsung Galaxy S24 Ultra (12/512)', 'price' => 7399, 'old_price' => null, 'brand' => 'Samsung', 'specs' => '12GB/512GB 5G', 'image' => 'https://electrosalam.ma/cdn/shop/files/10t_15_e01be133-4d0b-4005-9bc9-f03387a2feaf.png?v=1762616539', 'condition' => 'Comme Neuf'],
        ['name' => 'iPhone 15 Pro Max 256GB', 'price' => 8399, 'old_price' => null, 'brand' => 'Apple', 'specs' => '256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/15pro-w-1_2_1_1_e09f6a96-ba4d-447e-a395-7cbe450bf37d.jpg?v=1756040483', 'condition' => 'Occasion'],
        ['name' => 'iPhone 15 Pro Max 512GB', 'price' => 8599, 'old_price' => null, 'brand' => 'Apple', 'specs' => '512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/15pro-w-1_2_1_1_6be6183b-0de4-4d44-b52e-2e74140a8100.jpg?v=1765644103', 'condition' => 'Occasion'],
        ['name' => 'iPhone 15 Pro Max 1TB', 'price' => 8599, 'old_price' => null, 'brand' => 'Apple', 'specs' => '1TB', 'image' => 'https://electrosalam.ma/cdn/shop/files/15pro-t-1_2_1.jpg?v=1743600497', 'condition' => 'Occasion'],
        ['name' => 'iPhone 17 Air 256GB (eSIM US)', 'price' => 8749, 'old_price' => null, 'brand' => 'Apple', 'specs' => '256GB eSIM', 'image' => 'https://electrosalam.ma/cdn/shop/files/hhNouve_u_projet.png?v=1765192160', 'condition' => 'Neuf'],
        ['name' => 'iPhone 16 Pro Max 512GB', 'price' => 10499, 'old_price' => null, 'brand' => 'Apple', 'specs' => '512GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/332224.png?v=1741262079', 'condition' => 'Occasion'],
        ['name' => 'iPhone 17 Pro Max 256GB (Europe)', 'price' => 16500, 'old_price' => null, 'brand' => 'Apple', 'specs' => '256GB', 'image' => 'https://electrosalam.ma/cdn/shop/files/nn_projet.png?v=1765192341', 'condition' => 'Neuf'],
        ['name' => 'iPhone 17 Pro Max 512GB (eSIM)', 'price' => 16949, 'old_price' => null, 'brand' => 'Apple', 'specs' => '512GB eSIM', 'image' => 'https://electrosalam.ma/cdn/shop/files/nn_projet.png?v=1765192341', 'condition' => 'Neuf'],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $dryRun = $this->option('dry-run');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' introuvable.");
            return 1;
        }

        $this->info("Import dans le tenant : {$tenant->name} ({$tenantId})");
        $this->info(count($this->products) . ' produits à importer.');

        if ($dryRun) {
            $this->warn('Mode dry-run — aucune modification.');
            foreach ($this->products as $p) {
                $this->line("  - {$p['name']} | {$p['price']} MAD | {$p['brand']} | {$p['condition']}");
            }
            return 0;
        }

        $tenant->run(function () {
            // Create category
            $category = Category::firstOrCreate(
                ['ctg_title' => 'Smartphones'],
                ['ctg_code' => 'SMART', 'ctg_status' => true]
            );

            $created = 0;
            $skipped = 0;

            foreach ($this->products as $i => $item) {
                // Create brand if not exists
                $brandCode = Str::upper(Str::slug($item['brand'], '_'));
                $brand = Brand::firstOrCreate(
                    ['br_title' => $item['brand']],
                    ['br_code' => $brandCode, 'br_status' => true]
                );

                // Check if product already exists
                $slug = Str::slug($item['name']);
                if (Product::where('p_slug', $slug)->exists()) {
                    $this->line("  [SKIP] {$item['name']} (déjà existant)");
                    $skipped++;
                    continue;
                }

                // Determine purchase price (60-70% of sale price as estimate)
                $purchasePrice = round($item['price'] * 0.65, 2);

                // p_code auto-generated by BelongsToStructure trait
                // via StructureIncrementor when left empty
                $product = Product::create([
                    'p_title'         => $item['name'],
                    'p_slug'          => $slug,
                    'p_description'   => "{$item['specs']} — {$item['condition']}",
                    'p_long_description' => "Smartphone {$item['brand']} {$item['name']}. Spécifications: {$item['specs']}. État: {$item['condition']}.",
                    'p_sku'           => $slug,
                    'p_purchasePrice' => $purchasePrice,
                    'p_salePrice'     => $item['price'],
                    'p_cost'          => $item['old_price'] ?? $item['price'],
                    'p_status'        => true,
                    'p_taxRate'       => 20.00,
                    'p_unit'          => 'pièce',
                    'category_id'     => $category->id,
                    'brand_id'        => $brand->id,
                    'is_ecom'         => true,
                ]);

                // Download and save product image
                $this->downloadImage($product, $item['image'], $item['name']);

                $this->info("  [OK] {$item['name']} — {$item['price']} MAD");
                $created++;
            }

            $this->newLine();
            $this->info("Résultat : {$created} créés, {$skipped} ignorés.");
        });

        return 0;
    }

    private function downloadImage(Product $product, string $url, string $name): void
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; O3App/1.0)',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);
            if (! $imageData) {
                $this->warn("    Image non téléchargée pour: {$name}");
                // Still create image record with external URL
                ProductImage::create([
                    'product_id' => $product->id,
                    'url'        => $url,
                    'title'      => Str::slug($name),
                    'isPrimary'  => true,
                ]);
                return;
            }

            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $filename = Str::slug($name) . '.' . $ext;
            $path = 'products/' . $filename;

            Storage::disk('public')->put($path, $imageData);

            ProductImage::create([
                'product_id' => $product->id,
                'url'        => '/tenancy/assets/' . $path,
                'title'      => Str::slug($name),
                'isPrimary'  => true,
            ]);
        } catch (\Exception $e) {
            $this->warn("    Erreur image pour {$name}: {$e->getMessage()}");
            // Create with external URL as fallback
            ProductImage::create([
                'product_id' => $product->id,
                'url'        => $url,
                'title'      => Str::slug($name),
                'isPrimary'  => true,
            ]);
        }
    }
}
