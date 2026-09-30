<?php

namespace App\Notifications;

use App\Models\OrderMessage;
use App\Models\ThirdPartner;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Cinq PIN faux de suite sur les commandes par message d'un client : son canal
 * WhatsApp/SMS est bloqué jusqu'à ce que l'équipe génère un nouveau PIN.
 */
class OrderPinLocked extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private ThirdPartner $customer,
        private string $channel,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(['database'], $this->webPushChannel());
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Commandes par message bloquées')
            ->body("{$this->customer->tp_title} : 5 PIN incorrects reçus par {$this->channelLabel()}")
            ->icon('/favicon.ico')
            ->data(['url' => $this->webPushUrl('/customers')]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'order_pin_locked',
            'channel'       => $this->channel,
            'channel_label' => $this->channelLabel(),
            'customer_id'   => $this->customer->id,
            'customer'      => $this->customer->tp_title,
            'customer_code' => $this->customer->tp_code,
        ];
    }

    private function channelLabel(): string
    {
        return OrderMessage::CHANNEL_LABELS[$this->channel] ?? $this->channel;
    }
}
