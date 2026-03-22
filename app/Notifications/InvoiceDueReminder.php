<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class InvoiceDueReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Collection $overdueDocuments,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->overdueDocuments->count();

        $message = (new MailMessage)
            ->subject("Rappel échéance — {$count} facture(s) en retard")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$count} facture(s) ont dépassé leur date d'échéance :");

        foreach ($this->overdueDocuments->take(10) as $doc) {
            $partner = $doc->thirdPartner?->tp_title ?? '—';
            $due     = $doc->footer?->amount_due ?? 0;
            $dueAt   = $doc->due_at?->format('d/m/Y') ?? '—';
            $message->line("• {$doc->reference} — {$partner} — {$dueAt} — Reste dû : " . number_format($due, 2, ',', ' ') . " MAD");
        }

        if ($count > 10) {
            $message->line("… et " . ($count - 10) . " autre(s).");
        }

        return $message
            ->action('Voir les documents', url('/ventes/documents'))
            ->line('Merci de procéder au recouvrement.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'  => 'invoice_due_reminder',
            'count' => $this->overdueDocuments->count(),
            'items' => $this->overdueDocuments->take(10)->map(fn ($d) => [
                'reference'  => $d->reference,
                'partner'    => $d->thirdPartner?->tp_title,
                'due_at'     => $d->due_at?->format('Y-m-d'),
                'amount_due' => $d->footer?->amount_due,
            ])->toArray(),
        ];
    }
}
