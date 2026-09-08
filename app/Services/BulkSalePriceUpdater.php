<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule le prix de vente d'un lot de produits.
 *
 * Deux entrees pour un seul calcul : `preview()` chiffre l'operation sans rien
 * ecrire, `apply()` l'ecrit. L'ecran n'applique jamais sans avoir affiche le
 * chiffrage, et le controleur revalide le compte au moment d'appliquer — une
 * remise a plat des prix se fait les yeux ouverts.
 *
 * Les produits sont sauvegardes un par un plutot qu'en `UPDATE` de masse :
 * Product journalise `p_salePrice` via l'activity log, et un changement de
 * tarif est exactement ce qu'on veut retrouver dans la piste d'audit.
 */
class BulkSalePriceUpdater
{
    /**
     * Au-dela, l'operation est refusee : un lot de cette taille n'est plus une
     * revision de tarif, c'est un import — et la boucle de sauvegarde
     * unitaire, elle, deviendrait longue au point de tomber en timeout.
     */
    public const MAX_PRODUCTS = 5000;

    /** Nombre de lignes renvoyees en exemple par le chiffrage. */
    public const SAMPLE_SIZE = 50;

    /**
     * `margin` est l'ancien nom de « pourcentage applique au prix d'achat ».
     * Depuis que la base est un choix a part entiere, c'est un alias : il reste
     * accepte pour ne pas casser un appel deja ecrit, mais l'ecran ne le propose
     * plus — deux chemins vers le meme calcul se contredisent tot ou tard.
     */
    public const MODES = ['percent', 'amount', 'margin', 'set'];

    /** Sur quoi le pourcentage ou le montant s'applique. */
    public const BASES = ['sale', 'purchase', 'cost'];

    public const ROUNDINGS = ['none', '0.05', '0.10', '0.50', '1', '5', '10', 'end_90', 'end_99'];

    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $query = Product::query();

        if (!empty($filters['product_ids'])) {
            $query->whereIn('id', $filters['product_ids']);
        }

        if (!empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }

