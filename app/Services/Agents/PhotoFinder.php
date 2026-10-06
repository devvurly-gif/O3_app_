<?php

namespace App\Services\Agents;

use App\Models\Product;

/**
 * Cherche la photo d'un produit : d'abord le site officiel Jadever (références JD…), puis les sites ajoutés par
 * l'administrateur avec un modèle d'adresse de recherche, puis, en second recours, l'IA limitée à ces sites.
 *
 * Une photo trouvée sur un site ajouté doit porter une preuve : la référence du produit figure sur la page d'où elle
 * vient. Ce n'est pas une certitude, c'est pourquoi chaque photo est montrée en aperçu avant d'être rattachée.
 */
class PhotoFinder
{
    private const MAX_AI_PER_RUN = 6;
    private ?string $failure = null;
    private int $aiUsed = 0;

    public function __construct(private JadeverPhotoSource $jadever, private PhotoSites $sites, private PhotoWebClient $web, private PhotoAiSearch $ai)
    {
    }

    public function failure(): ?string
    {
        return $this->failure;
    }

    /** Y a-t-il des sites ajoutés (donc des produits non Jadever à chercher) ? */
    public function hasCustomSites(): bool
    {
        return $this->sites->custom() !== [];
    }

    /**
     * @return array{name: string, url: string, source: string, domain: ?string, method: string}|null null : introuvable (voir failure() pour une panne)
     */
    public function find(Product $product): ?array
    {
        $this->failure = null;
        $sku = trim((string) $product->p_sku);

        if (JadeverPhotoSource::isJadeverSku($sku)) {
            $found = $this->jadever->find($sku);
            $this->failure = $this->jadever->failure();

            return $found === null ? null : ['name' => $found['name'], 'url' => $found['url'], 'source' => JadeverPhotoSource::SITE, 'domain' => null, 'method' => 'jadever'];
        }

        foreach ($this->sites->withTemplate() as $site) {
            $found = $this->onSite($site, $sku);
            if ($found !== null) {
                return ['name' => $product->p_title, 'url' => $found, 'source' => $site['domain'], 'domain' => $site['domain'], 'method' => 'modele'];
            }
        }

        if ($this->sites->custom() !== [] && $this->ai->enabled() && $this->aiUsed < self::MAX_AI_PER_RUN) {
            $this->aiUsed++;
            $found = $this->ai->find($sku, (string) $product->p_title, $this->sites->domains());
            if ($found !== null) {
                return ['name' => $found['name'], 'url' => $found['url'], 'source' => $found['domain'], 'domain' => $found['domain'], 'method' => 'ia'];
            }
            $this->failure ??= $this->ai->failure();
        }

        return null;
    }

    /** Télécharge et vérifie l'image trouvée (sur le site d'office ou sur le site ajouté). @return array{bytes: string, mime: string, ext: string, width: int, height: int}|null */
    public function download(array $found): ?array
    {
        if (($found['domain'] ?? null) === null) {
            $image = $this->jadever->download($found['url']);
            $this->failure = $this->jadever->failure();

            return $image;
        }
        $image = $this->web->image($found['url'], $found['domain']);
        $this->failure = $this->web->failure();

        return $image;
    }

    /**
     * Cherche l'image sur un site à modèle d'adresse : la page de recherche, puis, au besoin, la fiche du produit
     * (un lien de la page qui contient la référence). L'image doit venir du domaine du site.
     *
     * @param array{domain: string, template: ?string} $site
     */
    private function onSite(array $site, string $sku): ?string
    {
        $url = str_replace('{ref}', rawurlencode($sku), (string) $site['template']);
        $html = $this->web->page($url, $site['domain']);
        if ($html === null) {
            $this->failure ??= "{$site['domain']} : " . ($this->web->failure() ?? 'page illisible');

            return null;
        }
        $ref = $this->norm($sku);

        $doc = $this->dom($html);
        if ($doc !== null) {
            // 1. Une fiche produit liée depuis la page (liste de résultats).
            foreach ($this->links($doc, $url, $site['domain'], $ref) as $productUrl) {
                $page = $this->web->page($productUrl, $site['domain']);
                $d = $page === null ? null : $this->dom($page);
                if ($d !== null && ($img = $this->image($d, $productUrl, $site['domain'])) !== null && $this->proven($d, $img, $ref)) {
                    return $img;
                }
            }
            // 2. La page est elle-même la fiche du produit.
            if (($img = $this->image($doc, $url, $site['domain'])) !== null && $this->proven($doc, $img, $ref)) {
                return $img;
            }
        }

        return null;
    }

    /** La preuve : la référence figure dans le texte de la page, ou dans l'adresse de l'image elle-même (jamais seulement dans l'adresse de la page). */
    private function proven(\DOMDocument $doc, string $image, string $ref): bool
    {
        return str_contains($this->norm($doc->textContent), $ref) || str_contains($this->norm((string) parse_url($image, PHP_URL_PATH)), $ref);
    }

    private function dom(string $html): ?\DOMDocument
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $doc : null;
    }

    /** @return array<int, string> au plus trois liens du site dont l'adresse ou le texte contient la référence */
    private function links(\DOMDocument $doc, string $base, string $domain, string $ref): array
    {
        $out = [];
        foreach ((new \DOMXPath($doc))->query('//a[@href]') ?: [] as $a) {
            $href = $a->getAttribute('href');
            if (!str_contains($this->norm($href . ' ' . $a->textContent), $ref)) {
                continue;
            }
            $abs = $this->web->absolute($base, $href);
            $host = $abs === null ? null : parse_url($abs, PHP_URL_HOST);
            if ($abs !== null && is_string($host) && PhotoSites::within($host, $domain) && !in_array($abs, $out, true)) {
                $out[] = $abs;
            }
            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    /** L'image principale d'une page : og:image, twitter:image, JSON-LD, puis la première vraie image. Du domaine du site seulement. */
    private function image(\DOMDocument $doc, string $base, string $domain): ?string
    {
        $xp = new \DOMXPath($doc);
        $candidates = [];
        foreach (['//meta[@property="og:image"]/@content', '//meta[@name="twitter:image"]/@content', '//meta[@property="og:image:secure_url"]/@content'] as $q) {
            foreach ($xp->query($q) ?: [] as $n) {
                $candidates[] = $n->nodeValue;
            }
        }
        foreach ($xp->query('//script[@type="application/ld+json"]') ?: [] as $s) {
            $json = json_decode($s->textContent, true);
            $img = is_array($json) ? ($json['image'] ?? ($json['@graph'][0]['image'] ?? null)) : null;
            $img = is_array($img) ? ($img[0] ?? ($img['url'] ?? null)) : $img;
            is_string($img) && $candidates[] = $img;
        }
        foreach ($xp->query('//img[@src]') ?: [] as $i) {
            $src = $i->getAttribute('src');
            $w = (int) $i->getAttribute('width');
            if ($src !== '' && !preg_match('/logo|icon|sprite|banner|placeholder|avatar|\.svg|\.gif/i', $src) && ($w === 0 || $w >= 150)) {
                $candidates[] = $src;
            }
        }
        foreach ($candidates as $c) {
            $abs = $this->web->absolute($base, (string) $c);
            $host = $abs === null ? null : parse_url($abs, PHP_URL_HOST);
            if ($abs !== null && is_string($host) && PhotoSites::within($host, $domain) && str_starts_with($abs, 'https://')) {
                return $abs;
            }
        }

        return null;
    }

    private function norm(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
    }
}
