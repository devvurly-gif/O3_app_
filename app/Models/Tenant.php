<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Services\PlanService;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

/**
 * Colonnes réelles de la table — voir getCustomColumns().
 *
 * @property string      $id
 * @property string      $name
 * @property string      $email
 * @property string      $plan                  Clé de config('plans.plans')
 * @property TenantStatus $status
 * @property bool        $is_active
 * @property \Carbon\Carbon|null $trial_ends_at        Date de fin de l'essai (historique)
 * @property \Carbon\Carbon|null $subscription_ends_at Échéance qui fait foi
 *
 * Colonnes virtuelles. Stancl range tout attribut absent de
 * getCustomColumns() dans la colonne JSON `data` et le ressort à la lecture
 * (trait VirtualColumn). Elles se manipulent exactement comme des colonnes,
 * mais aucune analyse statique ne peut les deviner : les déclarer ici est la
 * seule façon de les rendre visibles — à l'outil comme au lecteur.
 *
 * Les booléens `*_enabled` ne se saisissent plus à la main : ils sont dérivés
 * de la formule par PlanService::applyTo(). Pour un geste commercial hors
 * formule, passer par PlanService::grantFeature(), qui les recalcule.
 *
 * @property bool        $pos_enabled           Module caisse
 * @property bool        $ecom_enabled          Boutique en ligne
 * @property bool        $variants_enabled      Déclinaisons produit
 * @property bool        $imei_enabled          Suivi IMEI
 * @property bool        $paiement_bl_enabled   Règlement sur bon de livraison
 * @property bool        $ocr_import_enabled    Import OCR de factures fournisseur
 * @property array|null  $feature_overrides     Dérogations à la formule, par capacité
 * @property string|null $requested_plan        Formule demandée par le client, en attente de règlement
 * @property string|null $requested_billing_period
 * @property string|null $requested_at
 * @property string|null $requested_note
 * @property string|null $last_reminder_sent_for Échéance pour laquelle la dernière relance est partie
 * @property int|null    $last_reminder_days     Palier de relance déjà envoyé (7, 3, 1, 0)
 * @property bool        $url_ready             Domaine résolu et servi
 * @property string|null $ecom_api_key          Clé de l'API boutique
 * @property string|null $signup_phone          Téléphone saisi à l'inscription
 * @property string|null $verification_token     Jeton du lien de vérification
 * @property string|null $verification_token_expires_at
 * @property string|null $verified_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'plan',
            'status',
            'is_active',
            'trial_ends_at',
            'subscription_ends_at',
        ];
    }

    protected $casts = [
        'is_active'            => 'boolean',
        'status'               => TenantStatus::class,
        'trial_ends_at'        => 'date',
        'subscription_ends_at' => 'date',
        'url_ready'            => 'boolean',  // stored in JSON data column
    ];

    /*
    |--------------------------------------------------------------------------
    | Abonnement
    |--------------------------------------------------------------------------
    |
    | `subscription_ends_at` est l'échéance qui fait foi, quel que soit le
    | statut — essai compris. `trial_ends_at` ne sert plus qu'à savoir quand
    | l'essai s'est terminé : le faire décider de quoi que ce soit ramènerait
    | deux dates concurrentes, ce que cette phase supprime.
    |
    */

    /**
     * Statut garanti non nul. Nommé `currentStatus` et non `status` : une
     * méthode homonyme d'une colonne se fait tôt ou tard prendre pour un
     * accesseur ou une relation par Eloquent.
     */
    public function currentStatus(): TenantStatus
    {
        $status = $this->getAttribute('status');

        if ($status instanceof TenantStatus) {
            return $status;
        }

        return TenantStatus::tryFrom((string) $status) ?? TenantStatus::Trial;
    }

    public function isOnTrial(): bool
    {
        return $this->currentStatus() === TenantStatus::Trial;
    }

    /**
     * L'échéance est-elle dépassée ? Indépendant du statut : c'est ce que le
     * cron `subscriptions:check` compare pour faire basculer le statut.
     */
    public function isExpired(): bool
    {
        return $this->subscription_ends_at !== null
            && $this->subscription_ends_at->endOfDay()->isPast();
    }

    /**
     * Jours restants avant échéance. Négatif si dépassée, null si aucune
     * échéance n'est posée (tenant créé à la main avant cette phase).
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->subscription_ends_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->subscription_ends_at->startOfDay(),
            false
        );
    }

    /**
     * Le tenant peut-il écrire ? Le coupe-circuit manuel `is_active` prime
     * sur le statut commercial : c'est lui qu'un administrateur bascule en
     * cas de fraude ou à la demande du client.
     */
    public function canWrite(): bool
    {
        return $this->is_active && $this->currentStatus()->allowsWrites();
    }

    public function canRead(): bool
    {
        return $this->is_active && $this->currentStatus()->allowsReads();
    }

    /**
     * Build an absolute URL against this tenant's own domain, not the
     * central app's. Needed anywhere a link is generated outside an HTTP
     * request context (queued notifications/mailables) — Laravel's url()
     * helper falls back to config('app.url') there, which is the central
     * domain, so e.g. a "view this document" email link would send staff
     * to the central admin instead of the tenant's own app.
     */
    public function appUrl(string $path = ''): string
    {
        $domain = $this->domains()->first()?->domain;

        if (!$domain) {
            return url($path);
        }

        return 'https://' . $domain . '/' . ltrim($path, '/');
    }

    /**
     * Le tenant a-t-il droit à cette capacité ?
     *
     * Répondait auparavant à partir d'une table de correspondance interne
     * (starter/business/enterprise) qui n'était appelée nulle part, pendant
     * que le contrôle d'accès réel passait par des booléens sans aucun lien
     * avec la formule. Les deux sont désormais la même chose, définie dans
     * config/plans.php.
     */
    public function hasModule(string $module): bool
    {
        return app(PlanService::class)->tenantHasFeature($this, $module);
    }
}
