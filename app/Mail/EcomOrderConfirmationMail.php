<?php

namespace App\Mail;

use App\Models\DocumentHeader;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EcomOrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $company;

    public function __construct(
        public DocumentHeader $document,
    ) {
        $this->document->loadMissing(['lignes', 'footer']);

        $this->company = [
            'name'    => Setting::get('company', 'name', 'Mon Entreprise'),
            'address' => Setting::get('company', 'address', ''),
            'city'    => Setting::get('company', 'city', ''),
            'phone'   => Setting::get('company', 'phone', ''),
            'email'   => Setting::get('company', 'email', ''),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmation de votre commande {$this->document->reference} — {$this->company['name']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ecom-order-confirmation',
        );
    }
}
