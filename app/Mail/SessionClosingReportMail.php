<?php

namespace App\Mail;

use App\Models\PosSession;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SessionClosingReportMail extends Mailable
{

    public array $company;

    public function __construct(
        public PosSession $session,
        public array $stats,
        public string $pdfContent,
    ) {
        $this->company = [
            'name'  => Setting::get('company', 'name', 'Mon Entreprise'),
            'phone' => Setting::get('company', 'phone', ''),
        ];
    }

    public function envelope(): Envelope
    {
        $terminal = $this->session->terminal->name ?? 'POS';
        $closedAt = $this->session->closed_at;
        $date = $closedAt instanceof Carbon
            ? $closedAt->format('d/m/Y H:i')
            : (is_string($closedAt) ? Carbon::parse($closedAt)->format('d/m/Y H:i') : now()->format('d/m/Y H:i'));

        return new Envelope(
            subject: "Rapport de fermeture — {$terminal} — {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.session-closing-report',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = 'rapport-fermeture-session-' . $this->session->id . '.pdf';

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
