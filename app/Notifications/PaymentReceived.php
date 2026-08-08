<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        private Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(['database'], $this->webPushChannel());
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $document = $this->payment->document;
        $partner  = $document->thirdPartner?->tp_title ?? '—';
        $amount   = number_format((float) $this->payment->amount, 2, ',', ' ');

        return (new WebPushMessage)
            ->title("Paiement reçu — {$amount} DH")
            ->body("{$partner} · {$document->reference}")
            ->icon('/favicon.ico')
            // Same tag for every payment alert so a burst collapses into one
            // entry instead of burying the phone's notification shade.
            ->tag('payment')
            ->data(['url' => $this->webPushUrl('/ventes/documents/' . $document->id)]);
    }

    public function toArray(object $notifiable): array
    {
        $document = $this->payment->document;

        return [
            'type'               => 'payment_received',
            'payment_id'         => $this->payment->id,
            'document_reference' => $document->reference,
            'document_type'      => $document->document_type,
            'amount'             => (float) $this->payment->amount,
            'method'             => $this->payment->method,
            'partner_name'       => $document->thirdPartner?->tp_title ?? '-',
        ];
    }
}
