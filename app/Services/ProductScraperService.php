<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use DOMNode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductScraperService
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Scrape products from any ecommerce URL.
     *
     * Pipeline:
     * 1. JSON-LD structured data (Schema.org — best quality)
     * 2. Platform-specific APIs (Shopify, WooCommerce, PrestaShop)
     * 3. Open Graph meta (single product pages)
     * 4. Smart DOM-based product card detection
     * 5. Pagination: follow "next" links to get all pages
     *
     * @return array{products: array, source: string, count: int}
     */
    public function scrape(string $url): array
    {
        $html = $this->fetchPage($url);
        $domain = parse_url($url, PHP_URL_HOST);
        $dom = $this->parseDom($html);

        $products = [];

        // 1. JSON-LD (most reliable, works on most modern sites)
        $products = $this->extractJsonLd($html, $url);

        // 2. Platform-specific extraction
        if (empty($products)) {
            $platform = $this->detectPlatform($html, $url);

            $products = match ($platform) {
                'shopify'     => $this->extractShopify($html, $url),
                'woocommerce' => $this->extractWooCommerce($html, $url, $dom),
                'prestashop'  => $this->extractPrestaShop($html, $url, $dom),
                default       => [],
            };
        }

        // 3. Open Graph (single product page)
        if (empty($products)) {
            $ogProduct = $this->extractOpenGraph($dom, $url);
            if ($ogProduct) $products = [$ogProduct];
        }

        // 4. Smart DOM-based card extraction (universal fallback)
        if (empty($products)) {
            $products = $this->extractProductCards($dom, $html, $url);
        }

        // 5. Pagination — try to get more products from next pages
        if (! empty($products)) {
            $products = $this->followPagination($products, $dom, $html, $url);
        }

        // Normalize and deduplicate
        $products = $this->normalizeProducts($products, $url);

        return [
            'products' => $products,
            'source'   => $domain,
            'count'    => count($products),
        ];
    }

    // ── HTTP ─────────────────────────────────────────────────────────

    private function fetchPage(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent'      => $this->userAgent,
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8,ar;q=0.7',
        ])->timeout(20)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Impossible de charger la page: HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function fetchJson(string $url, array $query = []): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept'     => 'application/json',
            ])->timeout(15)->get($url, $query);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ── DOM ──────────────────────────────────────────────────────────

    private function parseDom(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    // ── Platform detection ───────────────────────────────────────────

    private function detectPlatform(string $html, string $url): string
    {
        // Shopify
        if (str_contains($html, 'cdn.shopify.com') || str_contains($html, 'Shopify.theme') || str_contains($html, 'myshopify.com')) {
            return 'shopify';
        }

        // WooCommerce
        if (str_contains($html, 'woocommerce') || str_contains($html, 'wc-block') || str_contains($html, 'wp-content/plugins/woocommerce')) {
            return 'woocommerce';
        }

        // PrestaShop
        if (str_contains($html, 'prestashop') || str_contains($html, 'PrestaShop') || str_contains($html, '/modules/ps_') || str_contains($html, 'id="js-product-list"')) {
            return 'prestashop';
        }

        // Magento
        if (str_contains($html, 'Magento') || str_contains($html, 'mage/') || str_contains($html, 'catalog-product')) {
            return 'magento';
        }

        // OpenCart
        if (str_contains($html, 'opencart') || str_contains($html, 'route=product/category')) {
            return 'opencart';
        }

        return 'unknown';
    }

    // ══════════════════════════════════════════════════════════════════
    //  1. JSON-LD (Schema.org)
    // ══════════════════════════════════════════════════════════════════

    private function extractJsonLd(string $html, string $baseUrl): array
    {
        $products = [];

        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $data = @json_decode(trim($json), true);
            if (! $data) continue;

            $items = $this->flattenJsonLd($data);

            foreach ($items as $item) {
                $type = $item['@type'] ?? '';

                if ($type === 'Product') {
                    $p = $this->parseJsonLdProduct($item, $baseUrl);
                    if ($p) $products[] = $p;
                }

                if (in_array($type, ['ItemList', 'CollectionPage', 'OfferCatalog'])) {
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

    private function flattenJsonLd(array $data): array
    {
        if (isset($data['@graph'])) return $data['@graph'];
        if (isset($data['@type'])) return [$data];
        if (isset($data[0])) return $data;
        return [$data];
    }

    private function parseJsonLdProduct(array $data, string $baseUrl): ?array
    {
        $name = $data['name'] ?? null;
        if (! $name) return null;

        $price = 0;
        $oldPrice = null;
        $offers = $data['offers'] ?? $data['offer'] ?? null;

        if ($offers) {
            // Can be a single offer or AggregateOffer or array
            if (isset($offers[0])) $offers = $offers[0];
            $price = (float) ($offers['price'] ?? $offers['lowPrice'] ?? 0);
            if (isset($offers['highPrice']) && (float) $offers['highPrice'] > $price) {
                $oldPrice = (float) $offers['highPrice'];
            }
        }

        $image = $data['image'] ?? null;
        if (is_array($image)) $image = $image[0] ?? ($image['url'] ?? null);

        $brand = $data['brand']['name'] ?? $data['brand'] ?? null;
        if (is_array($brand)) $brand = $brand['name'] ?? null;

        return [
            'name'        => $name,
            'price'       => $price,
            'old_price'   => $oldPrice,
            'brand'       => is_string($brand) ? $brand : null,
            'image'       => $image,
            'description' => Str::limit($data['description'] ?? '', 500),
            'url'         => $data['url'] ?? null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  2a. Shopify
    // ══════════════════════════════════════════════════════════════════

    private function extractShopify(string $html, string $baseUrl): array
    {
        // Strategy 1: /products.json API (most reliable, paginated)
        $products = $this->fetchShopifyProductsJson($baseUrl);
        if (! empty($products)) return $products;

        // Strategy 2: meta.products JS variable
        $products = $this->parseShopifyMeta($html, $baseUrl);
        if (! empty($products)) return $products;

        // Strategy 3: data-product JSON attributes
        $products = $this->parseShopifyDataAttributes($html, $baseUrl);
        if (! empty($products)) return $products;

        // Strategy 4: HTML card parsing
        return $this->parseShopifyCards($html, $baseUrl);
    }

    private function fetchShopifyProductsJson(string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $baseHost = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '';

        $jsonUrl = preg_match('#/collections/[^/]+#', $path, $m)
            ? $baseHost . $m[0] . '/products.json'
            : $baseHost . '/products.json';

        $products = [];
        $page = 1;

        while ($page <= 10) {
            $data = $this->fetchJson($jsonUrl, ['page' => $page, 'limit' => 250]);
            $items = $data['products'] ?? [];
            if (empty($items)) break;

            foreach ($items as $item) {
                $products[] = $this->mapShopifyProduct($item, $baseHost);
            }

            if (count($items) < 250) break;
            $page++;
        }

        return $products;
    }

    private function mapShopifyProduct(array $item, string $baseHost): array
    {
        $price = 0;
        $oldPrice = null;

        if (! empty($item['variants'])) {
            $v = $item['variants'][0];
            $price = (float) ($v['price'] ?? 0);
            $cap = (float) ($v['compare_at_price'] ?? 0);
            if ($cap > $price) $oldPrice = $cap;
        }

        $image = null;
        if (! empty($item['images'])) {
            $image = $item['images'][0]['src'] ?? null;
        } elseif (! empty($item['image'])) {
            $image = $item['image']['src'] ?? $item['image'] ?? null;
        }

        return [
            'name'        => $item['title'] ?? '',
            'price'       => $price,
            'old_price'   => $oldPrice,
            'brand'       => $item['vendor'] ?? null,
            'image'       => $image,
            'description' => Str::limit(strip_tags($item['body_html'] ?? ''), 500),
            'url'         => $baseHost . '/products/' . ($item['handle'] ?? ''),
        ];
    }

    private function parseShopifyMeta(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $baseHost = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        $patterns = [
            '/var\s+meta\s*=\s*(\{.*?\});\s*$/ms',
            '/"products"\s*:\s*(\[.*?\])\s*[,}]/s',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $html, $m)) continue;

            $data = @json_decode($m[1], true);
            if (! $data) continue;

            if (isset($data['products'])) $data = $data['products'];
            if (! is_array($data) || empty($data)) continue;

            $products = [];
            foreach ($data as $item) {
                $name = $item['title'] ?? $item['name'] ?? null;
                if (! $name) continue;

                $price = $this->extractShopifyPrice($item);
                $oldPrice = null;
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

        return [];
    }

    private function extractShopifyPrice(array $item): float
    {
        if (isset($item['variants'][0]['price'])) {
            return (float) $item['variants'][0]['price'];
        }
        if (isset($item['price'])) {
            $p = (float) $item['price'];
            // Shopify sometimes stores prices in cents
            return $p > 100000 ? $p / 100 : $p;
        }
        return 0;
    }

    private function parseShopifyDataAttributes(string $html, string $baseUrl): array
    {
        $products = [];
        preg_match_all('/data-product=["\']({.*?})["\']/s', $html, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $data = @json_decode(html_entity_decode($json), true);
            if (! $data || ! isset($data['title'])) continue;

            $products[] = [
                'name'        => $data['title'],
                'price'       => (float) ($data['price'] ?? 0) / 100,
                'old_price'   => isset($data['compare_at_price']) ? (float) $data['compare_at_price'] / 100 : null,
                'brand'       => $data['vendor'] ?? null,
                'image'       => isset($data['featured_image']) ? 'https:' . $data['featured_image'] : null,
                'description' => $data['description'] ?? '',
                'url'         => $baseUrl,
            ];
        }

        return $products;
    }

    private function parseShopifyCards(string $html, string $baseUrl): array
    {
        $dom = $this->parseDom($html);
        return $this->extractProductCards($dom, $html, $baseUrl);
    }

    // ══════════════════════════════════════════════════════════════════
    //  2b. WooCommerce
    // ══════════════════════════════════════════════════════════════════

    private function extractWooCommerce(string $html, string $baseUrl, DOMXPath $dom): array
    {
        $parsed = parse_url($baseUrl);
        $baseHost = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // Strategy 1: WooCommerce Store API (WC 7.6+)
        $products = $this->fetchWooCommerceApi($baseHost);
        if (! empty($products)) return $products;

        // Strategy 2: Parse wc_product_data / wc JSON embedded in page
        $products = $this->parseWooCommerceJson($html, $baseUrl);
        if (! empty($products)) return $products;

        // Strategy 3: DOM-based extraction of .products .product cards
        return $this->parseWooCommerceCards($dom, $baseUrl);
    }

    private function fetchWooCommerceApi(string $baseHost): array
    {
        // WC Store API — public, no auth needed
        $data = $this->fetchJson($baseHost . '/wp-json/wc/store/v1/products', ['per_page' => 100]);
        if (! $data || ! is_array($data)) return [];

        $products = [];
        foreach ($data as $item) {
            $price = 0;
            $oldPrice = null;

            if (isset($item['prices'])) {
                $price = (float) ($item['prices']['price'] ?? 0) / (10 ** (int) ($item['prices']['currency_minor_unit'] ?? 0));
                $reg = (float) ($item['prices']['regular_price'] ?? 0) / (10 ** (int) ($item['prices']['currency_minor_unit'] ?? 0));
                if ($reg > $price) $oldPrice = $reg;
            }

            $image = null;
            if (! empty($item['images'])) {
                $image = $item['images'][0]['src'] ?? null;
            }

            $products[] = [
                'name'        => $item['name'] ?? '',
                'price'       => $price,
                'old_price'   => $oldPrice,
                'brand'       => null,
                'image'       => $image,
                'description' => Str::limit(strip_tags($item['short_description'] ?? $item['description'] ?? ''), 500),
                'url'         => $item['permalink'] ?? null,
            ];
        }

        return $products;
    }

    private function parseWooCommerceJson(string $html, string $baseUrl): array
    {
        $products = [];

        // WooCommerce sometimes embeds product data in JS
        if (preg_match('/var\s+wc_product_params\s*=\s*(\{.*?\});/s', $html, $m)) {
            $data = @json_decode($m[1], true);
            if ($data && isset($data['product_id'])) {
                // Single product page data
                $products[] = [
                    'name'        => $data['product_title'] ?? '',
                    'price'       => (float) ($data['product_price'] ?? 0),
                    'old_price'   => null,
                    'brand'       => null,
                    'image'       => null,
                    'description' => '',
                    'url'         => $baseUrl,
                ];
            }
        }

        return $products;
    }

    private function parseWooCommerceCards(DOMXPath $dom, string $baseUrl): array
    {
        $products = [];

        // WooCommerce uses ul.products > li.product
        $nodes = $dom->query('//ul[contains(@class,"products")]//li[contains(@class,"product")]');
        if (! $nodes || $nodes->length === 0) {
            // Alt: div.products > div.product
            $nodes = $dom->query('//*[contains(@class,"products")]//*[contains(@class,"product") and not(contains(@class,"products"))]');
        }

        if (! $nodes) return [];

        foreach ($nodes as $node) {
            $p = $this->parseCardDom($node, $dom, $baseUrl);
            if ($p && $p['name']) $products[] = $p;
        }

        return $products;
    }

    // ══════════════════════════════════════════════════════════════════
    //  2c. PrestaShop
    // ══════════════════════════════════════════════════════════════════

    private function extractPrestaShop(string $html, string $baseUrl, DOMXPath $dom): array
    {
        $products = [];

        // Strategy 1: PrestaShop embeds productList JSON in page
        if (preg_match('/var\s+productList\s*=\s*(\[.*?\]);/s', $html, $m)) {
            $data = @json_decode($m[1], true);
            if ($data && is_array($data)) {
                foreach ($data as $item) {
                    $products[] = $this->mapPrestaProduct($item, $baseUrl);
                }
                if (! empty($products)) return $products;
            }
        }

        // Strategy 2: prestashop data in data-product-attribute or JSON script
        if (preg_match_all('/data-id-product=["\'](\d+)["\']/s', $html, $ids)) {
            // Products are in the page — use DOM extraction
        }

        // Strategy 3: Parse #js-product-list cards
        $nodes = $dom->query('//*[@id="js-product-list"]//*[contains(@class,"product-miniature") or contains(@class,"product_")]');
        if (! $nodes || $nodes->length === 0) {
            $nodes = $dom->query('//*[contains(@class,"product-miniature") or contains(@class,"product_list")]');
        }

        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $p = $this->parseCardDom($node, $dom, $baseUrl);
                if ($p && $p['name']) $products[] = $p;
            }
        }

        return $products;
    }

    private function mapPrestaProduct(array $item, string $baseUrl): array
    {
        $price = (float) ($item['price_amount'] ?? $item['price'] ?? 0);
        $oldPrice = null;
        if (isset($item['regular_price_amount']) && (float) $item['regular_price_amount'] > $price) {
            $oldPrice = (float) $item['regular_price_amount'];
        }
        if (isset($item['has_discount']) && $item['has_discount'] && isset($item['regular_price'])) {
            $reg = $this->parsePrice($item['regular_price']);
            if ($reg > $price) $oldPrice = $reg;
        }

        $image = $item['cover']['large']['url']
            ?? $item['cover']['medium']['url']
            ?? $item['cover']['bySize']['medium_default']['url']
            ?? $item['cover']['url']
            ?? $item['image']['url']
            ?? null;

        return [
            'name'        => $item['name'] ?? '',
            'price'       => $price,
            'old_price'   => $oldPrice,
            'brand'       => $item['manufacturer_name'] ?? null,
            'image'       => $image,
            'description' => Str::limit(strip_tags($item['description_short'] ?? $item['description'] ?? ''), 500),
            'url'         => $item['url'] ?? $item['link'] ?? null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  3. Open Graph (single product page)
    // ══════════════════════════════════════════════════════════════════

    private function extractOpenGraph(DOMXPath $dom, string $baseUrl): ?array
    {
        $og = [];

        // Use XPath to find OG meta tags
        $metas = $dom->query('//meta[starts-with(@property,"og:") or starts-with(@property,"product:")]');
        if ($metas) {
            foreach ($metas as $meta) {
                $prop = $meta->getAttribute('property');
                $content = $meta->getAttribute('content');
                if ($prop && $content) {
                    $key = preg_replace('/^og:/', '', $prop);
                    $og[$key] = $content;
                }
            }
        }

        // Also check twitter:data tags for price
        $twitterMetas = $dom->query('//meta[starts-with(@name,"twitter:")]');
        if ($twitterMetas) {
            foreach ($twitterMetas as $meta) {
                $name = $meta->getAttribute('name');
                $content = $meta->getAttribute('content');
                if (str_contains($name, 'label') && str_contains(strtolower($content), 'pri')) {
                    // Next twitter:data tag should have the price
                    $dataName = str_replace('label', 'data', $name);
                    $dataMeta = $dom->query("//meta[@name='{$dataName}']")->item(0);
                    if ($dataMeta) {
                        $og['product:price:amount'] = $this->parsePrice($dataMeta->getAttribute('content'));
                    }
                }
            }
        }

        if (($og['type'] ?? '') === 'product' || isset($og['product:price:amount'])) {
            return [
                'name'        => $og['title'] ?? null,
                'price'       => (float) ($og['product:price:amount'] ?? 0),
                'old_price'   => null,
                'brand'       => $og['product:brand'] ?? null,
                'image'       => $og['image'] ?? null,
                'description' => $og['description'] ?? '',
                'url'         => $og['url'] ?? $baseUrl,
            ];
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════
    //  4. Smart DOM-based product card detection (universal)
    // ══════════════════════════════════════════════════════════════════

    private function extractProductCards(DOMXPath $dom, string $html, string $baseUrl): array
    {
        // Try specific selectors first, then fall back to heuristic detection
        $selectorGroups = [
            // Common ecommerce class patterns
            '//*[contains(@class,"product-card") or contains(@class,"product-item") or contains(@class,"product_item")]',
            '//*[contains(@class,"product-miniature") or contains(@class,"product-thumb")]',
            '//*[contains(@class,"card-product") or contains(@class,"item-product")]',
            // Generic card patterns in a product grid/list
            '//*[contains(@class,"product")]//*[contains(@class,"card")]',
            '//*[contains(@class,"products") or contains(@class,"product-list") or contains(@class,"product-grid")]//*[self::div or self::li or self::article][contains(@class,"product") or contains(@class,"card") or contains(@class,"item")]',
            // Broader: any repeated child in a grid with "product" ancestor
            '//*[contains(@class,"grid") or contains(@class,"list") or contains(@class,"collection")]//*[self::div or self::li or self::article]',
        ];

        foreach ($selectorGroups as $xpath) {
            $nodes = $dom->query($xpath);
            if (! $nodes || $nodes->length < 2) continue;

            $products = [];
            foreach ($nodes as $node) {
                $p = $this->parseCardDom($node, $dom, $baseUrl);
                if ($p && $p['name'] && $p['price'] > 0) {
                    $products[] = $p;
                }
            }

            if (count($products) >= 2) return $products;
        }

        // Last resort: regex-based extraction from raw HTML
        return $this->extractProductsRegex($html, $baseUrl);
    }

    /**
     * Parse a single product card DOM node to extract name, price, image, url.
     */
    private function parseCardDom(DOMNode $node, DOMXPath $dom, string $baseUrl): ?array
    {
        $html = $node->ownerDocument->saveHTML($node);

        // ── Name ──
        $name = null;

        // Try heading tags first
        $headings = $dom->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $node);
        if ($headings && $headings->length > 0) {
            $name = trim($headings->item(0)->textContent);
        }

        // Try elements with title/name class
        if (! $name) {
            $titleNodes = $dom->query('.//*[contains(@class,"title") or contains(@class,"name") or contains(@class,"product-title") or contains(@class,"product-name")]', $node);
            if ($titleNodes && $titleNodes->length > 0) {
                $name = trim($titleNodes->item(0)->textContent);
            }
        }

        // Try first link text (non-empty, non-trivial)
        if (! $name) {
            $links = $dom->query('.//a[string-length(normalize-space()) > 3]', $node);
            if ($links && $links->length > 0) {
                $name = trim($links->item(0)->textContent);
            }
        }

        if (! $name || strlen($name) < 3) return null;

        // ── URL ──
        $url = null;
        $links = $dom->query('.//a[@href]', $node);
        if ($links && $links->length > 0) {
            $href = $links->item(0)->getAttribute('href');
            if ($href && $href !== '#' && ! str_starts_with($href, 'javascript:')) {
                $url = $this->resolveUrl($href, $baseUrl);
            }
        }

        // ── Image ──
        $image = null;
        // Try data-src first (lazy loading), then src
        $imgs = $dom->query('.//img[@data-src or @data-lazy-src or @data-original or @src]', $node);
        if ($imgs && $imgs->length > 0) {
            $img = $imgs->item(0);
            $image = $img->getAttribute('data-src')
                ?: $img->getAttribute('data-lazy-src')
                ?: $img->getAttribute('data-original')
                ?: $img->getAttribute('src');
        }

        // Also check background-image in style attributes
        if (! $image) {
            $bgNodes = $dom->query('.//*[contains(@style,"background-image")]', $node);
            if ($bgNodes && $bgNodes->length > 0) {
                $style = $bgNodes->item(0)->getAttribute('style');
                if (preg_match('/url\(["\']?([^"\')\s]+)["\']?\)/', $style, $bgM)) {
                    $image = $bgM[1];
                }
            }
        }

        if ($image) {
            $image = $this->resolveUrl($image, $baseUrl);
        }

        // ── Price ──
        $price = 0;
        $oldPrice = null;
        $prices = $this->extractPricesFromHtml($html);
        if (! empty($prices)) {
            $price = min($prices);
            if (count($prices) >= 2) {
                $oldPrice = max($prices);
                if ($oldPrice <= $price) $oldPrice = null;
            }
        }

        // ── Brand ──
        $brand = null;
        $brandNodes = $dom->query('.//*[contains(@class,"brand") or contains(@class,"vendor") or contains(@class,"manufacturer")]', $node);
        if ($brandNodes && $brandNodes->length > 0) {
            $brand = trim($brandNodes->item(0)->textContent);
        }

        return [
            'name'        => Str::limit($name, 200),
            'price'       => $price,
            'old_price'   => $oldPrice,
            'brand'       => $brand,
            'image'       => $image,
            'description' => '',
            'url'         => $url,
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  5. Pagination
    // ══════════════════════════════════════════════════════════════════

    private function followPagination(array $products, DOMXPath $dom, string $html, string $baseUrl, int $maxPages = 5): array
    {
        $visited = [rtrim(strtok($baseUrl, '?'), '/')];
        $pagesLoaded = 1;

        $currentHtml = $html;
        $currentDom = $dom;
        $currentUrl = $baseUrl;

        while ($pagesLoaded < $maxPages) {
            $nextUrl = $this->findNextPageUrl($currentDom, $currentHtml, $currentUrl);
            if (! $nextUrl) break;

            $normalizedNext = rtrim(strtok($nextUrl, '?'), '/');
            if (in_array($normalizedNext, $visited)) break;

            try {
                $currentHtml = $this->fetchPage($nextUrl);
            } catch (\Exception $e) {
                break;
            }

            $visited[] = $normalizedNext;
            $currentDom = $this->parseDom($currentHtml);
            $currentUrl = $nextUrl;

            // Extract products from this page with same pipeline
            $pageProducts = $this->extractJsonLd($currentHtml, $nextUrl);

            if (empty($pageProducts)) {
                $platform = $this->detectPlatform($currentHtml, $nextUrl);
                $pageProducts = match ($platform) {
                    'shopify'     => $this->extractShopify($currentHtml, $nextUrl),
                    'woocommerce' => $this->extractWooCommerce($currentHtml, $nextUrl, $currentDom),
                    'prestashop'  => $this->extractPrestaShop($currentHtml, $nextUrl, $currentDom),
                    default       => [],
                };
            }

            if (empty($pageProducts)) {
                $pageProducts = $this->extractProductCards($currentDom, $currentHtml, $nextUrl);
            }

            if (empty($pageProducts)) break;

            $products = array_merge($products, $pageProducts);
            $pagesLoaded++;
        }

        return $products;
    }

    private function findNextPageUrl(DOMXPath $dom, string $html, string $currentUrl): ?string
    {
        // XPath: find "next" pagination links
        $nextSelectors = [
            '//a[contains(@class,"next")]/@href',
            '//a[contains(@rel,"next")]/@href',
            '//li[contains(@class,"next")]//a/@href',
            '//*[contains(@class,"pagination")]//a[contains(@class,"next") or contains(text(),"Suivant") or contains(text(),"Next") or contains(text(),"›") or contains(text(),"»")]/@href',
            '//*[contains(@class,"pagination")]//a[contains(@aria-label,"Next") or contains(@aria-label,"Suivant")]/@href',
        ];

        foreach ($nextSelectors as $xpath) {
            $nodes = $dom->query($xpath);
            if ($nodes && $nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                if ($href && $href !== '#') {
                    return $this->resolveUrl($href, $currentUrl);
                }
            }
        }

        // Regex fallback: look for page=N or p=N pattern where N = current + 1
        $currentPage = 1;
        if (preg_match('/[?&](?:page|p|pg)=(\d+)/', $currentUrl, $m)) {
            $currentPage = (int) $m[1];
        }

        $nextPage = $currentPage + 1;
        if (preg_match('/href=["\']([^"\']*[?&](?:page|p|pg)=' . $nextPage . '(?:&[^"\']*)?)["\']/', $html, $m)) {
            return $this->resolveUrl(html_entity_decode($m[1]), $currentUrl);
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Price extraction (universal)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Extract all prices found in an HTML fragment.
     * Supports: 1,234.56 | 1.234,56 | 1 234.56 | plain 1234
     * Currencies: MAD/DH/dh, EUR/€, USD/$, TND, DA, GBP/£, SAR, AED, EGP, XOF/CFA, etc.
     */
    private function extractPricesFromHtml(string $html): array
    {
        // Remove HTML tags but keep the text
        $text = strip_tags($html);

        $prices = [];

        // Pattern: number with optional thousand/decimal separators, optionally followed or preceded by currency
        $currencies = 'MAD|DH|dh|Dh|د\.م|€|EUR|\$|USD|TND|DA|£|GBP|SAR|AED|EGP|XOF|CFA|FCFA|ر\.س|د\.إ|ج\.م|KWD';
        $numberPattern = '[\d]{1,3}(?:[.,\s]\d{3})*(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?';

        // Currency before number: $1,234.56 or € 99.00
        $pattern1 = '/(?:' . $currencies . ')\s*(' . $numberPattern . ')/iu';
        // Number before currency: 1,234.56 MAD or 99.00€
        $pattern2 = '/(' . $numberPattern . ')\s*(?:' . $currencies . ')/iu';

        foreach ([$pattern1, $pattern2] as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $raw) {
                    $p = $this->parsePrice($raw);
                    if ($p > 0 && $p < 10000000) {
                        $prices[] = $p;
                    }
                }
            }
        }

        // Also check data attributes: data-price, data-product-price, content attributes with price
        if (preg_match_all('/data-(?:price|product-price|amount)=["\']([^"\']+)["\']/i', $html, $dataMatches)) {
            foreach ($dataMatches[1] as $raw) {
                $p = (float) $raw;
                if ($p > 0 && $p < 10000000) $prices[] = $p;
            }
        }

        return array_unique($prices);
    }

    /**
     * Parse a raw price string into a float.
     * Handles: "1,234.56" "1.234,56" "1 234" "1234"
     */
    private function parsePrice(string $raw): float
    {
        $raw = trim($raw);

        // Remove currency symbols and whitespace
        $raw = preg_replace('/[^\d.,\s]/', '', $raw);
        $raw = trim($raw);

        if (! $raw) return 0;

        // Detect format:
        // "1.234,56" → European (dot=thousand, comma=decimal)
        // "1,234.56" → US/UK (comma=thousand, dot=decimal)
        // "1 234,56" → French (space=thousand, comma=decimal)
        // "1234" → integer

        // Replace spaces (thousand separator)
        $raw = str_replace(' ', '', $raw);

        $lastDot = strrpos($raw, '.');
        $lastComma = strrpos($raw, ',');

        if ($lastDot !== false && $lastComma !== false) {
            if ($lastDot > $lastComma) {
                // Format: 1,234.56 → remove commas
                $raw = str_replace(',', '', $raw);
            } else {
                // Format: 1.234,56 → remove dots, replace comma with dot
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            }
        } elseif ($lastComma !== false) {
            // Could be "1,234" (thousand) or "12,50" (decimal)
            $afterComma = substr($raw, $lastComma + 1);
            if (strlen($afterComma) === 3 && substr_count($raw, ',') === 1 && $lastComma > 0) {
                // Likely thousand separator: 1,234
                $raw = str_replace(',', '', $raw);
            } else {
                // Likely decimal: 12,50 or 1.234,56
                $raw = str_replace(',', '.', $raw);
            }
        }
        // Dots: if single dot, it's decimal. If multiple dots, they're thousands.
        elseif ($lastDot !== false && substr_count($raw, '.') > 1) {
            // Multiple dots = thousand separators: 1.234.567
            $raw = str_replace('.', '', $raw);
        }

        return (float) $raw;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Regex fallback (last resort for unknown HTML structures)
    // ══════════════════════════════════════════════════════════════════

    private function extractProductsRegex(string $html, string $baseUrl): array
    {
        $products = [];

        // Find repeated structures that look like product cards
        // Look for blocks containing both a title-like link and a price
        $currencies = 'MAD|DH|dh|€|\$|TND|DA|£|SAR|AED';
        $pricePattern = '([\d]+[.,\s]?\d*)\s*(?:' . $currencies . ')';

        // Find all links to product-like URLs
        preg_match_all('/<a[^>]*href=["\']([^"\']*(?:product|produit|article|item)[^"\']*)["\'][^>]*>\s*(?:<[^>]*>)*\s*([^<]{5,100})/si', $html, $linkMatches, PREG_SET_ORDER);

        if (count($linkMatches) >= 2) {
            foreach ($linkMatches as $link) {
                $url = $this->resolveUrl($link[1], $baseUrl);
                $name = trim(strip_tags($link[2]));

                // Look for a price near this link in the HTML
                $pos = strpos($html, $link[0]);
                $context = substr($html, max(0, $pos - 200), 600);
                $prices = $this->extractPricesFromHtml($context);

                $products[] = [
                    'name'        => $name,
                    'price'       => ! empty($prices) ? min($prices) : 0,
                    'old_price'   => count($prices) >= 2 ? max($prices) : null,
                    'brand'       => null,
                    'image'       => null,
                    'description' => '',
                    'url'         => $url,
                ];
            }
        }

        return $products;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════════

    private function normalizeProducts(array $products, string $baseUrl): array
    {
        $seen = [];
        $normalized = [];

        foreach ($products as $p) {
            $name = trim($p['name'] ?? '');
            if (! $name) continue;

            // Deduplicate by name (case-insensitive)
            $key = mb_strtolower($name);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            // Resolve relative URLs
            if (! empty($p['image'])) {
                $p['image'] = $this->resolveUrl($p['image'], $baseUrl);
            }
            if (! empty($p['url'])) {
                $p['url'] = $this->resolveUrl($p['url'], $baseUrl);
            }

            // Clean price
            $p['price'] = round((float) ($p['price'] ?? 0), 2);
            $p['old_price'] = ! empty($p['old_price']) ? round((float) $p['old_price'], 2) : null;
            if ($p['old_price'] && $p['old_price'] <= $p['price']) $p['old_price'] = null;

            // Ensure brand is string or null
            $p['brand'] = is_string($p['brand'] ?? null) ? trim($p['brand']) ?: null : null;

            // Clean name
            $p['name'] = html_entity_decode(trim($name), ENT_QUOTES, 'UTF-8');

            $normalized[] = $p;
        }

        return $normalized;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        $path = dirname($parsed['path'] ?? '/');
        return $base . rtrim($path, '/') . '/' . $url;
    }
}
