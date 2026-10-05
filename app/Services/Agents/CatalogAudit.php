<?php

namespace App\Services\Agents;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contrôle des fiches produits, en lecture seule : quelles fiches sont incomplètes ou incohérentes.
 * Il ne modifie rien. Les produits inactifs sont contrôlés comme les autres (on les compte à part) :
 * sur certains tenants le catalogue entier est importé inactif.
 *
 * Contrôles :
 *  - no_photo       : aucune image ;
 *  - no_description : description vide, ou identique au titre (ce que laisse un import) ;
 *  - no_category    : catégorie par défaut (« Non catégorisé »…) — la base impose une catégorie, donc une fiche non
 *                     classée est dans celle-là ;
 *  - no_sale_price  : prix de vente absent ou nul ;
 *  - below_cost     : prix de vente inférieur au prix d'achat (le même critère que la révision des prix).
 */
class CatalogAudit
{
    /** Catégories « par défaut » : une fiche qui y est n'a pas de vraie catégorie. */
    public const DEFAULT_CATEGORIES = ['Non catégorisé', 'Non catégorisée', 'Non classé', 'Sans catégorie', 'Uncategorized'];

    public const CHECKS = [
        'no_photo'       => 'Sans photo',
        'no_description' => 'Sans description (vide ou identique au titre)',
        'no_category'    => 'Non catégorisées (catégorie par défaut)',
        'no_sale_price'  => 'Sans prix de vente',
        'below_cost'     => "Prix de vente sous le prix d'achat",
    ];

    /**
     * @return array{total: int, inactive: int, issues: array<string, array<int, int>>, flagged: array<int, int>}
     */
    public function run(): array
    {
        $issues = [];
        foreach (array_keys(self::CHECKS) as $check) {
            $issues[$check] = $this->query($check)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return [
            'total'    => Product::count(),
            'inactive' => Product::where('p_status', false)->count(),
            'issues'   => $issues,
            'flagged'  => array_values(array_unique(array_merge(...array_values($issues)))),
        ];
    }

    /** Les produits qui échouent à un contrôle. */
    public function query(string $check): Builder
    {
        $q = Product::query();

        return match ($check) {
            'no_photo'       => $q->whereDoesntHave('images'),
            'no_description' => $q->where(fn (Builder $w) => $w->whereNull('p_description')->orWhere('p_description', '')->orWhereColumn('p_description', 'p_title')),
            'no_category'    => $q->where(fn (Builder $w) => $w->whereNull('category_id')->orWhereHas('category', fn (Builder $c) => $c->whereIn('ctg_title', self::DEFAULT_CATEGORIES))),
            'no_sale_price'  => $q->where(fn (Builder $w) => $w->whereNull('p_salePrice')->orWhere('p_salePrice', '<=', 0)),
            'below_cost'     => $q->where('p_purchasePrice', '>', 0)->where('p_salePrice', '>', 0)->whereColumn('p_salePrice', '<', 'p_purchasePrice'),
            default          => throw new \InvalidArgumentException("Contrôle inconnu : {$check}"),
        };
    }
}
