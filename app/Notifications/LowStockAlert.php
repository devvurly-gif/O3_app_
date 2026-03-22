<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $lowStockItems,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->lowStockItems->count();

        $message = (new MailMessage)
            ->subject("Alerte stock bas — {$count} produit(s)")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$count} produit(s) ont un niveau de stock critique :");

        foreach ($this->lowStockItems->take(10) as $item) {
            $message->line("• {$item->product->p_title} — {$item->warehouse->wh_title} : {$item->stockLevel} unités");
        }

        if ($count > 10) {
            $message->line("… et " . ($count - 10) . " autre(s).");
        }

        return $message
            ->action('Voir le stock', url('/stock/mouvements'))
            ->line('Pensez à réapprovisionner ces produits.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'  => 'low_stock',
            'count' => $this->lowStockItems->count(),
            'items' => $this->lowStockItems->take(10)->map(fn ($i) => [
                'product'   => $i->product->p_title,
                'warehouse' => $i->warehouse->wh_title,
                'level'     => $i->stockLevel,
            ])->toArray(),
        ];
    }
}
