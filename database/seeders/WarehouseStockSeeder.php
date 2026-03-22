<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Illuminate\Database\Seeder;

class WarehouseStockSeeder extends Seeder
{
    public function run(): void
    {
        $admin      = User::where('email', 'admin@o2app.ma')->first();
        $mainWh     = Warehouse::where('wh_code', 'WH-MAIN')->first();
        $casaWh     = Warehouse::where('wh_code', 'WH-CASA')->first();
        $products   = Product::all();

        $initialStocks = [
            'HP-PB450-I5'      => ['WH-MAIN' => 15, 'WH-CASA' => 8],
            'DELL-LAT5530-I7'  => ['WH-MAIN' => 10, 'WH-CASA' => 5],
            'CAN-PIXMA-G3420'  => ['WH-MAIN' => 20, 'WH-CASA' => 12],
            'CAN-INK-PG545'    => ['WH-MAIN' => 100, 'WH-CASA' => 60],
            'TPL-SG108'        => ['WH-MAIN' => 25, 'WH-CASA' => 15],
            'LOG-MXM3'         => ['WH-MAIN' => 30, 'WH-CASA' => 20],
            'PAP-A4-80G'       => ['WH-MAIN' => 200, 'WH-CASA' => 150],
            'LEN-L15G3-R5'     => ['WH-MAIN' => 12, 'WH-CASA' => 6],
        ];

        $warehouseMap = [
            'WH-MAIN' => $mainWh,
            'WH-CASA' => $casaWh,
        ];

        foreach ($products as $product) {
            $stocks = $initialStocks[$product->p_sku] ?? [];

            foreach ($stocks as $whCode => $qty) {
                $warehouse = $warehouseMap[$whCode] ?? null;
                if (!$warehouse) continue;

                // Create stock record
                $stock = WarehouseHasStock::firstOrCreate(
                    ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
                    [
                        'stockLevel'  => $qty,
                        'stockAtTime' => now(),
                        'wh_average'  => $product->p_purchasePrice,
                        'user_id'     => $admin?->id,
                    ]
                );

                // Record initial movement
                StockMouvement::firstOrCreate(
                    [
                        'product_id'   => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'reason'       => 'initial',
                    ],
                    [
                        'direction'    => 'in',
                        'reason'       => 'initial',
                        'quantity'     => $qty,
                        'unit_cost'    => $product->p_purchasePrice,
                        'stock_before' => 0,
                        'stock_after'  => $qty,
                        'user_id'      => $admin?->id,
                        'notes'        => 'Stock initial',
                    ]
                );
            }
        }
    }
}
