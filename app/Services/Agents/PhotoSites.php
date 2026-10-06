<?php

namespace App\Services\Agents;

use App\Models\Setting;

/**
 * Les sites sur lesquels l'orchestrateur a le droit de chercher des photos de produits.
 *
 * Il y en a un d'office, jadevermall.com/ma (adaptateur dédié, voir JadeverPhotoSource), et ceux que l'administrateur
 * ajoute : un domaine, avec éventuellement un modèle d'adresse de recherche contenant {ref} (par exemple
 * https://site.ma/recherche?q={ref}). Sans modèle, le site n'est cherché que par l'IA, limitée à ce domaine.
 *
 * Règles de sécurité : https obligatoire, nom de domaine public (ni adresse IP, ni « localhost », ni nom interne),
 * aucun identifiant ni port dans l'adresse, et les images ne sont acceptées que du domaine du site ou de ses
 * sous-domaines. La liste est un réglage de l'entreprise (agents.photo_sites).
 */
class PhotoSites
{
    private const KEY = 'photo_sites';
    public const MAX_SITES = 12;
    private const BLOCKED_SUFFIXES = ['.local', '.localhost', '.internal', '.intranet', '.lan', '.home', '.corp', '.test', '.invalid', '.example'];

    /** @return array<int, array{domain: string, template: ?string, added_by: string, added_at: string}> les sites ajoutés par l'administrateur */
    public function custom(): array
    {
        $raw = json_decode((string) Setting::get('agents', self::KEY, '[]'), true);

        return is_array($raw) ? array_values(array_filter($raw, fn ($s) => is_array($s) && is_string($s['domain'] ?? null))) : [];
    }

    /** Les sites ajoutés qui ont un modèle d'adresse (cherchés sans IA). @return array<int, array{domain: string, template: ?string, added_by: string, added_at: string}> */
    public function withTemplate(): array
    {
        return array_values(array_filter($this->custom(), fn ($s) => !empty($s['template'])));
    }

    /** @return array<int, string> les domaines ajoutés */
    public function domains(): array
    {
        return array_map(fn ($s) => $s['domain'], $this->custom());
    }

    public function has(string $domain): bool
    {
        return in_array(strtolower($domain), $this->domains(), true);
    }

    public function add(string $domain, ?string $template, string $by): void
    {
        $sites = $this->custom();
        $sites[] = ['domain' => strtolower($domain), 'template' => $template, 'added_by' => $by, 'added_at' => now()->toDateTimeString()];
        Setting::set('agents', self::KEY, json_encode(array_values($sites), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function remove(string $domain): bool
    {
        $domain = strtolower($domain);
        $before = $this->custom();
        $after = array_values(array_filter($before, fn ($s) => $s['domain'] !== $domain));
        if (count($after) === count($before)) {
            return false;
        }
        Setting::set('agents', self::KEY, json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * Lit « autorise le site https://www.site.ma/recherche?q={ref} » ou « ajoute le site site.ma ».
     *
     * @param string $text la phrase d'origine (l'adresse garde sa casse)
     * @return array{ok: true, domain: string, template: ?string}|array{ok: false, error: string}
     */
    public function parse(string $text): array
    {
        if (preg_match('~(https?://[^\s«»"\'<>]+)~i', $text, $m)) {
            $url = rtrim($m[1], '.,;)');
        } elseif (preg_match('~(?<![@\w.-])((?:[a-z0-9][a-z0-9-]*\.)+[a-z]{2,})(/[^\s«»"\'<>]*)?~i', $text, $m)) {
            $url = 'https://' . $m[1] . ($m[2] ?? '');
        } else {
            return ['ok' => false, 'error' => "Je ne vois pas d'adresse de site dans cette phrase. Donnez-la en entier, par exemple « autorise le site https://exemple.ma/recherche?q={ref} »."];
        }

        if (stripos($url, 'http://') === 0) {
            return ['ok' => false, 'error' => 'Seules les adresses https:// sont acceptées (le téléchargement doit être chiffré).'];
        }

        $hasRef = (bool) preg_match('/\{ref\}|%7Bref%7D/i', $url);
        $probe = preg_replace('/\{ref\}|%7Bref%7D/i', 'REF123', $url) ?? $url;
        $p = parse_url($probe);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || !isset($p['host'])) {
            return ['ok' => false, 'error' => "Cette adresse n'est pas valable."];
        }
        if (isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            return ['ok' => false, 'error' => "Une adresse avec identifiant ou numéro de port n'est pas acceptée."];
        }
        $host = strtolower($p['host']);
        if (!self::publicName($host)) {
            return ['ok' => false, 'error' => "« {$host} » n'est pas un nom de site public (adresse IP, nom interne ou local : refusé)."];
        }
        $domain = preg_replace('/^www\./', '', $host) ?? $host;
        if ($hasRef && stripos(parse_url($probe, PHP_URL_HOST) ?: '', 'REF123') !== false) {
            return ['ok' => false, 'error' => 'Le {ref} doit être dans le chemin ou dans la recherche, pas dans le nom du site.'];
        }
        if ($domain === 'jadevermall.com' || str_ends_with($domain, '.jadevermall.com')) {
            return ['ok' => false, 'error' => 'jadevermall.com/ma est déjà un site autorisé d\'office.'];
        }
        if ($this->has($domain)) {
            return ['ok' => false, 'error' => "Le site {$domain} est déjà autorisé."];
        }
        if (count($this->custom()) >= self::MAX_SITES) {
            return ['ok' => false, 'error' => 'Il y a déjà ' . self::MAX_SITES . ' sites autorisés : retirez-en un avant d\'en ajouter.'];
        }
        if (strlen($url) > 300) {
            return ['ok' => false, 'error' => 'Cette adresse est trop longue.'];
        }

        return ['ok' => true, 'domain' => $domain, 'template' => $hasRef ? preg_replace('/%7Bref%7D/i', '{ref}', $url) : null];
    }

    /** Un nom de site public : au moins un point, pas d'adresse IP, pas de nom réservé aux réseaux internes. */
    public static function publicName(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '' || !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/[^a-z0-9.\-]/', $host) || $host === 'localhost') {
            return false;
        }
        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return true;
    }

    /** Le nom de site appartient-il à ce domaine (le domaine lui-même ou l'un de ses sous-domaines) ? */
    public static function within(string $host, string $domain): bool
    {
        $host = strtolower($host);
        $domain = strtolower($domain);

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }
}
