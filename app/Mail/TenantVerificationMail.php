<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a public registrant right after they submit the sign-up form.
 * Contains the magic verification link that activates their tenant.
 */
class TenantVerificationMail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $adminName,
        public string $domain,
        public string $verifyUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Activez votre espace {$this->companyName} sur O3 App",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tenant-verification');
    }
}
