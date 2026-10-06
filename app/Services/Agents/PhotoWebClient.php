<?php

namespace App\Services\Agents;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Le seul accès au web des recherches de photos sur les sites ajoutés par l'administrateur : une page HTML, ou une image.
 *
 * Garde-fous : https seulement ; l'hôte doit appartenir au domaine autorisé du site (le domaine ou un sous-domaine) ;
 * son adresse doit être publique (jamais le réseau interne du serveur) ; les redirections ne sont suivies que vers
 * le même domaine, trois au plus ; page de 1,5 Mo au plus ; image JPEG, PNG ou WebP de 4 Mo au plus et d'au moins
 * 200 pixels. En cas d'échec la cause est dite (failure()), jamais une exception.
 */
class PhotoWebClient
{
    private const UA = 'Mozilla/5.0 (compatible; O3-Agent catalogue; +https://o3app.ma)';
    private const MAX_PAGE = 1_500_000;
    private const MAX_IMAGE = 4 * 1024 * 1024;
    private const MIN_SIDE = 200;
    private const MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private ?string $failure = null;

    public function failure(): ?string
    {
        return $this->failure;
    }

    /** Le HTML d'une page du site, ou null. */
    public function page(string $url, string $domain): ?string
    {
        $this->failure = null;
        try {
            for ($hop = 0; $hop <= 3; $hop++) {
                if (!$this->safe($url, $domain)) {
                    return null;
                }
                $r = Http::timeout(15)->withoutRedirecting()->withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html,application/xhtml+xml'])->get($url);
                if ($r->redirect()) {
                    $next = $this->absolute($url, (string) $r->header('Location'));
                    if ($next === null) {
                        $this->failure = 'redirection inexploitable';

                        return null;
                    }
                    $url = $next;
                    continue;
                }
                if (!$r->successful()) {
                    $this->failure = 'le site a refusé la page (HTTP ' . $r->status() . ')';

                    return null;
                }
                if (!str_contains(strtolower((string) $r->header('Content-Type')), 'html')) {
                    $this->failure = "la réponse n'est pas une page web";

                    return null;
                }
                $body = $r->body();
                if (strlen($body) > self::MAX_PAGE) {
                    $this->failure = 'la page est trop lourde';

                    return null;
                }

                return $body;
            }
            $this->failure = 'trop de redirections';

            return null;
        } catch (\Throwable $e) {
            Log::warning('Photos (page) : ' . $e->getMessage());
            $this->failure = 'le site est injoignable ou trop lent';

            return null;
        }
    }

    /** Une image du site, vérifiée. @return array{bytes: string, mime: string, ext: string, width: int, height: int}|null */
    public function image(string $url, string $domain): ?array
    {
        $this->failure = null;
        if (!$this->safe($url, $domain)) {
            return null;
        }
        try {
            $r = Http::timeout(25)->withoutRedirecting()->withHeaders(['User-Agent' => self::UA])->get($url);
            if (!$r->successful()) {
                $this->failure = "le téléchargement de l'image a été refusé (HTTP " . $r->status() . ')';

                return null;
            }
            $bytes = $r->body();
            if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE) {
                $this->failure = "l'image est vide ou trop lourde";

                return null;
            }
            $info = @getimagesizeFromString($bytes);
            if ($info === false || !isset(self::MIME_EXT[$info['mime']])) {
                $this->failure = "le fichier reçu n'est pas une image JPEG, PNG ou WebP";

                return null;
            }
            if ($info[0] < self::MIN_SIDE || $info[1] < self::MIN_SIDE) {
                $this->failure = "l'image est trop petite ({$info[0]}×{$info[1]})";

                return null;
            }

            return ['bytes' => $bytes, 'mime' => $info['mime'], 'ext' => self::MIME_EXT[$info['mime']], 'width' => $info[0], 'height' => $info[1]];
        } catch (\Throwable $e) {
            Log::warning('Photos (image) : ' . $e->getMessage());
            $this->failure = "le serveur d'images est injoignable ou trop lent";

            return null;
        }
    }

    /** L'adresse absolue d'un lien trouvé dans une page. */
    public function absolute(string $base, string $href): ?string
    {
        $href = trim(html_entity_decode($href));
        if ($href === '' || preg_match('/^(javascript|mailto|tel|data):/i', $href)) {
            return null;
        }
        $b = parse_url($base);
        if (!is_array($b) || !isset($b['scheme'], $b['host'])) {
            return null;
        }
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return $b['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return "{$b['scheme']}://{$b['host']}{$href}";
        }
        $dir = preg_replace('~[^/]*$~', '', $b['path'] ?? '/') ?: '/';

        return "{$b['scheme']}://{$b['host']}{$dir}{$href}";
    }

    private function safe(string $url, string $domain): bool
    {
        $p = parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || !isset($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            $this->failure = "l'adresse n'est pas une adresse https valable";

            return false;
        }
        if (!PhotoSites::within($p['host'], $domain)) {
            $this->failure = "l'adresse sort du site autorisé ({$domain})";

            return false;
        }
        if (!$this->publicAddress($p['host'])) {
            $this->failure = "le nom du site ne mène pas à une adresse publique";

            return false;
        }

        return true;
    }

    /** Le serveur ne doit jamais aller chercher quelque chose sur son propre réseau. */
    private function publicAddress(string $host): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }
        $ips = @gethostbynamel($host);
        if (!is_array($ips) || $ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