        if (!empty($filters['brand_ids'])) {
            $query->whereIn('brand_id', $filters['brand_ids']);
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('p_status', $filters['status'] === 'active');
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('p_title', 'like', $term)
                  ->orWhere('p_sku', 'like', $term)
                  ->orWhere('p_code', 'like', $term);
            });
        }

        return $query->orderBy('id');
    }

    /**
     * Valeur de depart du calcul : le prix de vente actuel, le prix d'achat ou
     * le cout de revient.
     */
    private function basisValue(Product $product, string $basis): float
    {
        return (float) match ($basis) {
            'purchase' => $product->p_purchasePrice,
            'cost'     => $product->p_cost,
            default    => $product->p_salePrice,
        };
    }

    /**
     * Nouveau prix pour un produit, ou null quand la regle ne s'applique pas :
     * une base achat ou cout a zero n'a rien a majorer, et un produit dont on
     * ignore le cout ne doit pas se retrouver a zero par accident.
     *
     * @param array<string, mixed> $rule
     */
    public function newPriceFor(Product $product, array $rule): ?float
    {
        $value = (float) $rule['value'];

        // `margin` valait « pourcentage sur le prix d'achat » avant que la base
        // devienne un choix ; on le ramene a sa forme actuelle.
        $mode  = $rule['mode'] === 'margin' ? 'percent' : $rule['mode'];
        $basis = $rule['basis'] ?? ($rule['mode'] === 'margin' ? 'purchase' : 'sale');

        if ($mode === 'set') {
            return $this->applyRounding($value, $rule['rounding'] ?? 'none');
        }

        $base = $this->basisValue($product, $basis);

        // Le prix de vente fait exception : partir de zero y est un cas normal
        // (un produit non tarife reste a zero), pas une base manquante.
        if ($basis !== 'sale' && $base <= 0) {
            return null;
        }

        $raw = $mode === 'percent'
            ? $base * (1 + $value / 100)
            : $base + $value;

        return $this->applyRounding($raw, $rule['rounding'] ?? 'none');
    }

    /**
     * Chiffre l'operation sans rien ecrire.
     *
     * $withCosts porte le prix d'achat, le cout de revient et la marge qui en
     * resulte dans chaque ligne : sans eux, une baisse en masse se juge a
     * l'aveugle. Ces champs sont derriere `products.view_cost` (cf.
     * Product::COST_FIELDS), c'est au controleur de decider qui les voit.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function preview(array $filters, array $rule, bool $withCosts = false): array
    {
        $matched = $changed = $skipped = $negative = $belowPurchase = 0;
        $sample  = [];

        $this->query($filters)->chunkById(500, function ($products) use (
            $rule, $withCosts, &$matched, &$changed, &$skipped, &$negative, &$belowPurchase, &$sample
        ) {
            foreach ($products as $product) {
                $matched++;

                $new = $this->newPriceFor($product, $rule);

                if ($new === null) {
                    $skipped++;
                    continue;
                }

                if ($new < 0) {
                    $negative++;
                }

                $current  = round((float) $product->p_salePrice, 2);
                $purchase = round((float) $product->p_purchasePrice, 2);
                $new      = round($new, 2);

                // Vendre sous le prix d'achat est le vrai risque d'une baisse
                // en masse : compte sur tout le lot, pas seulement l'echantillon.
                if ($purchase > 0 && $new < $purchase) {
                    $belowPurchase++;
                }

                if ($new === $current) {
                    continue;
                }

                $changed++;

                if (count($sample) < self::SAMPLE_SIZE) {
                    $row = [
                        'id'      => $product->id,
                        'p_code'  => $product->p_code,
                        'p_title' => $product->p_title,
                        'current' => $current,
                        'new'     => $new,
                        'delta'   => round($new - $current, 2),
                    ];

                    if ($withCosts) {
                        $row['purchase']     = $purchase;
                        $row['cost']         = round((float) $product->p_cost, 2);
                        $row['margin']       = $purchase > 0 ? round(($new - $purchase) / $purchase * 100, 1) : null;
                        $row['margin_before'] = $purchase > 0 ? round(($current - $purchase) / $purchase * 100, 1) : null;
                    }

                    $sample[] = $row;
                }
            }
        });

        return [
            'matched'          => $matched,
            'changed'          => $changed,
            'unchanged'        => $matched - $changed - $skipped,
            'skipped_no_basis' => $skipped,
            'negative'         => $negative,
            'below_purchase'   => $withCosts ? $belowPurchase : null,
            'costs_visible'    => $withCosts,
            'sample'           => $sample,
            'max_products'     => self::MAX_PRODUCTS,
        ];
    }

    /**
     * Ecrit les nouveaux prix. A n'appeler qu'apres les controles du
     * controleur (taille du lot, compte confirme, aucun prix negatif).
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $rule
     * @return int nombre de produits reellement modifies
     */
    public function apply(array $filters, array $rule): int
    {
        $updated = 0;

        DB::transaction(function () use ($filters, $rule, &$updated) {
            $this->query($filters)->chunkById(500, function ($products) use ($rule, &$updated) {
                foreach ($products as $product) {
                    $new = $this->newPriceFor($product, $rule);

                    if ($new === null || $new < 0) {
                        continue;
                    }

                    $new = round($new, 2);

                    if ($new === round((float) $product->p_salePrice, 2)) {
                        continue;
                    }

                    $product->p_salePrice = $new;
                    $product->save();
                    $updated++;
                }
            });
        });

        return $updated;
    }

    /**
     * `end_90` / `end_99` visent le prix psychologique : on arrondit a l'entier
     * puis on retire un centime ou dix, ce qui fait descendre 100 a 99,90 —
     * remonter a 100,90 serait un contresens commercial.
     */
    private function applyRounding(float $price, string $rounding): float
    {
        $rounded = match ($rounding) {
            '0.05'   => round($price / 0.05) * 0.05,
            '0.10'   => round($price / 0.10) * 0.10,
            '0.50'   => round($price / 0.50) * 0.50,
            '1'      => round($price),
            '5'      => round($price / 5) * 5,
            '10'     => round($price / 10) * 10,
            'end_90' => round($price) - 0.10,
            'end_99' => round($price) - 0.01,
            default  => $price,
        };

        // Un prix negatif est laisse tel quel : le chiffrage le compte et
        // l'application refuse le lot. En revanche l'arrondi ne doit pas, a lui
        // seul, faire passer un prix positif sous zero (0,40 en `end_90`).
        if ($price >= 0 && $rounded < 0) {
            $rounded = 0.0;
        }

        return round($rounded, 2);
    }
}
