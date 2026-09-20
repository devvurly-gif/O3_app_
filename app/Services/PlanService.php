<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Traduit une formule commerciale (config/plans.php) en droits d'accès réels
 * sur un tenant.
 *
 * Remplace PackageService, qui lisait son état dans un réglage de la base DU
 * TENANT (`billing.package_type`) : une donnée commerciale à portée du client,
 * jamais écrite nulle part, donc tout le monde était « basic » à vie et
 * l'import OCR était coupé pour l'ensemble des clients sans exception.
 *
 * Le point d'application ne change pas : les booléens `<feature>_enabled` de la
 * table centrale `tenants`, lus par le middleware `feature:` et renvoyés au
 * frontend par AuthService. Ce qui change, c'est qu'ils ne se saisissent plus
 * à la main — ils se déduisent de la formule.
 */
class PlanService
{
    /**
     * Capacités réellement verrouillées, et le booléen tenant qui les porte.
     *
     * Les autres capacités listées dans config/plans.php (multi_warehouse,
     * price_lists, reports, analytics) sont vendues mais pas encore gardées
     * par un middleware : les verrouiller demande de poser des gardes sur des
     * routes que des clients existants utilisent déjà, ce qui est une décision
     * à prendre séparément.
     *
     * @var array<string, string>
     */
    public const ENFORCED_FEATURES = [
        'pos'         => 'pos_enabled',
        'ecom'        => 'ecom_enabled',
        'variants'    => 'variants_enabled',
        'imei'        => 'imei_enabled',
        'paiement_bl' => 'paiement_bl_enabled',
        'ocr_import'  => 'ocr_import_enabled',
    ];

    /**
     * Catalogue complet, pour la page tarifs et l'écran « choisir une formule ».
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $plans = [];

        foreach (array_keys((array) config('plans.plans', [])) as $key) {
            $plans[$key] = $this->get($key);
        }

        return $plans;
    }

    /**
     * Définition d'une formule, identifiant inclus.
     *
     * @return array<string, mixed>
     */
    public function get(string $plan): array
    {
        $key        = $this->normalize($plan);
        $definition = config("plans.plans.{$key}");

        if (!is_array($definition)) {
            throw new InvalidArgumentException("Formule inconnue : {$plan}");
        }

        return ['key' => $key] + $definition;
    }

    /**
     * Ramène un libellé de formule — y compris un ancien nom (starter,
     * enterprise, trial…) — vers une clé du catalogue actuel.
     *
     * Ne dégrade jamais : en cas d'alias ambigu, config('plans.legacy_aliases')
     * pointe sur la formule supérieure. Un nom totalement inconnu retombe sur
     * la formule d'essai plutôt que de lever une exception en pleine requête.
     */
    public function normalize(?string $plan): string
    {
        $plan = strtolower(trim((string) $plan));

        if ($plan !== '' && is_array(config("plans.plans.{$plan}"))) {
            return $plan;
        }

        $alias = config("plans.legacy_aliases.{$plan}");

        if (is_string($alias) && is_array(config("plans.plans.{$alias}"))) {
            return $alias;
        }

        return (string) config('plans.trial_plan', 'pro');
    }

    public function exists(string $plan): bool
    {
        return is_array(config('plans.plans.' . strtolower(trim($plan))));
    }

    /**
     * Capacités incluses dans une formule.
     *
     * @return array<int, string>
     */
    public function featuresFor(string $plan): array
    {
        return (array) ($this->get($plan)['features'] ?? []);
    }

    public function planHasFeature(string $plan, string $feature): bool
    {
        return in_array($feature, $this->featuresFor($plan), true);
    }

    /**
     * Quotas contractuels de la formule.
     *
     * @return array<string, int|null>
     */
    public function limitsFor(string $plan): array
    {
        return (array) ($this->get($plan)['limits'] ?? []);
    }

