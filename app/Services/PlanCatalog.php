<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlanChange;
use App\Models\PlanOverride;
use App\Models\Tenant;
use App\Models\User;

/**
 * Catalogue des formules modifiable depuis la gestion des tenants.
 *
 * `config/plans.php` reste la valeur par DÉFAUT (livrée avec le code). Ce que le super-administrateur modifie est rangé
 * dans `plan_overrides` et fusionné dans `config('plans')` au démarrage de l'application : tout le reste (factures,
 * droits des clients, page d'abonnement, validations) continue de lire `config('plans.…')` sans rien savoir des
 * modifications. Un champ jamais modifié garde sa valeur du code ; « réinitialiser » supprime la modification.
 *
 * Ce qu'on peut changer : nom, slogan, prix (mensuel, annuel, installation), quotas, capacités incluses, droit aux agents IA ;
 * et le nom / prix mensuel des options. Les six capacités de base (ventes, achats, stock, tiers, documents, trésorerie)
 * restent toujours incluses. Les factures déjà émises portent leur propre copie de la formule : elles ne bougent jamais.
 * Les capacités incluses se répercutent sur les droits des clients à la synchronisation nocturne (tenants:sync-feature-flags).
 */
class PlanCatalog
{
    /** Capacités proposées dans l'éditeur, avec leur libellé. @var array<string, string> */
    public const CAPABILITIES = [
        'ventes' => 'Ventes', 'achats' => 'Achats', 'stock' => 'Stock', 'tiers' => 'Clients et fournisseurs', 'documents' => 'Documents', 'tresorerie' => 'Trésorerie',
        'multi_warehouse' => 'Plusieurs entrepôts', 'price_lists' => 'Listes de prix', 'reports' => 'Rapports', 'pos' => 'Caisse (POS)',
        'variants' => 'Déclinaisons produit', 'imei' => 'Suivi IMEI', 'ecom' => 'Boutique en ligne', 'ocr_import' => 'Import OCR des factures',
        'analytics' => 'Analytique', 'paiement_bl' => 'Règlement sur bon de livraison',
    ];

    /** Toujours incluses : une formule sans elles n'aurait pas de sens. @var array<int, string> */
    public const CORE = ['ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie'];

    /** Champs modifiables d'une formule. @var array<int, string> */
    public const PLAN_FIELDS = ['name', 'tagline', 'price_month_cents', 'price_year_cents', 'setup_fee_cents', 'features', 'limits', 'agents'];

    /** Champs modifiables d'une option. @var array<int, string> */
    public const ADDON_FIELDS = ['name', 'price_month_cents'];

    /** @var array<string, mixed>|null valeurs du code, avant toute fusion */
    private static ?array $defaults = null;

    private static bool $applied = false;

    // ── Fusion au démarrage ──────────────────────────────────────────

    /** Fusionne les modifications dans `config('plans')`. Sans effet (et sans erreur) si la table n'existe pas encore. */
    public static function apply(bool $force = false): void
    {
        if (self::$applied && !$force) {
            return;
        }
        self::$applied = true;
        $defaults = self::defaults();
        config(['plans.plans' => $defaults['plans'], 'plans.addons' => $defaults['addons'], 'plans.agents_plans' => $defaults['agents_plans']]);

        try {
            $overrides = PlanOverride::query()->get(['kind', 'item_key', 'data']);
        } catch (\Throwable) {
            return;                                                                       // base injoignable ou migration pas encore passée : le code fait foi
        }

        $plans = $defaults['plans'];
        $addons = $defaults['addons'];
        foreach ($overrides as $o) {
            $data = (array) $o->data;
            if ($o->kind === 'plan' && isset($plans[$o->item_key])) {
                $plans[$o->item_key] = self::mergePlan($plans[$o->item_key], $data);
            } elseif ($o->kind === 'addon' && isset($addons[$o->item_key])) {
                $addons[$o->item_key] = array_merge($addons[$o->item_key], array_intersect_key($data, array_flip(self::ADDON_FIELDS)));
            }
        }

        // Le droit aux agents IA suit la formule : celles dont « agents » est vrai (par défaut : la liste du code).
        $agents = array_values(array_filter(array_keys($plans), function (string $key) use ($plans, $defaults) {
            return array_key_exists('agents', $plans[$key]) ? (bool) $plans[$key]['agents'] : in_array($key, $defaults['agents_plans'], true);
        }));

        config(['plans.plans' => $plans, 'plans.addons' => $addons, 'plans.agents_plans' => $agents]);
    }

