<?php

namespace App\Services\Agents;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * La seule source de photos autorisée : le site officiel jadevermall.com/ma (Jadever Maroc).
 *
 * Le site est une application JavaScript : sa recherche par référence passe par l'interface publique que le site
 * utilise lui-même pour afficher son catalogue (aucun compte, aucune clé). Le serveur la interroge directement,
 * avec les mêmes en-têtes que le navigateur du site, et télécharge l'image officielle — sans navigateur ni
 * intervention. Si le site refuse ou change, on le dit : on ne contourne rien.
 *
 * Garde-fous : hôtes figés (interface du site et serveur d'images du site), https obligatoire, aucune redirection
 * suivie, image vérifiée (type, taille, dimensions) avant d'être gardée, un seul produit par requête, une pause
 * entre deux requêtes pour ne pas surcharger le site.
 */
class JadeverPhotoSource
{
    public const SITE = 'jadevermall.com/ma';
    private const API = 'https://gatewayapi-de.rdmcenter.com/website-common-api/product';
    private const IMAGE_HOST = 'res-de.togroup.com';
    private const MAX_BYTES = 4 * 1024 * 1024;
    private const MIN_SIDE = 200;
    private const MIME_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private ?string $failure = null;

    public function failure(): ?string
    {
        return $this->failure;
    }

    /** Une référence Jadever (JD…) ? Les autres produits n'ont pas de photo officielle. */
    public static function isJadeverSku(?string $sku): bool
    {
        return is_string($sku) && preg_match('/^JD[A-Z0-9-]{3,}$/i', trim($sku)) === 1;
    }

    /**
     * Cherche la fiche officielle d'une référence. Null si le site ne la connaît pas (ou en cas d'échec : voir failure()).
     *
     * @return array{name: string, url: string}|null
     */
    public function find(string $sku): ?array
    {
        $this->failure = null;
        try {
            $response = Http::timeout(20)->withoutRedirecting()->withHeaders([
                'User-Agent'  => 'Mozilla/5.0 (compatible; O3-Agent catalogue; +https://o3app.ma)',
                'domain'      => 'www.' . self::SITE,
                'siteorigin'  => 'https://www.' . self::SITE,
                'lang'        => 'fr_FR',
                'Accept'      => 'application/json',
            ])->get(self::API, ['pageNum' => 1, 'pageSize' => 10, 'keyword' => $sku]);

            if (!$response->successful() || $response->json('code') !== '0') {
                $this->failure = 'le site ' . self::SITE . ' a refusé la recherche (' . ($response->json('message') ?: 'HTTP ' . $response->status()) . ')';

                return null;
            }

            $wanted = self::normalize($sku);
            foreach ($response->json('data.list', []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (self::normalize((string) ($item['productNo'] ?? '')) !== $wanted && self::normalize((string) ($item['productExtNo'] ?? '')) !== $wanted) {
                    continue;
                }
                $url = (string) ($item['productPics'] ?? '') ?: (string) ($item['productThumbnail'] ?? '');

                return $this->allowedImageUrl($url) ? ['name' => (string) ($item['productName'] ?? $sku), 'url' => $url] : null;
            }

            return null;   // le site ne connaît pas cette référence
        } catch (\Throwable $e) {
            Log::warning("Photos Jadever : recherche de {$sku} impossible : {$e->getMessage()}");
            $this->failure = 'le site ' . self::SITE . ' est injoignable ou trop lent';

            return null;
        }
    }

    /**
     * Télécharge et vérifie l'image. @return array{bytes: string, mime: string, ext: string, width: int, height: int}|null
     */
    public function download(string $url): ?array
    {
        $this->failure = null;
        if (!$this->allowedImageUrl($url)) {
            $this->failure = "l'adresse de l'image n'est pas celle d'un serveur du site autorisé";

            return null;
        }
        try {
            $response = Http::timeout(25)->withoutRedirecting()->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; O3-Agent catalogue; +https://o3app.ma)'])->get($url);
            if (!$response->successful()) {
                $this->failure = 'le téléchargement de l\'image a été refusé (HTTP ' . $response->status() . ')';

                return null;
            }
            $bytes = $response->body();
            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
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
            Log::warning("Photos Jadever : téléchargement impossible : {$e->getMessage()}");
            $this->failure = 'le serveur d\'images du site est injoignable ou trop lent';

            return null;
        }
    }

    private function allowedImageUrl(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && ($p['host'] ?? '') === self::IMAGE_HOST
            && !isset($p['user']) && !isset($p['port']) && str_contains($p['path'] ?? '', '/jadever/');
    }

    private static function normalize(string $s): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
    }
}
