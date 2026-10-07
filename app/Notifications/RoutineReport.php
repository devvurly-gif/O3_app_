<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Prévient l'administrateur, hors du chat, qu'une routine planifiée vient de déposer un compte rendu qui demande sa
 * décision (propositions à valider) ou qui a échoué. Pas de message au client ni à un tiers : seulement la cloche et, si
 * les clés sont configurées, une notification push à l'administrateur lui-même. Le texte ne reprend que le nom de la routine
 * et des comptes : le détail reste dans le chat.
 */
class RoutineReport extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        private int $routineId,
        private string $routineName,
        private string $status,
        private int $pending,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(['database'], $this->webPushChannel());
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
