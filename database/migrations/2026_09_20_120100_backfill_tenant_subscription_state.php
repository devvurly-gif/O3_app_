<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprise des tenants existants — À LIRE AVANT DE DÉPLOYER.
 *
 * Le middleware EnsureTenantActive introduit avec cette phase refuse les
 * écritures d'un tenant dont l'abonnement est échu. Les tenants déjà en
 * production (demo, jadema, teliphoni) n'ont ni statut ni échéance : sans cette
 * reprise, ils basculeraient en lecture seule à la seconde où le middleware
 * arrive en production, c'est-à-dire que le déploiement couperait les clients
 * actuels.
 *
 * Cette migration leur pose donc un abonnement actif d'un an, à ajuster
 * ensuite client par client depuis le back-office central.
 *
 * Elle fige aussi leurs droits actuels sous forme de dérogations explicites
 * (`feature_overrides`) : les modules activés à la main jusqu'ici ne
 * correspondent à aucune formule, et sans cette photographie un futur
 * changement de formule les recalculerait — en retirant potentiellement un
 * module dont le client se sert tous les jours.
 */
return new class extends Migration
{
    /** Booléens de capacité portés par la colonne JSON `data`. */
    private const FEATURE_COLUMNS = [
        'pos'         => 'pos_enabled',
        'ecom'        => 'ecom_enabled',
        'variants'    => 'variants_enabled',
        'imei'        => 'imei_enabled',
        'paiement_bl' => 'paiement_bl_enabled',
        'ocr_import'  => 'ocr_import_enabled',
    ];

    public function up(): void
    {
        $aliases = (array) config('plans.legacy_aliases', [
            'trial'      => 'pro',
            'starter'    => 'essentiel',
            'business'   => 'business',
            'enterprise' => 'business',
        ]);

        $catalogue = (array) config('plans.plans', []);
        $endsAt    = now()->addYear()->toDateString();

        foreach (DB::table('tenants')->get() as $row) {
            $plan = strtolower(trim((string) $row->plan));

            // Un nom déjà valide est conservé tel quel ; sinon on suit la table
            // d'alias, qui ne dégrade jamais.
            $plan = isset($catalogue[$plan])
                ? $plan
                : (string) ($aliases[$plan] ?? 'essentiel');

            $data = json_decode((string) ($row->data ?? '{}'), true);
            $data = is_array($data) ? $data : [];

            // Photographie des droits réels d'aujourd'hui.
            $planFeatures = (array) ($catalogue[$plan]['features'] ?? []);
            $overrides    = [];

            foreach (self::FEATURE_COLUMNS as $feature => $column) {
                $granted    = (bool) ($data[$column] ?? false);
                $inPlan     = in_array($feature, $planFeatures, true);

                if ($granted !== $inPlan) {
                    $overrides[$feature] = $granted;
                }
            }

            if ($overrides !== []) {
                $data['feature_overrides'] = $overrides;
            }

            DB::table('tenants')
                ->where('id', $row->id)
                ->update([
                    'plan'                 => $plan,
                    'status'               => 'active',
                    'subscription_ends_at' => $endsAt,
                    'data'                 => json_encode($data),
                ]);
        }
    }

    /**
     * Les colonnes disparaissent avec la migration de schéma ; il ne reste
     * qu'à retirer la photographie des dérogations.
     */
    public function down(): void
    {
        foreach (DB::table('tenants')->get() as $row) {
            $data = json_decode((string) ($row->data ?? '{}'), true);

            if (!is_array($data) || !array_key_exists('feature_overrides', $data)) {
                continue;
            }

            unset($data['feature_overrides']);

            DB::table('tenants')
                ->where('id', $row->id)
                ->update(['data' => json_encode($data)]);
        }
    }
};
