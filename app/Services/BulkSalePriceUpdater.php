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
    public const MODES = ['percent', 'amount', 'margin', 'target_margin', 'set'];

    /**
     * Une marge de 100 % sur le prix de vente voudrait dire un prix infini :
     * la part du cout y serait nulle. Le mode `target_margin` s'arrete avant.
     */
    public const MAX_TARGET_MARGIN = 99.9;

    /** Sur quoi le pourcentage ou le montant s'applique. */
    public const BASES = ['sale', 'purchase', 'cost'];

    public const ROUNDINGS = ['none', '0.05', '0.10', '0.50', '1', '5', '10', 'end_90', 'end_99'];

    /**
     * Le perimetre : les produits en stock, avec leur quantite.
     *
     * Le stock positif n'est pas une option — une revision de tarif porte sur
     * ce qu'on a en rayon. La jointure sur la somme par produit fait les deux
     * en une passe : elle ecarte ceux dont le total est nul ou negatif, et
     * ramene `stock_qty` avec la ligne. L'accesseur `total_stock` du modele ne
     * saurait ni l'un ni l'autre — il interroge la base produit par produit.
     *
     * Les lignes de variantes portent aussi leur `product_id`, elles entrent
     * donc dans la meme somme.
     *
     * @param array<string, mixed> $filters
     */
    public function query(array $filters): Builder
    {
        $stock = DB::table('warehouse_has_stock')
            ->select('product_id', DB::raw('SUM(stockLevel) AS stock_qty'))
            ->groupBy('product_id')
            ->havingRaw('SUM(stockLevel) > 0');

        $query = Product::query()
            ->joinSub($stock, 'stk', fn ($join) => $join->on('stk.product_id', '=', 'products.id'))
            ->select('products.*', 'stk.stock_qty');

        if (!empty($filters['product_ids'])) {
            $query->whereIn('products.id', $filters['product_ids']);
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

        // Marge actuelle, rapportee au prix de vente — la lecture affichee en
        // premier a l'ecran. Un produit sans prix de vente ou sans prix d'achat
        // n'a pas de marge connue : des qu'un seuil est demande, il sort du
        // perimetre plutot que de compter comme s'il valait zero.
        $min = $filters['margin_min'] ?? null;
        $max = $filters['margin_max'] ?? null;

        if ($min !== null || $max !== null) {
            $query->where('p_salePrice', '>', 0)->where('p_purchasePrice', '>', 0);

            $margin = '((p_salePrice - p_purchasePrice) / p_salePrice * 100)';

            if ($min !== null) {
                $query->whereRaw($margin . ' >= ?', [$min]);
            }

            if ($max !== null) {
                $query->whereRaw($margin . ' <= ?', [$max]);
            }
        }

        return $query->orderBy('products.id');
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

        // La marge cible se lit sur le prix de vente, pas sur l'achat : viser
        // 30 % ne veut pas dire majorer l'achat de 30 %. Le prix qui laisse
        // cette part-la, c'est achat / (1 - marge) — un achat a 80 vise a 30 %
        // donne 114,29, dont 34,29 de marge, soit bien 30 % de 114,29.
        //
        // Le prix de vente ne peut pas servir de base ici : on part forcement
        // du cout, sinon le calcul se mordrait la queue.
        if ($mode === 'target_margin') {
            $basis = $basis === 'sale' ? 'purchase' : $basis;
            $base  = $this->basisValue($product, $basis);

            if ($base <= 0 || $value >= self::MAX_TARGET_MARGIN) {
                return null;
            }

            return $this->applyRounding($base / (1 - $value / 100), $rule['rounding'] ?? 'none');
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
     * Une ligne de chiffrage pour un produit : ce que l'ecran affiche et ce que
     * l'export ecrit sortent d'ici, pour qu'un chiffre vu a l'ecran et le meme
     * chiffre dans le tableur ne puissent pas diverger.
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function describe(Product $product, array $rule, bool $withCosts = false): array
    {
        $new      = $this->newPriceFor($product, $rule);
        $current  = round((float) $product->p_salePrice, 2);
        $purchase = round((float) $product->p_purchasePrice, 2);
        $new      = $new === null ? null : round($new, 2);

        $row = [
            'id'      => $product->id,
            'p_code'  => $product->p_code,
            'p_title' => $product->p_title,
            // Ramene par la jointure du perimetre, pas par l'accesseur.
            'stock'   => round((float) ($product->stock_qty ?? 0), 2),
            'current' => $current,
            'new'     => $new,
            'delta'   => $new === null ? null : round($new - $current, 2),
            // Une base achat ou cout a zero : le produit sort du lot.
            'skipped' => $new === null,
            'changed' => $new !== null && $new !== $current,
            // Vendre sous le prix d'achat est le vrai risque d'une baisse en
            // masse : marque sur chaque ligne, compte sur tout le lot.
            'below_purchase' => $new !== null && $purchase > 0 && $new < $purchase,
        ];

        if ($withCosts) {
            $row['purchase'] = $purchase;
            $row['cost']     = round((float) $product->p_cost, 2);

            // Deux lectures de la meme marge, et il faut les deux.
            //
            // `margin` rapporte le gain au prix d'achat — le coefficient qu'on
            // applique en achetant. `margin_sale` le rapporte au prix de vente
            // — la part de marge dans ce qu'encaisse la caisse, celle qu'on
            // compare a un taux cible ou a un concurrent. Un article achete 80
            // et vendu 100 fait 25 % sur achat et 20 % sur vente : meme somme,
            // deux chiffres, et les confondre fausse la decision.
            //
            // Prix d'achat a zero : aucune des deux n'a de sens. Rapporter 100
            // a un achat inconnu afficherait « 100 % de marge » sur un article
            // dont on ignore justement le cout.
            $row['margin']             = $this->marginOn($new, $purchase, $purchase);
            $row['margin_before']      = $this->marginOn($current, $purchase, $purchase);
            $row['margin_sale']        = $this->marginOn($new, $purchase, $new);
            $row['margin_sale_before'] = $this->marginOn($current, $purchase, $current);
        }

        return $row;
    }

    /**
     * (prix - achat) / base, en pourcentage — null des que la base ou l'achat
     * manque, plutot qu'un chiffre qui aurait l'air d'un resultat.
     */
    private function marginOn(?float $price, float $purchase, ?float $base): ?float
    {
        if ($price === null || $base === null || $purchase <= 0 || $base <= 0) {
            return null;
        }

        return round(($price - $purchase) / $base * 100, 1);
    }

    /**
     * Toutes les lignes du perimetre, en flux.
     *
     * `lazy()` plutot qu'un `get()` : l'export peut porter des milliers de
     * lignes et n'a aucune raison de les tenir toutes en memoire. Pas
     * `cursor()` non plus — il tient la connexion ouverte en mode non
     * bufferise, et toute requete emise pendant la boucle (une sauvegarde, un
     * test sous transaction) tombe alors sur « commands out of sync ».
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $rule
     * @return \Generator<array<string, mixed>>
     */
    public function rows(array $filters, array $rule, bool $withCosts = false): \Generator
    {
        foreach ($this->query($filters)->lazy() as $product) {
            yield $this->describe($product, $rule, $withCosts);
        }
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

        foreach ($this->rows($filters, $rule, $withCosts) as $row) {
            $matched++;

            if ($row['skipped']) {
                $skipped++;
                continue;
            }

            if ($row['new'] < 0) {
                $negative++;
            }

            if ($row['below_purchase']) {
                $belowPurchase++;
            }

            if (!$row['changed']) {
                continue;
            }

            $changed++;

            if (count($sample) < self::SAMPLE_SIZE) {
                unset($row['skipped'], $row['changed'], $row['below_purchase']);
                $sample[] = $row;
            }
        }

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
