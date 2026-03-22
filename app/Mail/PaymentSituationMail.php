<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\ThirdPartner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSituationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $company;
    public string $methodLabel;

    public function __construct(
        public ThirdPartner $partner,
        public float $totalPaid,
        public string $method,
        public ?string $reference,
        public array $affectedInvoices, // [{reference, amount_applied, amount_due, is_paid}]
        public float $totalDueRemaining,
        public float $encoursActuel,
        public float $seuilCredit,
    ) {
        $this->company = [
            'name'    => Setting::get('company', 'name', 'Mon Entreprise'),
            'address' => Setting::get('company', 'address', ''),
            'city'    => Setting::get('company', 'city', ''),
            'phone'   => Setting::get('company', 'phone', ''),
            'email'   => Setting::get('company', 'email', ''),
            'ice'     => Setting::get('company', 'ice', ''),
        ];

        $this->methodLabel = match ($this->method) {
            'cash'          => 'Espèces',
            'bank_transfer' => 'Virement bancaire',
            'cheque'        => 'Chèque',
            'effet'         => 'Effet',
            default         => $this->method,
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmation de paiement — {$this->company['name']}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-situation',
        );
    }

}
