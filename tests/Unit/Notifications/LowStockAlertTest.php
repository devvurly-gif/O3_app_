<?php

namespace Tests\Unit\Notifications;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Notifications\LowStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_is_sent_to_mail_and_database(): void
    {
        $items = collect([
            WarehouseHasStock::factory()->create(['stockLevel' => 2]),
        ]);

        $notif = new LowStockAlert($items);

        $this->assertEquals(['mail', 'database'], $notif->via(new User()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $stock = WarehouseHasStock::factory()->create(['stockLevel' => 3]);
        $items = collect([$stock]);

        $notif  = new LowStockAlert($items);
        $result = $notif->toArray(User::factory()->create());

        $this->assertEquals('low_stock', $result['type']);
        $this->assertEquals(1, $result['count']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(3, $result['items'][0]['level']);
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $stock = WarehouseHasStock::factory()->create(['stockLevel' => 1]);
        $items = collect([$stock]);

        $notif = new LowStockAlert($items);
        $mail  = $notif->toMail(User::factory()->create());

        $this->assertStringContainsString('1 produit(s)', $mail->subject);
    }
}
