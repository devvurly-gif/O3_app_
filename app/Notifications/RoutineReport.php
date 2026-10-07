<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Prévient l'administrateur, hors du chat, qu'une routine planifiée vient de déposer un compte rendu qui demande sa
 * décision (propositions à valider) ou qui a échoué. Pas de message au client ni à un tiers : seulement la cloche, un e-mail
 * à l'administrateur lui-même (réglage « agents.routine_email », activé par défaut, au plus un par routine et par 6 heures)
 * et, si les clés sont configurées, une notification push. Le texte ne reprend que le nom de la routine et des comptes :
 * le détail (clients, montants) reste dans le chat.
 */
class RoutineReport extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        private int $routineId,
        private string $routineName,
        private string $status,
        private int $pending,
        private bool $mail = false,
    ) {}

    public function via(object $notifiable): array
    {
        // L'e-mail ne part que si l'administrateur l'a gardé activé (réglage) et que la routine n'a pas déjà écrit ces dernières heures.
        $mail = $this->mail && filter_var($notifiable->email ?? '', FILTER_VALIDATE_EMAIL) ? ['mail'] : [];

        return array_merge(['database'], $mail, $this->webPushChannel());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('O3 — ' . $this->title())
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->title() . '.')
            ->line("Rien n'a été appliqué : les routines ne font que lire et préparer des propositions que vous validez.")
            ->action("Ouvrir l'orchestrateur", $this->webPushUrl('/settings/orchestrateur'))
            ->line("Pour ne plus recevoir ces e-mails, écrivez « désactive l'e-mail des routines » à l'orchestrateur.")
            ->salutation('Cordialement, ' . config('app.name'));
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body('Ouvrez l\'orchestrateur pour voir le compte rendu et valider.')
            ->icon('/favicon.ico')
            ->tag('routine-' . $this->routineId)
            ->data(['url' => $this->webPushUrl('/settings/orchestrateur')]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'routine_report',
            'routine_id' => $this->routineId,
            'routine'    => $this->routineName,
            'status'     => $this->status,
            'pending'    => $this->pending,
            'title'      => $this->title(),
        ];
    }

    private function title(): string
    {
        return match (true) {
            $this->status === 'error'   => "Routine « {$this->routineName} » : échec",
            $this->pending > 0          => "Routine « {$this->routineName} » : {$this->pending} proposition(s) à valider" . ($this->status === 'partial' ? ' (étapes non réalisées)' : ''),
            default                     => "Routine « {$this->routineName} » : étapes non réalisées",
        };
    }
}
