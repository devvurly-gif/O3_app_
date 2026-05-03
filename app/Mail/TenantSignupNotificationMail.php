<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the platform admin (you) whenever a public registration
 * provisions a new tenant. Heads-up + quick link to the central admin.
 */
class TenantSignupNotificationMail extends Mailable
{
    public function __construct(
        public string $tenantId,
        public string $companyName,
        public string $adminName,
        public string $email,
        public ?string $phone,
        public string $domain,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[O3 App] Nouveau tenant: {$this->companyName} ({$this->tenantId})",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tenant-signup-notification');
    }
}
