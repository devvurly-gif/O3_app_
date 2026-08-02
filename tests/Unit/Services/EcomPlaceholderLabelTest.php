<?php

namespace Tests\Unit\Services;

use App\Services\PromotionService;
use Tests\TestCase;

/**
 * Les fourre-tout « Non catégorisé » / « Marque inconnue » créés à la naissance
 * d'un tenant ne doivent pas remonter sur la boutique : ils s'affichaient en
 * badge au-dessus du nom du produit et polluaient les filtres.
 */
class EcomPlaceholderLabelTest extends TestCase
{
    public function test_detecte_la_categorie_fourre_tout(): void
    {
        $this->assertTrue(PromotionService::isPlaceholder('Non catégorisé', 'category'));
    }

    public function test_detecte_la_marque_fourre_tout(): void
    {
        $this->assertTrue(PromotionService::isPlaceholder('Marque inconnue', 'brand'));
    }

    public function test_ignore_la_casse_et_les_espaces_de_bord(): void
    {
        // Ces libellés sont saisis au seed du tenant, rien ne garantit leur forme.
        $this->assertTrue(PromotionService::isPlaceholder('  NON CATÉGORISÉ  ', 'category'));
        $this->assertTrue(PromotionService::isPlaceholder('marque Inconnue', 'brand'));
    }

    public function test_laisse_passer_une_vraie_categorie(): void
    {
        $this->assertFalse(PromotionService::isPlaceholder('Compresseurs à air', 'category'));
        $this->assertFalse(PromotionService::isPlaceholder('Motoculteurs', 'category'));
    }

    public function test_ne_confond_pas_categorie_et_marque(): void
    {
        $this->assertFalse(PromotionService::isPlaceholder('Non catégorisé', 'brand'));
        $this->assertFalse(PromotionService::isPlaceholder('Marque inconnue', 'category'));
    }

    public function test_un_produit_sans_rattachement_n_est_pas_un_fourre_tout(): void
    {
        // null = pas de catégorie du tout ; le champ était déjà géré en amont,
        // isPlaceholder() ne doit pas transformer ce cas en vrai.
        $this->assertFalse(PromotionService::isPlaceholder(null, 'category'));
    }

    public function test_une_categorie_renommee_redevient_une_vraie_categorie(): void
    {
        // Repli volontaire : si un tenant s'approprie le fourre-tout en le
        // renommant, il redevient une catégorie affichable comme une autre.
        $this->assertFalse(PromotionService::isPlaceholder('Divers atelier', 'category'));
    }
}