    /**
     * Capacités effectives d'un tenant : celles de sa formule, corrigées par
     * les dérogations commerciales accordées au cas par cas.
     *
     * @return array<int, string>
     */
    public function featuresForTenant(Tenant $tenant): array
    {
        $features  = $this->featuresFor((string) $tenant->plan);
        $overrides = $this->overridesOf($tenant);

        foreach ($overrides as $feature => $granted) {
            $features = array_values(array_diff($features, [$feature]));

            if ($granted) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    public function tenantHasFeature(Tenant $tenant, string $feature): bool
    {
        return in_array($feature, $this->featuresForTenant($tenant), true);
    }

    /**
     * Écrit sur le tenant les booléens qui découlent de sa formule.
     *
     * À appeler à chaque changement de formule — et à cet endroit seulement.
     * Toute écriture manuelle d'un `*_enabled` ailleurs dans le code réintroduit
     * exactement l'incohérence que ce service existe pour supprimer : passer
     * par grantFeature()/revokeFeature().
     *
     * @param string|null $plan Nouvelle formule ; null pour réappliquer l'actuelle.
     */
    public function applyTo(Tenant $tenant, ?string $plan = null): Tenant
    {
        $key = $this->normalize($plan ?? $tenant->plan);

        $tenant->plan = $key;

        $effective = $this->featuresForTenant($tenant);

        foreach (self::ENFORCED_FEATURES as $feature => $column) {
            $tenant->{$column} = in_array($feature, $effective, true);
        }

        $tenant->save();

        $this->mirrorPaiementBlSetting($tenant);

        return $tenant;
    }

    /**
     * Le règlement sur bon de livraison est le seul drapeau doublé dans la
     * base du tenant (`ventes.paiement_sur_bl`), où le module le lit. La
     * commande `tenants:sync-feature-flags` répare la dérive chaque nuit à
     * 2 h ; la recopier ici évite qu'un client qui vient de payer attende le
     * lendemain matin pour obtenir ce qu'il a acheté.
     *
     * Silencieux en cas d'échec : à la création d'un tenant, applyTo() est
     * appelé avant que la base du tenant ne soit peuplée, et l'absence de
     * table `settings` ne doit pas faire échouer l'inscription.
     */
    private function mirrorPaiementBlSetting(Tenant $tenant): void
    {
        try {
            $tenant->run(function () use ($tenant) {
                Setting::set(
                    'ventes',
                    'paiement_sur_bl',
                    $tenant->paiement_bl_enabled ? 'true' : 'false'
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Mirroring paiement_sur_bl into tenant settings failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Accorde une capacité hors formule (geste commercial), de façon tracée.
     */
    public function grantFeature(Tenant $tenant, string $feature): Tenant
    {
        return $this->setOverride($tenant, $feature, true);
    }

    /**
     * Retire une capacité pourtant incluse dans la formule.
     */
    public function revokeFeature(Tenant $tenant, string $feature): Tenant
    {
        return $this->setOverride($tenant, $feature, false);
    }

    /**
     * Supprime la dérogation : la capacité repasse sous le régime de la formule.
     */
    public function clearOverride(Tenant $tenant, string $feature): Tenant
    {
        $overrides = $this->overridesOf($tenant);
        unset($overrides[$feature]);

        $tenant->feature_overrides = $overrides;

        return $this->applyTo($tenant);
    }

    /**
     * Dérogations en cours.
     *
     * @return array<string, bool>
     */
    public function overridesOf(Tenant $tenant): array
    {
        $raw = $tenant->feature_overrides;

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_map(static fn ($v): bool => (bool) $v, $raw);
    }

    private function setOverride(Tenant $tenant, string $feature, bool $granted): Tenant
    {
        $overrides            = $this->overridesOf($tenant);
        $overrides[$feature]  = $granted;

        $tenant->feature_overrides = $overrides;

        return $this->applyTo($tenant);
    }
}
