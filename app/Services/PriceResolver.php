<?php
namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ThirdPartner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Services\TaxService;

/**
 * Resolves the effective unit price for a product given:
 *   - a customer (may have a price_list_id)
 *   - a quantity (matches min_qty tiers)
 *   - a channel (pos / ecom / all)
 *
 * Resolution order:
 *   1. Customer's assigned price list (if any)
 *   2. Default price list for the channel
 *   3. Fallback to product's base price (p_salePrice)
 *
 * Within a price list, matches the highest min_qty <= quantity,
 * filtered by valid_from/valid_to window.
 */
class PriceResolver
{
    /**
     * @return array{price_ht: float, price_ttc: float, price_list_id: ?int, source: string}
     */
    public function resolve(
        Product $product,
        ?ThirdPartner $customer = null,
        int $quantity = 1,
        string $channel = 'all'
    ): array {
        // 1. Try customer's price list first
        if ($customer && $customer->price_list_id) {
            $match = $this->findItemInList($product->id, $customer->price_list_id, $quantity, $channel);
            if ($match) {
                return $this->format($match, 'customer_price_list');
            }
        }

        // 2. Try default price list for the channel
        $defaultList = PriceList::default($channel);
        if ($defaultList) {
            $match = $this->findItemInList($product->id, $defaultList->id, $quantity, $channel);
            if ($match) {
                return $this->format($match, 'default_price_list');
            }
        }

        // 3. Fallback to product base price
        $ht = (float) $product->p_salePrice;
        $ttc = TaxService::calculateTTC($ht, (float) $product->p_taxRate);
        return [
            'price_ht'      => round($ht, 2),
            'price_ttc'     => round($ttc, 2),
            'price_list_id' => null,
            'source'        => 'product_base',
        ];
    }

    /**
     * Find the best matching item (highest min_qty <= quantity) in a given list,
     * filtered by valid_from/valid_to window.
     */
    private function findItemInList(int $productId, int $priceListId, int $quantity, string $channel): ?PriceListItem
    {
        // Ensure the list is active and targets the channel
        $list = PriceList::active()
            ->forChannel($channel)
            ->where('id', $priceListId)
            ->first();
        if (!$list) return null;

        $today = now()->toDateString();

        return PriceListItem::where('price_list_id', $priceListId)
            ->where('product_id', $productId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('min_qty')
            ->first();
    }

    private function format(PriceListItem $item, string $source): array
    {
        return [
            'price_ht'      => (float) $item->price_ht,
            'price_ttc'     => (float) $item->price_ttc,
            'price_list_id' => $item->price_list_id,
            'source'        => $source,
        ];
    }

    /**
     * Prix affiché sur la boutique en ligne, pour un visiteur anonyme.
     *
     * Ordre de résolution :
     *   1. grille de canal « ecom » — la plus spécifique
     *   2. grille par défaut de canal « all »
     *   3. prix de vente du produit (p_salePrice)
     *
     * resolve() ne convient pas ici : il ne consulte que les grilles marquées
     * is_default, si bien qu'une grille « Détail » (canal all, par défaut)
     * l'emportait sur une grille « ECommerce » (canal ecom, non marquée par
     * défaut) — soit l'inverse de l'intention. Une grille dédiée au canal est
     * par nature plus spécifique qu'une grille générique.
     *
     * @return array{price_ht: float, price_ttc: float, price_list_id: ?int, source: string}
     */
    public function resolveForStorefront(Product $product, int $quantity = 1): array
    {
        foreach ($this->storefrontPriceLists() as $list) {
            $match = $this->findValidItem($product->id, $list->id, $quantity);
            if ($match) {
                return $this->format($match, $list->channel === 'ecom' ? 'ecom_price_list' : 'default_price_list');
            }
        }

        $ht = (float) $product->p_salePrice;

        return [
            'price_ht'      => round($ht, 2),
            'price_ttc'     => round(TaxService::calculateTTC($ht, (float) $product->p_taxRate), 2),
            'price_list_id' => null,
            'source'        => 'product_base',
        ];
    }

    /**
     * Grilles candidates pour la boutique, de la plus spécifique à la moins.
     *
     * Mémorisé : transformForEcom() est appelé une fois par produit, on ne veut
     * pas rejouer cette requête pour chacun des douze produits d'une page.
     */
    private ?Collection $storefrontLists = null;

    private function storefrontPriceLists(): Collection
    {
        if ($this->storefrontLists !== null) {
            return $this->storefrontLists;
        }

        $ecom = PriceList::active()
            ->where('channel', 'ecom')
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->get();

        // Seule la grille générique marquée par défaut sert de repli : une
        // grille « all » quelconque ne doit pas tarifer le site à son insu.
        $generique = PriceList::active()
            ->where('channel', 'all')
            ->where('is_default', true)
            ->orderByDesc('priority')
            ->get();

        return $this->storefrontLists = $ecom->concat($generique);
    }

    /**
     * Tarif applicable dans une grille : plus fort min_qty <= quantité, dans la
     * fenêtre de validité.
     */
    private function findValidItem(int $productId, int $priceListId, int $quantity): ?PriceListItem
    {
        $today = now()->toDateString();

        return PriceListItem::where('price_list_id', $priceListId)
            ->where('product_id', $productId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('min_qty')
            ->first();
    }

    /**
     * Shortcut: just return the TTC price (useful for POS/ecom).
     */
    public function resolveTtc(Product $product, ?ThirdPartner $customer = null, int $quantity = 1, string $channel = 'all'): float
    {
        return $this->resolve($product, $customer, $quantity, $channel)['price_ttc'];
    }
}
