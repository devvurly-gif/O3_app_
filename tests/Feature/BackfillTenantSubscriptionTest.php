<?php

namespace Tests\Feature;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * La migration de reprise des tenants existants.
 *
 * C'est le point le plus dangereux de cette phase : le jour ou le middleware
 * `tenant.active` arrive en production, les tenants deja en service (demo,
 * jadema, teliphoni) n'ont ni statut ni echeance. Sans reprise correcte, le
 * deploiement couperait les clients actuels — et leurs modules actives a la
 * main, qui ne correspondent a aucune formule, seraient recalcules au premier
 * changement d'offre.
 */
class BackfillTenantSubscriptionTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Rejoue la migration de reprise sur les lignes presentes.
     *
     * `migrate:fresh` l'a deja executee sur une table vide ; on l'appelle donc
     * a nouveau, apres avoir insere des tenants a l'ancienne.
     */
    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_09_20_120100_backfill_tenant_subscription_state.php'
        );

        $migration->up();
    }

    /**
     * Insere un tenant tel qu'il existait avant cette phase : un nom de
     * formule de l'ancienne nomenclature, aucun statut, aucune echeance, et
     * des drapeaux de capacite poses a la main.
     */
    private function legacyTenant(string $id, string $plan, array $data = []): void
    {
        DB::table('tenants')->insert([
            'id'         => $id,
            'name'       => ucfirst($id),
            'email'      => "{$id}@exemple.ma",
            'plan'       => $plan,
            'is_active'  => true,
            'data'       => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_existing_tenants_keep_working_after_the_gate_ships(): void
    {
        $this->legacyTenant('jadema', 'business');

        $this->runBackfill();

        $tenant = Tenant::find('jadema');

        $this->assertSame(TenantStatus::Active, $tenant->currentStatus());
        $this->assertTrue($tenant->canWrite());
        $this->assertTrue($tenant->subscription_ends_at->isFuture());
    }

    public function test_legacy_plan_names_are_mapped_without_downgrading(): void
    {
        $this->legacyTenant('ancien-starter', 'starter');
        $this->legacyTenant('ancien-enterprise', 'enterprise');

        $this->runBackfill();

        $this->assertSame('essentiel', Tenant::find('ancien-starter')->plan);
        $this->assertSame('business', Tenant::find('ancien-enterprise')->plan);
    }

    public function test_a_module_enabled_by_hand_survives_a_later_plan_change(): void
    {
        // Cas reel : un client « starter » a qui la caisse a ete ouverte
        // manuellement. La formule Essentiel ne contient pas le POS — sans
        // derogation enregistree, le premier applyTo() le lui retirerait.
        $this->legacyTenant('teliphoni', 'starter', ['pos_enabled' => true]);

        $this->runBackfill();

        $tenant = Tenant::find('teliphoni');

        $this->assertSame('essentiel', $tenant->plan);
        $this->assertTrue($tenant->pos_enabled, 'La caisse doit rester ouverte apres la reprise.');

        // Et elle doit y survivre quand la formule est reappliquee.
        app(PlanService::class)->applyTo($tenant);

        $this->assertTrue($tenant->fresh()->pos_enabled);
    }

    public function test_no_override_is_recorded_when_the_flags_already_match_the_plan(): void
    {
        // Un tenant dont les drapeaux correspondent deja a sa formule ne doit
        // pas se retrouver avec des derogations : elles figeraient des droits
        // qu'un changement d'offre devrait pouvoir retirer.
        $this->legacyTenant('conforme', 'starter', ['pos_enabled' => false]);

        $this->runBackfill();

        $this->assertSame([], app(PlanService::class)->overridesOf(Tenant::find('conforme')));
    }
}
