<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envoyé à O3App quand un client choisit une formule depuis son espace.
 *
 * C'est le signal d'envoyer le contrat et la facture : tant que le paiement en
 * ligne n'existe pas, cet email est le seul maillon entre le choix du client
 * et l'encaissement.
 */
class PlanChangeRequestMail extends Mailable
{
    public function __construct(
        public string $tenantId,
        public string $companyName,
        public string $email,
        public ?string $phone,
        public string $plan,
        public string $planName,
        public string $billingPeriod,
        public ?string $note,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[O3 App] {$this->companyName} demande la formule {$this->planName}",
            replyTo: [$this->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.plan-change-request');
    }
}
