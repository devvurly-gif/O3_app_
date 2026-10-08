<?php

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\PlanChange;
use App\Models\PlanOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agents\AgentsProvisioning;
use App\Services\PlanCatalog;
use App\Services\PlanService;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Personnaliser les formules et leurs prix depuis la gestion des tenants : validé, tracé, réversible ; les factures déjà
 * émises ne bougent jamais ; les prochaines utilisent les nouveaux prix ; le droit aux agents IA suit la formule.
 */
class PlanCatalogTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
    }

    protected function tearDown(): void
    {
        PlanCatalog::refresh();                                                          // le catalogue en mémoire repart des valeurs du code pour le test suivant
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $over = []): array
    {
        $payload = array_replace_recursive([
            'name' => 'Pro', 'tagline' => 'La caisse et le multi-dépôts.', 'price_month_cents' => 79_000, 'price_year_cents' => 790_000, 'setup_fee_cents' => 190_000,
            'features' => ['ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie', 'multi_warehouse', 'price_lists', 'pos'],
            'limits' => ['users' => 10, 'pos_terminals' => 3, 'storage_gb' => 30], 'agents' => true,
        ], $over);
        isset($over['features']) && $payload['features'] = $over['features'];                  // une liste se remplace, elle ne se fusionne pas case par case

        return $payload;
    }

    private function save(string $key, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->putJson("/api/central/plan-catalog/plans/{$key}", $payload);
    }

    public function test_the_catalogue_lists_the_plans_with_their_code_values_counts_and_capabilities(): void
    {
        Tenant::withoutEvents(function () {
            foreach (['a', 'b'] as $id) {
                $t = new Tenant();
                $t->id = $id; $t->name = $id; $t->email = "{$id}@t.ma"; $t->plan = 'pro'; $t->status = TenantStatus::Active; $t->is_active = true;
                $t->save();
            }
        });

        $data = $this->actingAs($this->admin, 'sanctum')->getJson('/api/central/plan-catalog')->assertOk()->json('data');

        $this->assertSame(['essentiel', 'pro', 'business'], array_column($data['plans'], 'key'));
        $pro = collect($data['plans'])->firstWhere('key', 'pro');
        $this->assertSame(69_000, $pro['effective']['price_month_cents']);
        $this->assertSame(2, $pro['tenants_count']);
        $this->assertFalse($pro['overridden']);
        $this->assertTrue($pro['effective']['agents']);
        $this->assertFalse(collect($data['plans'])->firstWhere('key', 'essentiel')['effective']['agents']);
        $this->assertArrayHasKey('pos', $data['capabilities']);
        $this->assertSame(['ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie'], $data['core']);
        $this->assertSame([], $data['changes']);
    }

    public function test_saving_a_plan_changes_prices_content_and_the_agents_right_everywhere_and_logs_it(): void
    {
        $r = $this->save('pro', $this->payload(['agents' => false, 'features' => ['pos', 'reports']]));

        $r->assertOk();
        $this->assertSame(79_000, (new PlanService())->get('pro')['price_month_cents']);                 // le reste de l'application lit le même catalogue
        $this->assertSame(10, config('plans.plans.pro.limits.users'));
        $this->assertEqualsCanonicalizing(['ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie', 'pos', 'reports'], config('plans.plans.pro.features'));   // les capacités de base restent toujours
        $this->assertSame(['business'], AgentsProvisioning::eligiblePlans());                              // « Agents IA » décoché pour Pro
        $this->assertSame(1, PlanOverride::count());

        $change = PlanChange::firstOrFail();
        $this->assertSame(['plan', 'pro', 'update', 'Karim Admin'], [$change->kind, $change->item_key, $change->action, $change->user_name]);
        $this->assertSame(69_000, $change->before['price_month_cents']);
        $this->assertSame(79_000, $change->after['price_month_cents']);
        $this->assertTrue($change->before['agents']);
        $this->assertFalse($change->after['agents']);

        $this->assertSame('pro', $this->actingAs($this->admin, 'sanctum')->getJson('/api/central/plan-catalog')->json('data.changes.0.key'));
        $this->assertTrue(collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/central/plan-catalog')->json('data.plans'))->firstWhere('key', 'pro')['overridden']);
    }

    public function test_invalid_values_are_refused_and_nothing_is_saved(): void
    {
        $this->save('pro', $this->payload(['price_month_cents' => -1]))->assertStatus(422)->assertJsonValidationErrors('price_month_cents');
        $this->save('pro', $this->payload(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
        $this->save('pro', $this->payload(['features' => ['ventes', 'teleportation']]))->assertStatus(422);
        $this->save('pro', $this->payload(['limits' => ['users' => 0]]))->assertStatus(422)->assertJsonValidationErrors('limits.users');
        $this->save('pro', $this->payload(['agents' => 'peut-être']))->assertStatus(422);
        $this->save('inconnue', $this->payload())->assertNotFound();

        $this->assertSame(0, PlanOverride::count());
        $this->assertSame(0, PlanChange::count());
        $this->assertSame(69_000, config('plans.plans.pro.price_month_cents'));
    }

    public function test_unlimited_quotas_are_allowed_and_a_reset_restores_the_code_values(): void
    {
        $this->save('pro', $this->payload(['limits' => ['users' => null, 'storage_gb' => null]]))->assertOk();
        $this->assertNull(config('plans.plans.pro.limits.users'));                       // vide = illimité

        $reset = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/central/plan-catalog/plans/pro')->assertOk();
        $this->assertSame(69_000, $reset->json('data.price_month_cents'));
        $this->assertSame(7, config('plans.plans.pro.limits.users'));
        $this->assertSame(0, PlanOverride::count());
        $this->assertSame(['update', 'reset'], PlanChange::orderBy('id')->pluck('action')->all());
        $this->assertSame(['pro', 'business'], AgentsProvisioning::eligiblePlans());
    }

    public function test_add_on_prices_are_editable(): void
    {
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/central/plan-catalog/addons/extra_user', ['name' => 'Utilisateur en plus', 'price_month_cents' => 7_500])->assertOk();

        $this->assertSame(7_500, config('plans.addons.extra_user.price_month_cents'));
        $this->assertSame('Utilisateur en plus', config('plans.addons.extra_user.name'));
        $this->assertSame('addon', PlanChange::firstOrFail()->kind);
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/central/plan-catalog/addons/rien', ['name' => 'x', 'price_month_cents' => 1])->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/central/plan-catalog/addons/extra_user', ['name' => 'x', 'price_month_cents' => -5])->assertStatus(422);
    }

    public function test_issued_invoices_keep_their_price_and_the_next_ones_use_the_new_one(): void
    {
        Storage::fake('local');
        config()->set('billing.issuer', ['name' => 'O3 App SARL', 'address' => '45 boulevard Mohammed V', 'city' => 'Casablanca', 'ice' => '001234567000089', 'rc' => '123456']);
        $make = fn (string $id) => Tenant::withoutEvents(function () use ($id) {
            $t = new Tenant();
            foreach (['id' => $id, 'name' => "Client {$id}", 'email' => "{$id}@c.ma", 'plan' => 'pro', 'status' => TenantStatus::Active, 'is_active' => true, 'subscription_ends_at' => now()->addDays(10), 'billing_ice' => '002345678000091'] as $k => $v) {
                $t->{$k} = $v;
            }
            $t->save();

            return $t;
        });
        $service = app(SubscriptionInvoiceService::class);

        $old = $service->issueFor($make('ancien'), ['with_setup_fee' => false]);
        $this->assertSame(69_000, $old->subtotal_cents);

        $this->save('pro', $this->payload(['name' => 'Pro Plus', 'price_month_cents' => 79_000]))->assertOk();

        $this->assertSame(69_000, $old->fresh()->subtotal_cents);                         // la facture déjà émise ne bouge jamais, ni son montant…
        $this->assertSame('Pro', $old->fresh()->snapshot['plan']['name']);                // … ni la formule telle qu'elle était écrite dessus
        $new = $service->issueFor($make('nouveau'), ['with_setup_fee' => false]);
        $this->assertSame(79_000, $new->subtotal_cents);                                  // la suivante : le nouveau prix
        $this->assertSame(94_800, $new->amount_ttc_cents);
        $this->assertSame('Pro Plus', $new->snapshot['plan']['name']);
    }

    public function test_only_the_super_admin_can_read_or_change_the_catalogue(): void
    {
        $cashier = User::factory()->cashier()->create();
        $api = $this->actingAs($cashier, 'sanctum');

        $api->getJson('/api/central/plan-catalog')->assertForbidden();
        $api->putJson('/api/central/plan-catalog/plans/pro', $this->payload())->assertForbidden();
        $api->deleteJson('/api/central/plan-catalog/plans/pro')->assertForbidden();
        $this->assertSame(0, PlanOverride::count());
    }
    public function test_a_new_formula_can_be_created_used_edited_and_deleted_only_when_unused(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $payload = $this->payload(['name' => 'Pro Plus', 'price_month_cents' => 89_000, 'agents' => true]) + ['key' => 'pro_plus'];

        $api->postJson('/api/central/plan-catalog/plans', $payload)->assertCreated()->assertJsonPath('data.name', 'Pro Plus');

        $this->assertSame('Pro Plus', config('plans.plans.pro_plus.name'));
        $this->assertSame(89_000, config('plans.plans.pro_plus.price_month_cents'));
        $this->assertContains('pro_plus', config('plans.agents_plans'));                      // « agents » coché : la nouvelle formule ouvre le droit
        $this->assertSame(['essentiel', 'pro', 'business', 'pro_plus'], array_keys(config('plans.plans')));
        $this->assertSame('create', PlanChange::latest('id')->first()->action);

        // survit au redémarrage (la fusion repart des valeurs du code puis relit la base)
        PlanCatalog::refresh();
        $this->assertSame('Pro Plus', config('plans.plans.pro_plus.name'));

        // modifiable : le drapeau « créée ici » est conservé
        $this->save('pro_plus', $this->payload(['name' => 'Pro Plus 2', 'price_month_cents' => 95_000]))->assertOk();
        PlanCatalog::refresh();
        $this->assertSame('Pro Plus 2', config('plans.plans.pro_plus.name'));

        // un abonné la prend : la suppression est refusée avec la raison
        $tenant = new Tenant();
        Tenant::withoutEvents(function () use ($tenant) {
            $tenant->id = 'pp'; $tenant->name = 'PP'; $tenant->email = 'pp@t.ma'; $tenant->plan = 'pro_plus'; $tenant->status = TenantStatus::Active; $tenant->is_active = true;
            $tenant->save();
        });
        $api->deleteJson('/api/central/plan-catalog/plans/pro_plus')->assertStatus(422);
        $this->assertNotNull(config('plans.plans.pro_plus'));

        Tenant::withoutEvents(fn () => $tenant->forceFill(['plan' => 'pro'])->save());
        $api->deleteJson('/api/central/plan-catalog/plans/pro_plus')->assertOk();
        $this->assertNull(config('plans.plans.pro_plus'));
        $this->assertSame('delete', PlanChange::latest('id')->first()->action);
    }

    public function test_a_new_formula_key_must_be_well_formed_and_free(): void
    {
        $api = $this->actingAs($this->admin, 'sanctum');
        $base = $this->payload(['name' => 'X']);

        $api->postJson('/api/central/plan-catalog/plans', $base + ['key' => 'pro'])->assertStatus(422);          // déjà une formule
        $api->postJson('/api/central/plan-catalog/plans', $base + ['key' => 'starter'])->assertStatus(422);      // ancien nom d'une formule
        $api->postJson('/api/central/plan-catalog/plans', $base + ['key' => 'Pro Plus'])->assertStatus(422);     // forme invalide
        $api->postJson('/api/central/plan-catalog/plans', $base + ['key' => '9plus'])->assertStatus(422);
        $api->postJson('/api/central/plan-catalog/plans', $base)->assertStatus(422);
        $this->assertSame(0, PlanOverride::count());
    }

    public function test_the_core_capabilities_are_always_in_a_new_formula(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/central/plan-catalog/plans', $this->payload(['features' => ['pos']]) + ['key' => 'mini'])->assertCreated();

        $this->assertEqualsCanonicalizing(['ventes', 'achats', 'stock', 'tiers', 'documents', 'tresorerie', 'pos'], config('plans.plans.mini.features'));
    }
}