    /** Remet le catalogue à ses valeurs du code puis le refusionne (après une modification). */
    public static function refresh(): void
    {
        self::apply(true);
    }

    /** @return array{plans: array<string, array<string, mixed>>, addons: array<string, array<string, mixed>>, agents_plans: array<int, string>} */
    public static function defaults(): array
    {
        if (self::$defaults === null) {
            $file = (array) require config_path('plans.php');
            self::$defaults = [
                'plans'        => (array) ($file['plans'] ?? []),
                'addons'       => (array) ($file['addons'] ?? []),
                'agents_plans' => array_values((array) ($file['agents_plans'] ?? ['pro', 'business'])),
            ];
        }

        return self::$defaults;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function mergePlan(array $plan, array $data): array
    {
        foreach (['name', 'tagline', 'price_month_cents', 'price_year_cents', 'setup_fee_cents'] as $field) {
            if (array_key_exists($field, $data)) {
                $plan[$field] = $data[$field];
            }
        }
        if (isset($data['features']) && is_array($data['features'])) {
            $plan['features'] = array_values(array_unique(array_merge(self::CORE, array_intersect($data['features'], array_keys(self::CAPABILITIES)))));
        }
        if (isset($data['limits']) && is_array($data['limits'])) {
            $plan['limits'] = array_merge((array) ($plan['limits'] ?? []), array_intersect_key($data['limits'], array_flip(['users', 'pos_terminals', 'storage_gb'])));
        }
        if (array_key_exists('agents', $data)) {
            $plan['agents'] = (bool) $data['agents'];
        }

        return $plan;
    }

    // ── Lecture pour l'éditeur ───────────────────────────────────────

    /**
     * Les formules et options telles qu'elles s'appliquent, avec leur valeur du code, ce qui a été modifié, le nombre de
     * clients concernés et la dernière modification.
     *
     * @return array<string, mixed>
     */
    public function view(): array
    {
        $defaults = self::defaults();
        $overrides = PlanOverride::query()->get()->keyBy(fn ($o) => "{$o->kind}:{$o->item_key}");
        $counts = Tenant::query()->selectRaw('plan, COUNT(*) AS n')->groupBy('plan')->pluck('n', 'plan');

        $plans = [];
        foreach ((array) config('plans.plans', []) as $key => $plan) {
            $override = $overrides->get("plan:{$key}");
            $plans[] = [
                'key'            => $key,
                'effective'      => $this->editable($key, $plan, true),
                'defaults'       => $this->editable($key, $defaults['plans'][$key] ?? $plan, false),
                'overridden'     => $override !== null,
                'tenants_count'  => (int) ($counts[$key] ?? 0),
                'updated_at'     => $override?->updated_at?->toIso8601String(),
            ];
        }
        $addons = [];
        foreach ((array) config('plans.addons', []) as $key => $addon) {
            $override = $overrides->get("addon:{$key}");
            $addons[] = [
                'key'        => $key,
                'effective'  => array_intersect_key($addon, array_flip(self::ADDON_FIELDS)),
                'defaults'   => array_intersect_key($defaults['addons'][$key] ?? $addon, array_flip(self::ADDON_FIELDS)),
                'overridden' => $override !== null,
            ];
        }

        return ['plans' => $plans, 'addons' => $addons, 'capabilities' => self::CAPABILITIES, 'core' => self::CORE];
    }

    /**
     * Les champs éditables d'une formule sous leur forme normalisée.
     *
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function editable(string $key, array $plan, bool $effective): array
    {
        $defaults = self::defaults();
        $agents = array_key_exists('agents', $plan) ? (bool) $plan['agents'] : in_array($key, $defaults['agents_plans'], true);

        return [
            'name'              => (string) ($plan['name'] ?? $key),
            'tagline'           => (string) ($plan['tagline'] ?? ''),
            'price_month_cents' => (int) ($plan['price_month_cents'] ?? 0),
            'price_year_cents'  => (int) ($plan['price_year_cents'] ?? 0),
            'setup_fee_cents'   => (int) ($plan['setup_fee_cents'] ?? 0),
            'features'          => array_values((array) ($plan['features'] ?? [])),
            'limits'            => [
                'users'         => $plan['limits']['users'] ?? null,
                'pos_terminals' => $plan['limits']['pos_terminals'] ?? null,
                'storage_gb'    => $plan['limits']['storage_gb'] ?? null,
            ],
            'agents'            => $agents,
        ];
    }

    // ── Écriture ─────────────────────────────────────────────────────

    /**
     * Enregistre les modifications d'une formule (déjà validées) et les trace.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> la formule telle qu'elle s'applique ensuite
     */
    public function updatePlan(string $key, array $input, ?User $by): array
    {
        $before = $this->editable($key, (array) config("plans.plans.{$key}"), true);
        $data = array_intersect_key($input, array_flip(self::PLAN_FIELDS));
        $data['features'] = array_values(array_unique(array_merge(self::CORE, array_intersect((array) ($data['features'] ?? []), array_keys(self::CAPABILITIES)))));

        PlanOverride::updateOrCreate(['kind' => 'plan', 'item_key' => $key], ['data' => $data, 'updated_by' => $by?->id]);
        self::refresh();

        return $this->logged('plan', $key, 'update', $before, $by);
    }

    /** Supprime les modifications d'une formule : elle retrouve ses valeurs du code. @return array<string, mixed> */
    public function resetPlan(string $key, ?User $by): array
    {
        $before = $this->editable($key, (array) config("plans.plans.{$key}"), true);
        PlanOverride::where('kind', 'plan')->where('item_key', $key)->delete();
        self::refresh();

        return $this->logged('plan', $key, 'reset', $before, $by);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateAddon(string $key, array $input, ?User $by): array
    {
        $before = array_intersect_key((array) config("plans.addons.{$key}"), array_flip(self::ADDON_FIELDS));
        PlanOverride::updateOrCreate(['kind' => 'addon', 'item_key' => $key], ['data' => array_intersect_key($input, array_flip(self::ADDON_FIELDS)), 'updated_by' => $by?->id]);
        self::refresh();

        $after = array_intersect_key((array) config("plans.addons.{$key}"), array_flip(self::ADDON_FIELDS));
        $this->record('addon', $key, 'update', $before, $after, $by, 0);

        return $after;
    }

    /**
     * @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function logged(string $kind, string $key, string $action, array $before, ?User $by): array
    {
        $after = $this->editable($key, (array) config("plans.plans.{$key}"), true);
        $this->record($kind, $key, $action, $before, $after, $by, Tenant::where('plan', $key)->count());

        return $after;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function record(string $kind, string $key, string $action, array $before, array $after, ?User $by, int $tenants): void
    {
        PlanChange::create(['kind' => $kind, 'item_key' => $key, 'action' => $action, 'user_id' => $by?->id, 'user_name' => $by?->name, 'before' => $before, 'after' => $after, 'tenants_concerned' => $tenants]);
    }

    /** @return array<int, array<string, mixed>> les dernières modifications, les plus récentes d'abord */
    public function changes(int $limit = 50): array
    {
        return PlanChange::query()->latest('id')->limit($limit)->get()->map(fn (PlanChange $c) => [
            'id' => $c->id, 'kind' => $c->kind, 'key' => $c->item_key, 'action' => $c->action, 'by' => $c->user_name, 'tenants_concerned' => $c->tenants_concerned,
            'at' => $c->created_at?->toIso8601String(), 'before' => $c->before, 'after' => $c->after,
        ])->all();
    }
}
