<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * Mise en page des documents imprimes, reglable par le tenant.
 *
 * Chaque tenant a sa propre base (stancl/tenancy), donc la table
 * `settings` est deja cloisonnee : il suffit d'y ranger la config.
 * Domaine `documents`, une ligne JSON par cle :
 *
 *   default             -> mise en page appliquee a tous les documents
 *   type_InvoiceSale    -> surcharge complete pour ce type
 *   type_DeliveryNote   -> ...
 *
 * La resolution est : DEFAULTS (code) <- default (tenant) <- type (tenant).
 * Un type sans ligne dediee suit le reglage general ; des qu'une ligne
 * existe, elle gagne en entier — pas de cascade partielle, pour que
 * "ce type a sa propre mise en page" reste previsible cote utilisateur.
 *
 * SECURITE : le contenu finit dans du HTML rendu par dompdf. Toute
 * valeur est donc validee ici (couleurs en hexa, enums, bornes
 * numeriques) et tout texte libre est echappe dans la vue. Sans ca une
 * "couleur" du genre `red; background:url(...)` injecterait du CSS
 * arbitraire dans le PDF.
 */
class DocumentTemplateService
{
    /** Libelle imprime par defaut, par type de document. */
    public const TYPE_LABELS = [
        'QuoteSale'            => 'Devis',
        'CustomerOrder'        => 'Bon de Commande Client',
        'DeliveryNote'         => 'Bon de Livraison',
        'InvoiceSale'          => 'Facture',
        'CreditNoteSale'       => 'Avoir Client',
        'ReturnSale'           => 'Bon de Retour Client',
        'PurchaseOrder'        => 'Bon de Commande',
        'ReceiptNotePurchase'  => 'Bon de Réception',
        'InvoicePurchase'      => 'Facture Achat',
        'CreditNotePurchase'   => 'Avoir Fournisseur',
        'ReturnPurchase'       => 'Bon de Retour Fournisseur',
        'StockEntry'           => 'Bon Entrée en Stock',
        'StockExit'            => 'Bon de Sortie de Stock',
        'StockTransfer'        => 'Bon de Transfert',
        'StockAdjustmentNote'  => "Bon d'Ajustement",
    ];

    public const DOMAIN      = 'documents';
    public const DEFAULT_KEY = 'default';

    public const FONTS          = ['dejavu', 'helvetica', 'times', 'courier'];
    public const PAPERS         = ['a4', 'letter'];
    public const ORIENTATIONS   = ['portrait', 'landscape'];
    public const TABLE_STYLES   = ['filled', 'bordered', 'minimal'];
    public const LOGO_POSITIONS = ['left', 'right', 'hidden'];

    /** Colonnes du tableau de lignes, dans l'ordre d'impression. */
    public const COLUMNS = ['index', 'reference', 'quantity', 'unit_price', 'discount', 'tax', 'total'];

    /**
     * Mise en page livree avec l'application : c'est exactement le rendu
     * historique, pour qu'un tenant qui ne touche a rien ne voie aucun
     * changement.
     */
    public const DEFAULTS = [
        // Identite visuelle
        'accent_color'          => '#1e3a5f',
        'text_color'            => '#1a1a1a',
        'font_family'           => 'dejavu',
        'font_size'             => 10,
        'paper_size'            => 'a4',
        'orientation'           => 'portrait',
        'margin_x'              => 40,
        'margin_y'              => 30,

        // En-tete
        'logo_position'         => 'left',
        'logo_height'           => 60,
        'show_company_block'    => true,
        'title_override'        => '',
        'header_note'           => '',

        // Blocs
        'show_partner_block'    => true,
        'show_status'           => true,
        'show_warehouse'        => true,
        'show_user'             => true,
        'show_totals'           => true,
        'show_total_in_words'   => true,
        'show_payments'         => true,
        'show_notes'            => true,
        'show_bank_details'     => true,
        'show_legal_mentions'   => true,

        // Tableau des lignes
        'table_style'           => 'filled',
        'zebra_rows'            => true,
        'columns'               => [
            'index'      => true,
            'reference'  => true,
            'quantity'   => true,
            'unit_price' => true,
            'discount'   => true,
            'tax'        => true,
            'total'      => true,
        ],

        // Signature
        'show_signature'        => false,
        'signature_left_label'  => 'Le client',
        'signature_right_label' => 'Pour la société',

        // Filigrane
        'watermark_text'        => '',
        'watermark_opacity'     => 8,   // en pourcents

        // Bas de page
        'terms'                 => '',
        'footer_note'           => '',
        'currency'              => 'MAD',
    ];

