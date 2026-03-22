<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Promo Smartphones -20% ──────────────────────────────────
        $promo1 = Promotion::create([
            'name'         => 'Promo Smartphones -20%',
            'slug'         => 'promo-smartphones-20',
            'description'  => 'Profitez de 20% de réduction sur tous les smartphones !',
            'type'         => 'percentage',
            'value'        => 20,
            'max_discount' => 500,
            'banner_image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1200&h=400&fit=crop',
            'banner_text'  => 'Smartphones à prix cassés !',
            'starts_at'    => now(),
            'ends_at'      => now()->addDays(30),
            'is_active'    => true,
            'priority'     => 10,
        ]);

        // Attacher les smartphones (id 1, 8)
        $smartphoneIds = Product::whereIn('id', [1, 8])->pluck('id');
        foreach ($smartphoneIds as $id) {
            $promo1->products()->attach($id);
        }

        // ── 2. Promo Accessoires -50 MAD ───────────────────────────────
        $promo2 = Promotion::create([
            'name'         => 'Accessoires -50 MAD',
            'slug'         => 'accessoires-50-mad',
            'description'  => '50 MAD de réduction immédiate sur les accessoires sélectionnés.',
            'type'         => 'fixed_amount',
            'value'        => 50,
            'banner_image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1200&h=400&fit=crop',
            'banner_text'  => '-50 MAD sur les accessoires',
            'starts_at'    => now(),
            'ends_at'      => now()->addDays(15),
            'is_active'    => true,
            'priority'     => 5,
        ]);

        // Attacher accessoires (id 4, 5, 7, 9, 10)
        $accessoireIds = Product::whereIn('id', [4, 5, 7, 9, 10])->pluck('id');
        foreach ($accessoireIds as $id) {
            $promo2->products()->attach($id);
        }

        // ── 3. Flash Sale PC Portable ──────────────────────────────────
        $promo3 = Promotion::create([
            'name'         => 'Flash Sale PC Portable',
            'slug'         => 'flash-sale-pc-portable',
            'description'  => 'Prix exceptionnel sur le PC Portable Neo 14" — stock limité !',
            'type'         => 'percentage',
            'value'        => 15,
            'banner_image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=1200&h=400&fit=crop',
            'banner_text'  => 'Vente Flash -15% !',
            'starts_at'    => now(),
            'ends_at'      => now()->addDays(3),
            'is_active'    => true,
            'priority'     => 20,
        ]);

        // Attacher PC (id 2, 6) avec prix forcé pour le id 6
        $promo3->products()->attach(2);
        $promo3->products()->attach(6, ['promo_price' => 999.00]);

        // ── 4. Promo future (inactive) ─────────────────────────────────
        $promo4 = Promotion::create([
            'name'         => 'Black Friday 2026',
            'slug'         => 'black-friday-2026',
            'description'  => 'Offres exceptionnelles pour le Black Friday !',
            'type'         => 'percentage',
            'value'        => 30,
            'max_discount' => 1000,
            'banner_image' => 'https://images.unsplash.com/photo-1607083206968-13611e3d76db?w=1200&h=400&fit=crop',
            'banner_text'  => 'BLACK FRIDAY -30% sur TOUT',
            'starts_at'    => now()->addMonths(8),
            'ends_at'      => now()->addMonths(8)->addDays(4),
            'is_active'    => true,
            'priority'     => 100,
        ]);

        // Marquer tous les produits attachés comme is_ecom + slug
        $allProductIds = collect([1, 2, 4, 5, 6, 7, 8, 9, 10]);
        Product::whereIn('id', $allProductIds)->each(function (Product $p) {
            $p->update([
                'is_ecom' => true,
                'p_slug'  => Str::slug($p->p_title),
                'p_long_description' => "Découvrez le {$p->p_title}. Produit de haute qualité disponible sur notre boutique en ligne. Livraison rapide partout au Maroc.",
            ]);
        });

        $this->command->info('4 promotions créées avec produits associés.');
    }
}
