<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class ProductScraperService
{
    /**
     * Scrape products from any ecommerce URL.
     *
     * Strategy:
     * 1. Try JSON-LD structured data (best quality)
     * 2. Try Open Graph / meta tags
     * 3. Try common HTML patterns (product cards)
     *
     * @return array{products: array, source: string, count: int}
     */
    public function scrape(string $url): array
    {
        $html = $this->fetchPage($url);
        $domain = parse_url($url, PHP_URL_HOST);

        // 1. Try JSON-LD (most reliable)
        $products = $this->extractJsonLd($html, $url);

        // 2. Try Shopify-specific extraction
        if (empty($products) && $this->isShopify($html)) {
            $products = $this->extractShopify($html, $url);
        }

        // 3. Try generic HTML patterns
        if (empty($products)) {
            $products = $this->extractFromHtml($html, $url);
        }

        // Normalize and deduplicate
        $products = $this->normalizeProducts($products, $url);

        return [
            'products' => $products,
            'source'   => $domain,
            'count'    => count($products),
        ];
    }

    private function fetchPage(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Impossible de charger la page: HTTP {$response->status()}");
        }

        return $response->body();
    }

    // ── JSON-LD (Schema.org) ─────────────────────────────────────────

    private function extractJsonLd(string $html, string $baseUrl): array
    {
        $products = [];

        // Find all <script type="application/ld+json"> blocks
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $data = @json_decode(trim($json), true);
            if (! $data) continue;

            // Handle @graph arrays
            $items = [];
            if (isset($data['@graph'])) {
                $items = $data['@graph'];
            } elseif (isset($data['@type'])) {
                $items = [$data];
            } elseif (isset($data[0])) {
                $items = $data;
            }

            foreach ($items as $item) {
                $type = $item['@type'] ?? '';
                if (! in_array($type, ['Product', 'ItemList', 'CollectionPage'])) continue;

                if ($type === 'Product') {
                    $p = $this->parseJsonLdProduct($item, $baseUrl);
                    if ($p) $products[] = $p;
                }

                // ItemList with Product items
                if (in_array($type, ['ItemList', 'CollectionPage'])) {
                    foreach ($item['itemListElement'] ?? [] as $listItem) {
                        $productData = $listItem['item'] ?? $listItem;
                        if (($productData['@type'] ?? '') === 'Product') {
                            $p = $this->parseJsonLdProduct($productData, $baseUrl);
                            if ($p) $products[] = $p;
                        }
                    }
                }
            }
        }

        return $products;
    }

    private function parseJsonLdProduct(array $data, string $baseUrl): ?array
    {
        $name = $data['name'] ?? null;
        if (! $name) return null;

        // Price
        $price = 0;
        $oldPrice = null;
        $offers = $data['offers'] ?? $data['offer'] ?? null;
        if ($offers) {
            if (isset($offers[0])) $offers = $offers[0];
            $price = (float) ($offers['price'] ?? $offers['lowPrice'] ?? 0);
            if (isset($offers['highPrice']) && (float) $offers['highPrice'] > $price) {
                $oldPrice = (float) $offers['highPrice'];
            }
        }

        // Image
        $image = $data['image'] ?? null;
        if (is_array($image)) $image = $image[0] ?? ($image['url'] ?? null);

        // Brand
        $brand = $data['brand']['name'] ?? $data['brand'] ?? null;
        if (is_array($brand)) $brand = $brand['name'] ?? null;

        return [
            'name'      => $name,
            'price'     => $price,
            'old_price' => $oldPrice,
            'brand'     => $brand,
            'image'     => $image,
            'description' => Str::limit($data['description'] ?? '', 500),
            'url'       => $data['url'] ?? null,
        ];
    }

    // ── Shopify ──────────────────────────────────────────────────────

    private function isShopify(string $html): bool
    {
        return str_contains($html, 'Shopify') ||
               str_contains($html, 'cdn.shopify.com') ||
               str_contains($html, 'myshopify.com');
    }

    private function extractShopify(string $html, string $baseUrl): array
    {
        $products = [];

        // Strategy 1: Try /products.json endpoint (most reliable for Shopify)
        $products = $this->fetchShopifyProductsJson($baseUrl);
        if (! empty($products)) return $products;

        // Strategy 2: Parse meta.products JS variable (Shopify collection pages)
        $products = $this->parseShopifyMeta($html, $baseUrl);
        if (! empty($products)) return $products;

        // Strategy 3: Try data-product attributes
        preg_match_all('/data-product=["\']({.*?})["\']/s', $html, $dataMatches);
        foreach ($dataMatches[1] ?? [] as $json) {
            $data = @json_decode(html_entity_decode($json), true);
            if ($data && isset($data['title'])) {
                $products[] = [
                    'name'      => $data['title'],
                    'price'     => (float) ($data['price'] ?? 0) / 100,
                    'old_price' => isset($data['compare_at_price']) ? (float) $data['compare_at_price'] / 100 : null,
                    'brand'     => $data['vendor'] ?? null,
                    'image'     => isset($data['featured_image']) ? 'https:' . $data['featured_image'] : null,
                    'description' => $data['description'] ?? '',
                    'url'       => $baseUrl,
                ];
            }
        }

        // Strategy 4: Fallback to HTML card parsing
        if (empty($products)) {
            $products = $this->parseShopifyCards($html, $baseUrl);
        }

        return $products;
    }

    /**
     * Fetch ALL products via Shopify's /products.json API (handles pagination).
     */
    private function fetchShopifyProductsJson(string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $baseHost = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';

        // Determine the JSON URL
        // /collections/xxx → /collections/xxx/products.json
        // /products → /products.json
        $jsonUrl = null;
        if (preg_match('#/collections/[^/]+#', $path, $m)) {
            $jsonUrl = $baseHost . $m[0] . '/products.json';
        } else {
            $jsonUrl = $baseHost . '/products.json';
        }

        $products = [];
        $page = 1;
        $limit = 250; // Shopify max per page

        while (true) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                ])->timeout(15)->get($jsonUrl, ['page' => $page, 'limit' => $limit]);

                if (! $response->successful()) break;

                $data = $response->json();
                $items = $data['products'] ?? [];
                if (empty($items)) break;

                foreach ($items as $item) {
                    $price = 0;
                    $oldPrice = null;
                    $image = null;

                    // Get price from first variant
                    if (! empty($item['variants'])) {
                        $variant = $item['variants'][0];
                        $price = (float) ($variant['price'] ?? 0);
                        $compareAt = (float) ($variant['compare_at_price'] ?? 0);
                        if ($compareAt > $price) $oldPrice = $compareAt;
                    }

                    // Get image
                    if (! empty($item['images'])) {
                        $image = $item['images'][0]['src'] ?? null;
                    } elseif (! empty($item['image'])) {
                        $image = $item['image']['src'] ?? null;
                    }

                    $products[] = [
                        'name'        => $item['title'] ?? '',
                        'price'       => $price,
                        'old_price'   => $oldPrice,
                        'brand'       => $item['vendor'] ?? null,
                        'image'       => $image,
                        'description' => Str::limit(strip_tags($item['body_html'] ?? ''), 500),
                        'url'         => $baseHost . '/products/' . ($item['handle'] ?? ''),
                    ];
                }

                // If we got less than limit, we're done
                if (count($items) < $limit) break;
                $page++;
                if ($page > 10) break; // safety limit
            } catch (\Exception $e) {
                break;
            }
        }

        return $products;
    }

    /**
     * Parse Shopify's meta.products JavaScript variable from collection pages.
     */
    private function parseShopifyMeta(string $html, string $baseUrl): array
    {
        $products = [];
        $parsed = parse_url($baseUrl);
        $baseHost = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // Match: var meta = {...products:[...]...} or meta.products = [...]
        // Shopify embeds product data in various JS patterns
        $patterns = [
            '/var\s+meta\s*=\s*(\{.*?\});\s*$/ms',
            '/"products"\s*:\s*(\[.*?\])\s*[,}]/s',
            '/productVariants"\s*:\s*(\[.*?\])/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $data = @json_decode($m[1], true);
                if (! $data) continue;

                // If it's the meta object, get products from it
                if (isset($data['products'])) {
                    $data = $data['products'];
                }

                if (! is_array($data)) continue;

                foreach ($data as $item) {
                    $name = $item['title'] ?? $item['name'] ?? null;
                    if (! $name) continue;

                    $price = 0;
                    $oldPrice = null;

                    // Price can be in cents (Shopify) or as regular number
                    if (isset($item['price'])) {
                        $p = (float) $item['price'];
                        $price = $p > 1000000 ? $p / 100 : ($p > 10000 ? $p / 100 : $p);
                    }
                    if (isset($item['variants'][0]['price'])) {
                        $price = (float) $item['variants'][0]['price'];
                    }
                    if (isset($item['compare_at_price'])) {
                        $cap = (float) $item['compare_at_price'];
                        if ($cap > $price) $oldPrice = $cap > 10000 ? $cap / 100 : $cap;
                    }

                    $image = $item['featured_image'] ?? $item['image'] ?? null;
                    if ($image && ! str_starts_with($image, 'http')) {
                        $image = 'https:' . $image;
                    }

                    $products[] = [
                        'name'        => $name,
                        'price'       => $price,
                        'old_price'   => $oldPrice,
                        'brand'       => $item['vendor'] ?? null,
                        'image'       => $image,
                        'description' => Str::limit(strip_tags($item['description'] ?? $item['body_html'] ?? ''), 500),
                        'url'         => isset($item['handle']) ? $baseHost . '/products/' . $item['handle'] : null,
                    ];
                }

                if (! empty($products)) return $products;
            }
        }

        return $products;
    }

    private function parseShopifyCards(string $html, string $baseUrl): array
    {
        $products = [];
        $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        // Match product cards - Shopify uses various patterns
        // Look for product links with prices
        preg_match_all('/<a[^>]*href=["\']\/products\/([^"\'?#]+)["\'][^>]*>.*?<\/a>/si', $html, $linkMatches);

        // Look for price patterns
        preg_match_all('/class=["\'][^"\']*(?:price|product-price|money)[^"\']*["\'][^>]*>\s*(?:<[^>]*>)*\s*([\d,.]+)\s*(?:dh|MAD|DH|€|\$)/si', $html, $priceMatches);

        // Look for product titles
        preg_match_all('/class=["\'][^"\']*(?:product-title|product-name|card-title|product__title)[^"\']*["\'][^>]*>\s*(?:<[^>]*>)*\s*([^<]+)/si', $html, $titleMatches);

        // Look for images
        preg_match_all('/class=["\'][^"\']*(?:product-image|product-featured-image|card-image)[^"\']*["\'][^>]*src=["\']([^"\']+)["\']/si', $html, $imgMatches);
        if (empty($imgMatches[1])) {
            preg_match_all('/<img[^>]*src=["\']([^"\']*cdn\.shopify[^"\']+)["\']/si', $html, $imgMatches);
        }

        $count = min(count($titleMatches[1] ?? []), count($priceMatches[1] ?? []));
        for ($i = 0; $i < $count; $i++) {
            $products[] = [
                'name'      => trim(strip_tags($titleMatches[1][$i])),
                'price'     => (float) str_replace([',', ' '], ['', ''], $priceMatches[1][$i]),
                'old_price' => null,
                'brand'     => null,
                'image'     => isset($imgMatches[1][$i]) ? $this->resolveUrl($imgMatches[1][$i], $baseUrl) : null,
                'description' => '',
                'url'       => null,
            ];
        }

        return $products;
    }

    // ── Generic HTML ─────────────────────────────────────────────────

    private function extractFromHtml(string $html, string $baseUrl): array
    {
        $products = [];

        // Strategy: Find product cards by common patterns
        // Look for repeated structures containing: title + price + image

        // Extract Open Graph product data (single product page)
        $ogProduct = $this->extractOpenGraph($html, $baseUrl);
        if ($ogProduct) {
            return [$ogProduct];
        }

        // Try matching common product card patterns
        // Pattern: elements with "product" in class containing title, price, image
        $cardPatterns = [
            '/<(?:div|li|article)[^>]*class=["\'][^"\']*product[^"\']*["\'][^>]*>(.*?)<\/(?:div|li|article)>/si',
            '/<(?:div|li|article)[^>]*class=["\'][^"\']*card[^"\']*["\'][^>]*>(.*?)<\/(?:div|li|article)>/si',
            '/<(?:div|li|article)[^>]*class=["\'][^"\']*item[^"\']*["\'][^>]*>(.*?)<\/(?:div|li|article)>/si',
        ];

        foreach ($cardPatterns as $pattern) {
            preg_match_all($pattern, $html, $cards);
            foreach ($cards[1] ?? [] as $card) {
                $p = $this->parseCardHtml($card, $baseUrl);
                if ($p && $p['name'] && $p['price'] > 0) {
                    $products[] = $p;
                }
            }
            if (! empty($products)) break;
        }

        return $products;
    }

    private function extractOpenGraph(string $html, string $baseUrl): ?array
    {
        $og = [];
        preg_match_all('/<meta[^>]*property=["\']og:([^"\']+)["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/si', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $og[$m[1]] = $m[2];
        }

        // Also check reversed attribute order
        preg_match_all('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*property=["\']og:([^"\']+)["\'][^>]*>/si', $html, $matches2, PREG_SET_ORDER);
        foreach ($matches2 as $m) {
            $og[$m[2]] = $m[1];
        }

        if (($og['type'] ?? '') === 'product' || isset($og['product:price:amount'])) {
            return [
                'name'      => $og['title'] ?? null,
                'price'     => (float) ($og['product:price:amount'] ?? 0),
                'old_price' => null,
                'brand'     => null,
                'image'     => $og['image'] ?? null,
                'description' => $og['description'] ?? '',
                'url'       => $og['url'] ?? $baseUrl,
            ];
        }

        return null;
    }

    private function parseCardHtml(string $cardHtml, string $baseUrl): ?array
    {
        // Title: first heading or link text
        $name = null;
        if (preg_match('/<(?:h[1-6]|a)[^>]*>\s*(?:<[^>]*>)*\s*([^<]{3,80})/si', $cardHtml, $m)) {
            $name = trim(strip_tags($m[1]));
        }

        // Price: number followed by currency
        $price = 0;
        $oldPrice = null;
        if (preg_match_all('/([\d]+[.,]?\d*)\s*(?:dh|MAD|DH|€|\$|TND|DA)/si', $cardHtml, $priceMatches)) {
            $prices = array_map(fn($p) => (float) str_replace(',', '', $p), $priceMatches[1]);
            $prices = array_filter($prices, fn($p) => $p > 0);
            $prices = array_values($prices);
            if (count($prices) >= 2) {
                $price = min($prices);
                $oldPrice = max($prices);
                if ($oldPrice <= $price) $oldPrice = null;
            } elseif (count($prices) === 1) {
                $price = $prices[0];
            }
        }

        // Image
        $image = null;
        if (preg_match('/<img[^>]*src=["\']([^"\']+)["\']/si', $cardHtml, $imgM)) {
            $image = $this->resolveUrl($imgM[1], $baseUrl);
        }

        if (! $name || $price <= 0) return null;

        return [
            'name'      => $name,
            'price'     => $price,
            'old_price' => $oldPrice,
            'brand'     => null,
            'image'     => $image,
            'description' => '',
            'url'       => null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function normalizeProducts(array $products, string $baseUrl): array
    {
        $seen = [];
        $normalized = [];

        foreach ($products as $p) {
            $name = trim($p['name'] ?? '');
            if (! $name || isset($seen[$name])) continue;
            $seen[$name] = true;

            // Resolve relative image URLs
            if (isset($p['image']) && $p['image']) {
                $p['image'] = $this->resolveUrl($p['image'], $baseUrl);
            }

            // Resolve relative product URLs
            if (isset($p['url']) && $p['url']) {
                $p['url'] = $this->resolveUrl($p['url'], $baseUrl);
            }

            // Clean price
            $p['price'] = round((float) ($p['price'] ?? 0), 2);
            $p['old_price'] = $p['old_price'] ? round((float) $p['old_price'], 2) : null;

            // Ensure brand is string or null
            $p['brand'] = is_string($p['brand'] ?? null) ? trim($p['brand']) : null;

            $normalized[] = $p;
        }

        return $normalized;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parsed = parse_url($baseUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        $path = dirname($parsed['path'] ?? '/');
        return $base . $path . '/' . $url;
    }
}
