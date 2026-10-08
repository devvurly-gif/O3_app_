<?php

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\Agent;
use App\Models\Tenant;
use App\Services\Agents\AgentsProvisioning;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Allumer « Agents IA » depuis la gestion des tenants : réservé aux formules Pro et Business payées, installe le socle des
 * agents (sept agents, sans doublon) ; éteindre ne supprime rien ; la reprise garde allumés les clients qui s'en servent déjà.
 */
class AgentsProvisioningTest extends TestCase
{
    use RefreshTenantDatabase, InteractsWithTenancy;

    private function tenant(array $attributes = []): Tenant
    {
        return array_values($this->tenantsOnTheTestDatabase(['client-' . uniqid() => $attributes]))[0];
    }

    private function service(): AgentsProvisioning
    {
        return app(AgentsProvisioning::class);
    }

    public function test_only_pro_and_business_with_a_paid_subscription_are_eligible(): void
    {
        $this->assertSame(['pro', 'business'], AgentsProvisioning::eligiblePlans());

        $this->assertNull($this->service()->unavailableReason($this->tenant(['plan' => 'pro', 'status' => TenantStatus::Active])));
        $this->assertNull($this->service()->unavailableReason($this->tenant(['plan' => 'business', 'status' => TenantStatus::Active])));

        $this->assertStringContainsString('réservés aux formules Pro et Business (formule actuelle : essentiel)', (string) $this->service()->unavailableReason($this->tenant(['plan' => 'essentiel'])));
        $this->assertStringContainsString("après le paiement de la formule (statut actuel : Essai gratuit)", (string) $this->service()->unavailableReason($this->tenant(['plan' => 'pro', 'status' => TenantStatus::Trial])));
        $this->assertStringContainsString('Échéance dépassée', (string) $this->service()->unavailableReason($this->tenant(['plan' => 'business', 'status' => TenantStatus::PastDue])));
        $this->assertStringContainsString('Suspendu', (string) $this->service()->unavailableReason($this->tenant(['plan' => 'business', 'status' => TenantStatus::Suspended])));

        $essentiel = $this->tenant(['plan' => 'essentiel']);
        $this->assertNull($this->service()->unavailableReason($essentiel, 'business'));            // la formule envisagée dans la même demande compte
    }

    public function test_turning_the_option_on_installs_the_seven_agents_once_and_keeps_them_when_turned_off(): void
    {
        $tenant = $this->tenant(['plan' => 'business', 'status' => TenantStatus::Active]);
        $this->assertSame(0, Agent::count());
        $this->assertFalse($tenant->agentsUsable());

        $first = $this->service()->enable($tenant);
        $this->assertSame(['installed' => 7, 'total' => 7], $first);
        $this->assertSame(['achats', 'stocks', 'ventes', 'expedition'], Agent::where('is_active', true)->orderBy('id')->pluck('domain')->sort()->values()->sortBy(fn ($d) => array_search($d, ['achats', 'stocks', 'ventes', 'expedition']))->values()->all());
        $this->assertTrue(Tenant::find($tenant->id)->agentsUsable());

        $this->assertSame(['installed' => 0, 'total' => 7], $this->service()->enable($tenant));      // relancer ne crée aucun doublon
        $this->assertSame(7, Agent::count());

        Agent::where('domain', 'recouvrement')->update(['is_active' => true]);                       // un réglage fait depuis reste
        $this->service()->disable($tenant);
        $fresh = Tenant::find($tenant->id);
        $this->assertFalse($fresh->agentsEnabled());
        $this->assertFalse($fresh->agentsUsable());
        $this->assertSame(7, Agent::count());                                                         // rien n'est supprimé
        $this->service()->enable($tenant);
        $this->assertTrue(Agent::where('domain', 'recouvrement')->value('is_active'));                // et le réglage est retrouvé tel quel
    }

    public function test_it_is_refused_with_the_reason_and_installs_nothing_without_the_right_plan_or_payment(): void
    {
        foreach ([['plan' => 'essentiel', 'status' => TenantStatus::Active], ['plan' => 'pro', 'status' => TenantStatus::Trial]] as $attributes) {
            $tenant = $this->tenant($attributes);

            try {
                $this->service()->enable($tenant);
                $this->fail('Une activation aurait dû être refusée.');
            } catch (\DomainException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
            $this->assertFalse(Tenant::find($tenant->id)->agentsEnabled());
            $this->assertSame(0, Agent::count());
        }
    }

    public function test_the_migration_keeps_the_option_on_only_for_tenants_that_already_use_the_agents(): void
    {
        $migration = require base_path('database/migrations/2026_10_08_000001_keep_agents_on_for_tenants_already_using_them.php');

        $without = $this->tenant(['plan' => 'business', 'status' => TenantStatus::Active]);
        $migration->up();
        $this->assertFalse(Tenant::find($without->id)->agentsEnabled());                              // pas d'agents : reste éteint, procédure normale

        Agent::create(['domain' => 'achats', 'name' => 'Achats', 'is_active' => true]);
        $migration->up();
        $this->assertTrue(Tenant::find($without->id)->agentsEnabled());                               // des agents existent : on ne coupe pas le client
        $this->assertSame(1, Agent::count());                                                         // rien n'est installé ni supprimé
    }
    public function test_the_tenant_list_tells_the_screen_who_has_the_agents_and_who_may(): void
    {
        $this->tenantsOnTheTestDatabase([
            'paye-allume'  => ['plan' => 'business', 'status' => TenantStatus::Active, 'agents_enabled' => true],
            'paye-eteint'  => ['plan' => 'pro', 'status' => TenantStatus::Active],
            'essentiel'    => ['plan' => 'essentiel', 'status' => TenantStatus::Active],
            'essai'        => ['plan' => 'pro', 'status' => TenantStatus::Trial],
        ]);
        $admin = \App\Models\User::factory()->admin()->create();

        $rows = collect($this->actingAs($admin, 'sanctum')->getJson('/api/central/tenants')->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($rows['paye-allume']['agents_enabled']);
        $this->assertTrue($rows['paye-allume']['agents_available']);
        $this->assertFalse($rows['paye-eteint']['agents_enabled']);
        $this->assertTrue($rows['paye-eteint']['agents_available']);                                   // éteint mais éligible : le bouton « allumer » est proposé
        $this->assertNull($rows['paye-eteint']['agents_unavailable_reason']);
        $this->assertFalse($rows['essentiel']['agents_available']);
        $this->assertStringContainsString('réservés aux formules Pro et Business', $rows['essentiel']['agents_unavailable_reason']);
        $this->assertFalse($rows['essai']['agents_available']);
        $this->assertStringContainsString('après le paiement', $rows['essai']['agents_unavailable_reason']);
    }
}
