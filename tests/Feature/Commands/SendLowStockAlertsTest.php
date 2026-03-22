<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Models\WarehouseHasStock;
use App\Notifications\LowStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendLowStockAlertsTest extends TestCase
{
    use RefreshDatabase;

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
}
