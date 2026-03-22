<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WarehouseHasStock;
use App\Notifications\LowStockAlert;
use Illuminate\Console\Command;

class SendLowStockAlerts extends Command
{
    protected $signature = 'notify:low-stock {--threshold=5 : Stock level threshold}';

    protected $description = 'Send email alerts for products with low stock levels';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');

        $lowItems = WarehouseHasStock::with(['product', 'warehouse'])
            ->where('stockLevel', '>', 0)
            ->where('stockLevel', '<=', $threshold)
            ->get();

        if ($lowItems->isEmpty()) {
            $this->info('No low-stock items found.');
            return self::SUCCESS;
        }

        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager', 'warehouse']))
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No active admin/manager/warehouse users to notify.');
            return self::SUCCESS;
        }

        $notification = new LowStockAlert($lowItems);

        foreach ($recipients as $user) {
            $user->notify($notification);
        }

        $this->info("Low stock alert sent to {$recipients->count()} user(s) for {$lowItems->count()} product(s).");

        return self::SUCCESS;
    }
}
