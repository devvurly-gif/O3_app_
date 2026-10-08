<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient le super-administrateur (jamais le client) qu'un abonné vient de passer en retard de paiement : lecture seule
 * (past_due) ou accès suspendu (suspended). Si les agents IA étaient allumés chez lui, le message dit qu'ils se referment.
 * Une seule alerte par changement de statut : la commande quotidienne ne la renvoie pas tant que le statut ne change pas.
 */
class TenantPaymentLate extends Notification
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $tenantName,
        private readonly string $planName,
        private readonly string $status,          // past_due | suspended
        private readonly string $endedOn,         // jj/mm/aaaa
        private readonly bool $agentsWereOn,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return filter_var($notifiable->email ?? '', FILTER_VALIDATE_EMAIL) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('O3 — ' . $this->title())
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->title() . '.')
            ->line("Formule : {$this->planName} — échéance du {$this->endedOn}.")
            ->line($this->status === 'suspended'
                ? "Son accès est suspendu : il ne peut plus se connecter qu'à la page d'abonnement."
                : "Son compte est en lecture seule : il consulte ses données mais n'écrit plus.");
        if ($this->agentsWereOn) {
            $mail->line("Ses agents IA sont allumés : ils se referment tant que l'abonnement n'est pas réglé (rien n'est supprimé, tout revient au paiement).");
        }

        return $mail->action('Ouvrir la fiche du client', $this->url)
            ->line("Aucun message n'a été envoyé au client par cette alerte.")
            ->salutation('Cordialement, ' . config('app.name'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'tenant_payment_late',
            'tenant_id'   => $this->tenantId,
            'tenant_name' => $this->tenantName,
            'status'      => $this->status,
            'agents'      => $this->agentsWereOn,
            'title'       => $this->title(),
            'url'         => $this->url,
        ];
    }

    private function title(): string
    {
        $what = $this->status === 'suspended' ? 'est suspendu (impayé)' : 'est en retard de paiement';

        return "« {$this->tenantName} » {$what}" . ($this->agentsWereOn ? ' — ses agents IA se referment' : '');
    }
}
