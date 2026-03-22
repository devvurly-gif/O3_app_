<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
