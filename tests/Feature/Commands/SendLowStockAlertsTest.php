<?php

namespace Tests\Feature\Commands;

use App\Enums\TenantStatus;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarehouseHasStock;
use App\Notifications\LowStockAlert;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\RefreshTenantDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendLowStockAlertsTest extends TestCase
{
    use InteractsWithTenancy;
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le planificateur lance la commande depuis le contexte central : sans
        // tenant enregistré, elle n'a plus personne à prévenir.
        $this->tenantsOnTheTestDatabase(['jadema' => []]);
    }

    public function test_command_sends_alerts_when_low_stock_exists(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        WarehouseHasStock::factory()->create(['stockLevel' => 3]);

        $this->artisan('notify:low-stock', ['--threshold' => 5])
             ->assertSuccessful();

        Notification::assertSentTo($admin, LowStockAlert::class);
    }

    public function test_command_does_not_send_when_no_low_stock(): void
    {
        Notification::fake();

        User::factory()->admin()->create();
        WarehouseHasStock::factory()->create(['stockLevel' => 100]);

        $this->artisan('notify:low-stock', ['--threshold' => 5])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_skips_zero_stock_items(): void
    {
        Notification::fake();

        User::factory()->admin()->create();
        WarehouseHasStock::factory()->create(['stockLevel' => 0]);

        $this->artisan('notify:low-stock', ['--threshold' => 5])
             ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_sends_to_admin_manager_warehouse_only(): void
    {
        Notification::fake();

        $admin   = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $whUser  = User::factory()->warehouse()->create();
        $cashier = User::factory()->cashier()->create();

        WarehouseHasStock::factory()->create(['stockLevel' => 2]);

        $this->artisan('notify:low-stock', ['--threshold' => 5])
             ->assertSuccessful();

        Notification::assertSentTo($admin, LowStockAlert::class);
        Notification::assertSentTo($manager, LowStockAlert::class);
        Notification::assertSentTo($whUser, LowStockAlert::class);
        Notification::assertNotSentTo($cashier, LowStockAlert::class);
    }

    /**
     * Régression : lancée depuis le contexte central par o3-scheduler, la
     * commande lisait le stock de la base centrale et ne prévenait personne.
     */
    public function test_each_tenant_in_good_standing_is_alerted_about_its_own_stock(): void
    {
        Notification::fake();

        $this->tenantsOnTheTestDatabase([
            'teliphoni' => ['status' => TenantStatus::Trial],
            // Lecture seule : son stock reste le sien, l'alerte aussi.
            'impaye'    => ['status' => TenantStatus::PastDue],
            'suspendu'  => ['status' => TenantStatus::Suspended],
            'coupe'     => ['is_active' => false],
        ]);

        $alerted = [];
        $this->isolateEachTenantRun(
            seed: function (Tenant $tenant) {
                User::factory()->admin()->create(['email' => "admin@{$tenant->id}.test"]);
                WarehouseHasStock::factory()->create([
                    'stockLevel' => 2,
                    'product_id' => Product::factory()->create(['p_title' => "Produit {$tenant->id}"])->id,
                ]);
            },
            inspect: function (Tenant $tenant) use (&$alerted) {
                $admin = User::where('email', "admin@{$tenant->id}.test")->firstOrFail();

                $alerted[$tenant->id] = Notification::sent($admin, LowStockAlert::class)
                    ->map(fn (LowStockAlert $alert) => array_column($alert->toArray($admin)['items'], 'product'))
                    ->all();
            },
        );

        $this->artisan('notify:low-stock', ['--threshold' => 5])->assertSuccessful();

        $this->assertSame([
            'impaye'    => [['Produit impaye']],
            'jadema'    => [['Produit jadema']],
            'teliphoni' => [['Produit teliphoni']],
        ], $alerted);
    }
}
