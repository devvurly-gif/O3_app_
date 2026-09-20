<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Relance d'échéance envoyée au client.
 *
 * Un seul modèle couvre les quatre moments (J-7, J-3, J-1 et après échéance) :
 * le ton change, pas la structure, et une seule vue à maintenir vaut mieux que
 * quatre qui divergent.
 */
class SubscriptionReminderMail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $planName,
        public string $endsAt,
        public int $daysLeft,
        public string $subscriptionUrl,
        public bool $isTrial,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminderSubject());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-reminder');
    }

    /**
     * Objet de l'email.
     *
     * Ni `subject()` ni `buildSubject()` : Mailable déclare déjà ces deux
     * méthodes, et les redéfinir en privé est une erreur fatale de PHP.
     */
    private function reminderSubject(): string
    {
        if ($this->daysLeft <= 0) {
            return $this->isTrial
                ? "Votre essai O3 App est terminé"
                : "Votre abonnement O3 App est arrivé à échéance";
        }

        $jours = $this->daysLeft === 1 ? 'jour' : 'jours';

        return $this->isTrial
            ? "Il vous reste {$this->daysLeft} {$jours} d'essai sur O3 App"
            : "Votre abonnement O3 App expire dans {$this->daysLeft} {$jours}";
    }
}
