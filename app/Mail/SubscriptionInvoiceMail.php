<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\TenantInvoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Facture d'abonnement envoyee au client, PDF en piece jointe.
 *
 * Le PDF est passe en contenu brut plutot que par un chemin : c'est celui qui
 * a ete fige a l'emission, et le service sait le relire ou le regenerer. Le
 * mailable n'a pas a savoir ou il est range.
 */
class SubscriptionInvoiceMail extends Mailable
{
    public function __construct(
        public TenantInvoice $invoice,
        private readonly string $pdfContents,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Facture {$this->invoice->number} — abonnement O3 App",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-invoice',
            with: [
                'invoice' => $this->invoice,
                'issuer'  => $this->invoice->snapshot['issuer'] ?? [],
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfContents, 'Facture_' . $this->invoice->number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
