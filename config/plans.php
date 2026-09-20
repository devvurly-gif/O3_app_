<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catalogue des formules O3App
|--------------------------------------------------------------------------
|
| Source de vérité UNIQUE de ce qu'un client achète. Avant ce fichier, trois
| systèmes concurrents coexistaient (Tenant::plan + hasModule(), les booléens
| *_enabled du tenant, et PackageService qui lisait un réglage dans la base du
| tenant) : aucun prix ne pouvait être rattaché à quoi que ce soit.
|
| Règle : rien d'autre ne définit une formule. Les booléens `*_enabled` de la
| table centrale `tenants` restent le point d'application (middleware
| `feature:`), mais ils sont désormais DÉRIVÉS d'ici par PlanService::applyTo().
|
| Montants en CENTIMES de dirham, hors taxes — comme toute somme stockée dans
| l'application. 39_000 = 390,00 MAD HT.
|
| Tarifs validés le 2026-09-20. Voir docs/commercial/plan-commercialisation.md
| et l'Annexe 1.C de docs/legal/contrat-services-saas.md.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Formule attribuée pendant l'essai gratuit
    |----------------------------------------------------------------------
    |
    | L'essai donne accès au périmètre Pro — donc à la caisse, qui est le
    | principal différenciateur face à un tableur. Un prospect qui n'a jamais
    | vu le POS n'a aucune raison de payer 690 plutôt que 390.
    |
    */
    'trial_plan' => 'pro',

    'trial_days' => 14,

    /*
    |----------------------------------------------------------------------
    | Délai de grâce après échéance
    |----------------------------------------------------------------------
    |
    | Passé `subscription_ends_at`, le compte devient lecture seule (statut
    | past_due) : le client consulte ses données et peut payer, mais n'écrit
    | plus. Après `grace_days` supplémentaires sans paiement, il passe en
    | suspended (accès refusé sauf authentification et page d'abonnement).
    |
    | Un chèque au Maroc met facilement une semaine à arriver : couper trop
    | vite coûte plus cher en clients perdus que les quelques jours offerts.
    |
    */
    'grace_days' => 15,

    /*
    |----------------------------------------------------------------------
    | Relances automatiques (jours restants avant échéance)
    |----------------------------------------------------------------------
    */
    'reminder_days' => [7, 3, 1],

    /*
    |----------------------------------------------------------------------
    | Correspondance anciens noms → formules actuelles
    |----------------------------------------------------------------------
    |
    | Utilisée par la migration de reprise et par PlanService::normalize().
    | Principe : on ne dégrade JAMAIS un tenant existant. En cas de doute, la
    | formule supérieure l'emporte.
    |
    */
    'legacy_aliases' => [
        'trial'      => 'pro',
        'starter'    => 'essentiel',
        'business'   => 'business',
        'enterprise' => 'business',
    ],

    /*
    |----------------------------------------------------------------------
    | Les formules
    |----------------------------------------------------------------------
    |
    | `features` — liste des capacités incluses. Deux familles :
    |
    |   • Celles qui possèdent un point d'application (un booléen
    |     `<feature>_enabled` sur le tenant, lu par le middleware `feature:`
    |     et par le frontend) : pos, ecom, variants, imei, paiement_bl,
    |     ocr_import. Elles sont listées dans PlanService::ENFORCED_FEATURES
    |     et réellement verrouillées.
    |
    |   • Celles qui sont vendues mais pas encore verrouillées techniquement
    |     (multi_warehouse, price_lists, reports, analytics). Elles figurent
    |     ici pour la page tarifs et le contrat. Les verrouiller demande de
    |     poser des middlewares sur les routes concernées — à faire
    |     délibérément, pas en même temps que la facturation, sous peine de
    |     couper l'accès à des clients existants qui s'en servent déjà.
    |
    | `limits` — quotas contractuels. `null` = illimité. Voir PlanService pour
    | l'application (users seulement à ce stade).
    |
    */
    'plans' => [

        'essentiel' => [
            'name'              => 'Essentiel',
            'tagline'           => 'Gérer ses ventes, ses achats et son stock sans tableur.',
            'price_month_cents' => 39_000,
            'price_year_cents'  => 390_000,   // 10 mois payés pour 12
            'setup_fee_cents'   => 190_000,
            'features' => [
                'ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie',
            ],
            'limits' => [
                'users'         => 3,
                'pos_terminals' => 0,
                'storage_gb'    => 5,
            ],
        ],

        'pro' => [
            'name'              => 'Pro',
            'tagline'           => 'La caisse et le multi-dépôts, pour un commerce qui tourne.',
            'price_month_cents' => 69_000,
            'price_year_cents'  => 690_000,
            'setup_fee_cents'   => 190_000,
            'features' => [
                'ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie',
                'multi_warehouse', 'price_lists', 'reports', 'pos',
            ],
            'limits' => [
                'users'         => 7,
                'pos_terminals' => 2,
                'storage_gb'    => 20,
            ],
        ],

        'business' => [
            'name'              => 'Business',
            'tagline'           => 'Boutique en ligne, déclinaisons et import automatique des factures.',
            'price_month_cents' => 129_000,
            'price_year_cents'  => 1_290_000,
            'setup_fee_cents'   => 190_000,
            'features' => [
                'ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie',
                'multi_warehouse', 'price_lists', 'reports', 'pos',
                'variants', 'imei', 'ecom', 'ocr_import', 'analytics', 'paiement_bl',
            ],
            'limits' => [
                'users'         => 15,
                'pos_terminals' => 5,
                'storage_gb'    => 50,
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Options mensuelles (facturation manuelle à ce stade)
    |----------------------------------------------------------------------
    */
    'addons' => [
        'extra_user'         => ['name' => 'Utilisateur supplémentaire',      'price_month_cents' => 6_000],
        'extra_pos_terminal' => ['name' => 'Terminal POS supplémentaire',     'price_month_cents' => 15_000],
        'extra_storage_5gb'  => ['name' => 'Stockage +5 Go',                  'price_month_cents' => 5_000],
        'ecom_on_pro'        => ['name' => 'Boutique en ligne (sur Pro)',     'price_month_cents' => 35_000],
    ],
];
