<?php

namespace App\Notifications;

use App\Models\DocumentHeader;
use App\Models\OrderMessage;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Un BL brouillon vient d'être créé depuis la messagerie commandes
 * (WhatsApp, SMS ou chat) : à relire puis confirmer dans O3.
 */
class MessageOrderDrafted extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private DocumentHeader $document,
        private string $channel,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(['database'], $this->webPushChannel());
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $partner = $this->document->thirdPartner?->tp_title ?? '—';

        return (new WebPushMessage)
            ->title('BL brouillon reçu par ' . $this->channelLabel())
            ->body("{$partner} · {$this->document->reference}")
            ->icon('/favicon.ico')
            ->data(['url' => $this->webPushUrl('/ventes/documents/' . $this->document->id)]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'message_order',
            'channel'     => $this->channel,
            'channel_label' => $this->channelLabel(),
            'document_id' => $this->document->id,
            'reference'   => $this->document->reference,
            'customer'    => $this->document->thirdPartner?->tp_title,
        ];
    }

    private function channelLabel(): string
    {
        return OrderMessage::CHANNEL_LABELS[$this->channel] ?? $this->channel;
    }
}
