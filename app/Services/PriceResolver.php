<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ThirdPartner;
use Carbon\Carbon;

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
        $ttc = $ht * (1 + ((float) $product->p_taxRate) / 100);
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
     * Shortcut: just return the TTC price (useful for POS/ecom).
     */
    public function resolveTtc(Product $product, ?ThirdPartner $customer = null, int $quantity = 1, string $channel = 'all'): float
    {
        return $this->resolve($product, $customer, $quantity, $channel)['price_ttc'];
    }
}
