<?php

namespace App\Notifications;

use App\Models\DocumentHeader;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class NewEcomOrderNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private DocumentHeader $document,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(['mail', 'database'], $this->webPushChannel());
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $partner = $this->document->thirdPartner?->tp_title ?? '—';
        $total   = number_format((float) ($this->document->footer?->total_ttc ?? 0), 2, ',', ' ');

        return (new WebPushMessage)
            ->title("Nouvelle commande — {$total} DH")
            ->body("{$partner} · {$this->document->reference}")
            ->icon('/favicon.ico')
            // No shared tag here: every order deserves its own line, since
            // missing one means an unshipped parcel.
            ->requireInteraction()
            ->data(['url' => $this->webPushUrl('/ventes/documents/' . $this->document->id)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partner = $this->document->thirdPartner?->tp_title ?? '—';
        $total   = $this->document->footer?->total_ttc ?? 0;

        return (new MailMessage)
            ->subject("Nouvelle commande boutique en ligne — {$this->document->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une nouvelle commande vient d'arriver depuis la boutique en ligne.")
            ->line("Référence : **{$this->document->reference}**")
            ->line("Client : **{$partner}**")
            ->line("Montant TTC : **" . number_format($total, 2, ',', ' ') . " MAD**")
            ->action('Voir la commande', $this->appUrl('/ventes/documents/' . $this->document->id))
            ->line("Elle attend d'être confirmée puis préparée.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'new_ecom_order',
            'document_id' => $this->document->id,
            'reference'   => $this->document->reference,
            'total_ttc'   => $this->document->footer?->total_ttc,
        ];
    }

    /**
     * Absolute link to the tenant app. Falls back to url() when there is no
     * tenant in context — a queued notification handled outside tenancy (or a
     * test) would otherwise fatal on null instead of just losing the host.
     */
    private function appUrl(string $path): string
    {
        return tenant()?->appUrl($path) ?? url($path);
    }
}