    /** Types de documents personnalisables. */
    public static function types(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    /** Cle de stockage d'un type ('default' reste tel quel). */
    public static function settingKey(string $type): string
    {
        return $type === self::DEFAULT_KEY ? self::DEFAULT_KEY : 'type_' . $type;
    }

    /**
     * Mise en page effective d'un type de document.
     * Le libelle imprime est resolu ici pour que la vue n'ait qu'a
     * afficher `$tpl['title']`.
     */
    public function resolve(string $type): array
    {
        $general = $this->stored(self::DEFAULT_KEY);
        $own     = $this->stored($type);

        $config = array_replace(
            self::DEFAULTS,
            ['currency' => self::fallbackCurrency()],
            $general,
            $own,
        );

        // `columns` est un sous-tableau : array_replace ne fusionne pas en
        // profondeur, donc on le recompose pour qu'une config enregistree
        // avant l'ajout d'une colonne ne la fasse pas disparaitre.
        $config['columns'] = array_replace(
            self::DEFAULTS['columns'],
            is_array($general['columns'] ?? null) ? $general['columns'] : [],
            is_array($own['columns'] ?? null) ? $own['columns'] : [],
        );

        $config = self::harden($config);

        // Le titre imprime ne se herite pas du reglage general : sinon un
        // tenant qui saisit "FACTURE" cote general verrait tous ses bons de
        // livraison s'appeler Facture. Seule la surcharge du type compte.
        $ownTitle = is_string($own['title_override'] ?? null) ? trim($own['title_override']) : '';

        $config['title_override'] = $ownTitle;
        $config['title']          = $ownTitle !== ''
            ? $ownTitle
            : (self::TYPE_LABELS[$type] ?? $type);

        return $config;
    }

    /**
     * Ramene chaque valeur dans son domaine autorise avant le rendu.
     *
     * L'API valide deja les entrees, mais la vue construit du CSS a
     * partir de ces valeurs : une ligne `settings` modifiee a la main
     * (import, correctif SQL, ancienne version) ne doit pas pouvoir
     * injecter du CSS dans le PDF. Toute valeur hors domaine retombe
     * sur le defaut.
     */
    public static function harden(array $config): array
    {
        $hex = static fn ($value, string $fallback) => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ? $value
            : $fallback;

        $enum = static fn ($value, array $allowed, string $fallback) => is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $fallback;

        $int = static fn ($value, int $min, int $max, int $fallback) => is_numeric($value)
            ? max($min, min($max, (int) $value))
            : $fallback;

        $config['accent_color']      = $hex($config['accent_color'] ?? null, self::DEFAULTS['accent_color']);
        $config['text_color']        = $hex($config['text_color'] ?? null, self::DEFAULTS['text_color']);
        $config['font_family']       = $enum($config['font_family'] ?? null, self::FONTS, self::DEFAULTS['font_family']);
        $config['paper_size']        = $enum($config['paper_size'] ?? null, self::PAPERS, self::DEFAULTS['paper_size']);
        $config['orientation']       = $enum($config['orientation'] ?? null, self::ORIENTATIONS, self::DEFAULTS['orientation']);
        $config['table_style']       = $enum($config['table_style'] ?? null, self::TABLE_STYLES, self::DEFAULTS['table_style']);
        $config['logo_position']     = $enum($config['logo_position'] ?? null, self::LOGO_POSITIONS, self::DEFAULTS['logo_position']);
        $config['font_size']         = $int($config['font_size'] ?? null, 7, 16, self::DEFAULTS['font_size']);
        $config['logo_height']       = $int($config['logo_height'] ?? null, 20, 160, self::DEFAULTS['logo_height']);
        $config['margin_x']          = $int($config['margin_x'] ?? null, 10, 80, self::DEFAULTS['margin_x']);
        $config['margin_y']          = $int($config['margin_y'] ?? null, 10, 80, self::DEFAULTS['margin_y']);
        $config['watermark_opacity'] = $int($config['watermark_opacity'] ?? null, 1, 40, self::DEFAULTS['watermark_opacity']);

        foreach (self::booleanKeys() as $key) {
            $config[$key] = filter_var($config[$key] ?? self::DEFAULTS[$key], FILTER_VALIDATE_BOOLEAN);
        }

        foreach (self::COLUMNS as $column) {
            $config['columns'][$column] = filter_var($config['columns'][$column] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['title_override', 'header_note', 'signature_left_label', 'signature_right_label', 'watermark_text', 'terms', 'footer_note', 'currency'] as $key) {
            $config[$key] = is_scalar($config[$key] ?? null) ? (string) $config[$key] : '';
        }

        if (trim($config['currency']) === '') {
            $config['currency'] = self::fallbackCurrency();
        }

        return $config;
    }

    /** Config brute enregistree pour une cle, [] si le tenant n'a rien regle. */
    public function stored(string $type): array
    {
        $raw = Setting::get(self::DOMAIN, self::settingKey($type));

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Tout ce que l'ecran de reglages doit afficher. */
    public function all(): array
    {
        $configs = [self::DEFAULT_KEY => $this->stored(self::DEFAULT_KEY)];

        foreach (self::types() as $type) {
            $configs[$type] = $this->stored($type);
        }

        return $configs;
    }

    public function save(string $type, array $config): void
    {
        Setting::set(self::DOMAIN, self::settingKey($type), json_encode($config, JSON_UNESCAPED_UNICODE));
    }

    /** Supprime la surcharge : le type repasse au reglage general. */
    public function reset(string $type): void
    {
        Setting::where('st_domain', self::DOMAIN)
            ->where('st_key', self::settingKey($type))
            ->delete();
    }

    /**
     * Regles de validation de la config. Rien n'entre en base sans
     * passer par la — cf. la note de securite en tete de classe.
     */
    public static function rules(string $prefix = ''): array
    {
        $p = $prefix === '' ? '' : $prefix . '.';

        $rules = [
            $p . 'accent_color'          => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            $p . 'text_color'            => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            $p . 'font_family'           => ['required', Rule::in(self::FONTS)],
            $p . 'font_size'             => ['required', 'integer', 'between:7,16'],
            $p . 'paper_size'            => ['required', Rule::in(self::PAPERS)],
            $p . 'orientation'           => ['required', Rule::in(self::ORIENTATIONS)],
            $p . 'margin_x'              => ['required', 'integer', 'between:10,80'],
            $p . 'margin_y'              => ['required', 'integer', 'between:10,80'],
            $p . 'logo_position'         => ['required', Rule::in(self::LOGO_POSITIONS)],
            $p . 'logo_height'           => ['required', 'integer', 'between:20,160'],
            $p . 'title_override'        => ['present', 'nullable', 'string', 'max:60'],
            $p . 'header_note'           => ['present', 'nullable', 'string', 'max:500'],
            $p . 'table_style'           => ['required', Rule::in(self::TABLE_STYLES)],
            $p . 'signature_left_label'  => ['present', 'nullable', 'string', 'max:60'],
            $p . 'signature_right_label' => ['present', 'nullable', 'string', 'max:60'],
            $p . 'watermark_text'        => ['present', 'nullable', 'string', 'max:40'],
            $p . 'watermark_opacity'     => ['required', 'integer', 'between:1,40'],
            $p . 'terms'                 => ['present', 'nullable', 'string', 'max:1000'],
            $p . 'footer_note'           => ['present', 'nullable', 'string', 'max:300'],
            $p . 'currency'              => ['required', 'string', 'max:8'],
            $p . 'columns'               => ['required', 'array'],
        ];

        foreach (self::booleanKeys() as $key) {
            $rules[$p . $key] = ['required', 'boolean'];
        }

        foreach (self::COLUMNS as $column) {
            $rules[$p . 'columns.' . $column] = ['required', 'boolean'];
        }

        return $rules;
    }

    /** Cles booleennes de la config (deduites des defauts). */
    public static function booleanKeys(): array
    {
        return array_keys(array_filter(
            self::DEFAULTS,
            fn ($value) => is_bool($value),
        ));
    }

    /**
     * Normalise la charge validee : seules les cles connues sont
     * conservees, et les booleens arrivent typees (une case cochee
     * transitant en "1" doit etre stockee en true).
     */
    public static function sanitize(array $input): array
    {
        $booleans = self::booleanKeys();
        $config   = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            if ($key === 'columns') {
                continue;
            }

            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            $config[$key] = match (true) {
                in_array($key, $booleans, true)         => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                is_int(self::DEFAULTS[$key])            => (int) $value,
                default                                 => (string) ($value ?? ''),
            };
        }

        foreach (self::COLUMNS as $column) {
            $config['columns'][$column] = filter_var($input['columns'][$column] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        return $config;
    }

    /**
     * Devise par defaut : celle deja reglee dans Parametres > Localisation,
     * sinon MAD. Evite de faire saisir deux fois la meme information.
     */
    private static function fallbackCurrency(): string
    {
        $symbol = Setting::get('locale', 'currency_symbol')
            ?: Setting::get('locale', 'currency');

        return is_string($symbol) && trim($symbol) !== '' ? trim($symbol) : 'MAD';
    }
}
