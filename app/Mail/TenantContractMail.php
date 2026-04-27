<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email sent to a prospective tenant to share the SaaS service contract
 * (and optionally the subscription intake form) for electronic signature.
 *
 * Attachments are read at send-time from disk. Sources:
 *   - docs/legal/contrat-services-saas.docx
 *   - docs/legal/fiche-souscription-client.docx (when $includeIntakeForm)
 */
class TenantContractMail extends Mailable
{
    public string $companyName;
    public string $clientName;
    /** @var string|null Custom message from the sender (HTML-safe via blade). */
    public ?string $customMessage;

    /**
     * @param  Tenant       $tenant            Recipient tenant (only id/name/email used).
     * @param  string|null  $customMessage     Optional free-text intro from the sender.
     * @param  bool         $includeIntakeForm Attach fiche-souscription-client.docx if true.
     */
    public function __construct(
        public Tenant $tenant,
        ?string $customMessage = null,
        public bool $includeIntakeForm = true,
    ) {
        $this->companyName   = Setting::get('company', 'name', 'O3 App');
        $this->clientName    = $tenant->name ?: 'Client';
        $this->customMessage = $customMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Contrat de services {$this->companyName} — {$this->clientName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-contract',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $files = [];

        $contract = base_path('docs/legal/contrat-services-saas.docx');
        if (is_file($contract)) {
            $files[] = Attachment::fromPath($contract)
                ->as('contrat-services-saas.docx')
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        }

        if ($this->includeIntakeForm) {
            $fiche = base_path('docs/legal/fiche-souscription-client.docx');
            if (is_file($fiche)) {
                $files[] = Attachment::fromPath($fiche)
                    ->as('fiche-souscription-client.docx')
                    ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            }
        }

        return $files;
    }
}
