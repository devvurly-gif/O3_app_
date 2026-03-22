<?php

namespace Database\Seeders;

use App\Models\Slide;
use Illuminate\Database\Seeder;

class SlideSeeder extends Seeder
{
    public function run(): void
    {
        // ── Hero Slides ────────────────────────────────────────────────
        Slide::create([
            'title'       => 'Promo Smartphones -20%',
            'subtitle'    => 'Les meilleurs smartphones à prix réduit',
            'image'       => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1400&h=500&fit=crop',
            'button_text' => 'Voir les offres',
            'link_type'   => 'promotion',
            'link_id'     => 1,
            'position'    => 'hero',
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        Slide::create([
            'title'       => 'Vente Flash PC Portable',
            'subtitle'    => 'Stock limité — Jusqu\'à -15% !',
            'image'       => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=1400&h=500&fit=crop',
            'button_text' => 'En profiter',
            'link_type'   => 'promotion',
            'link_id'     => 3,
            'position'    => 'hero',
            'sort_order'  => 2,
            'is_active'   => true,
        ]);

        Slide::create([
            'title'       => 'Nouveautés Tech',
            'subtitle'    => 'Découvrez nos derniers arrivages',
            'image'       => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1400&h=500&fit=crop',
            'button_text' => 'Explorer',
            'link_type'   => 'category',
            'link_id'     => 2,
            'position'    => 'hero',
            'sort_order'  => 3,
            'is_active'   => true,
        ]);

        Slide::create([
            'title'       => 'Livraison Gratuite',
            'subtitle'    => 'Dès 500 MAD d\'achat — Partout au Maroc',
            'image'       => 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=1400&h=500&fit=crop',
            'button_text' => null,
            'link_type'   => 'none',
            'position'    => 'hero',
            'sort_order'  => 4,
            'is_active'   => true,
        ]);

        // ── Sidebar Slides ─────────────────────────────────────────────
        Slide::create([
            'title'       => 'Accessoires -50 MAD',
            'subtitle'    => 'Offre limitée',
            'image'       => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=400&fit=crop',
            'button_text' => 'Voir',
            'link_type'   => 'promotion',
            'link_id'     => 2,
            'position'    => 'sidebar',
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        Slide::create([
            'title'       => 'HP LAPTOP 835',
            'subtitle'    => 'Le choix des pros',
            'image'       => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=400&fit=crop',
            'button_text' => 'Acheter',
            'link_type'   => 'product',
            'link_id'     => 2,
            'position'    => 'sidebar',
            'sort_order'  => 2,
            'is_active'   => true,
        ]);

        // ── Popup Slide ────────────────────────────────────────────────
        Slide::create([
            'title'       => 'Newsletter -10%',
            'subtitle'    => 'Inscrivez-vous et recevez 10% de réduction',
            'image'       => 'https://images.unsplash.com/photo-1557200134-90327ee9fafa?w=800&h=600&fit=crop',
            'button_text' => 'S\'inscrire',
            'link_type'   => 'url',
            'link_url'    => '/newsletter',
            'position'    => 'popup',
            'sort_order'  => 1,
            'is_active'   => true,
            'starts_at'   => now(),
            'ends_at'     => now()->addMonths(3),
        ]);

        $this->command->info('7 slides créés (4 hero, 2 sidebar, 1 popup).');
    }
}
