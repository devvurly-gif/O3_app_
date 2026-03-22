<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockMovementAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Product   $product,
        private Warehouse $warehouse,
        private float     $newStockLevel,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'stock_movement',
            'product_id'      => $this->product->id,
            'product_name'    => $this->product->p_title,
            'warehouse_id'    => $this->warehouse->id,
            'warehouse_name'  => $this->warehouse->wh_title,
            'new_stock_level' => $this->newStockLevel,
        ];
    }
}
