<?php

namespace App\Services;

use App\Mail\PaymentSituationMail;
use App\Models\Setting;
use App\Models\ThirdPartner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentNotificationService
{
    public function __construct(
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * Send payment confirmation (email + WhatsApp) to the partner.
     *
     * @param ThirdPartner $partner
     * @param float        $totalPaid       Total amount paid
     * @param string       $method          Payment method
     * @param string|null  $reference       Payment reference
     * @param array        $affectedInvoices [{reference, amount_applied, amount_due, is_paid}]
     */
    public function send(
        ThirdPartner $partner,
        float $totalPaid,
        string $method,
        ?string $reference,
        array $affectedInvoices,
    ): void {
        // Refresh partner to get updated encours
        $partner->refresh();

        // Calculate total due remaining across ALL unpaid invoices
        $totalDueRemaining = $partner->documentHeaders()
            ->whereIn('document_type', ['InvoiceSale', 'InvoicePurchase'])
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
            ->with('footer')
            ->get()
            ->sum(fn ($doc) => (float) $doc->footer->amount_due);

        $encoursActuel = (float) ($partner->encours_actuel ?? 0);
        $seuilCredit   = (float) ($partner->seuil_credit ?? 0);

        // Send email only if enabled in settings
        $emailEnabled = Setting::get('mail', 'enabled', '0');
        if ($emailEnabled === '1' || $emailEnabled === 'true' || $emailEnabled === true) {
            $this->sendEmail($partner, $totalPaid, $method, $reference, $affectedInvoices, $totalDueRemaining, $encoursActuel, $seuilCredit);
        } else {
            Log::info("PaymentNotification: Email disabled in settings, skipping.");
        }

        // Send WhatsApp only if enabled in settings
        $whatsappEnabled = Setting::get('whatsapp', 'enabled', '0');
        if ($whatsappEnabled === '1' || $whatsappEnabled === 'true' || $whatsappEnabled === true) {
            $this->sendWhatsApp($partner, $totalPaid, $method, $reference, $affectedInvoices, $totalDueRemaining, $encoursActuel, $seuilCredit);
        } else {
            Log::info("PaymentNotification: WhatsApp disabled in settings, skipping.");
        }
    }

    private function sendEmail(
        ThirdPartner $partner,
        float $totalPaid,
        string $method,
        ?string $reference,
        array $affectedInvoices,
        float $totalDueRemaining,
        float $encoursActuel,
        float $seuilCredit,
    ): void {
        if (!$partner->tp_email) {
            Log::info("PaymentNotification: No email for partner {$partner->tp_title}, skipping email.");
            return;
        }

        try {
            Mail::to($partner->tp_email)->send(
                new PaymentSituationMail(
                    partner: $partner,
                    totalPaid: $totalPaid,
                    method: $method,
                    reference: $reference,
                    affectedInvoices: $affectedInvoices,
                    totalDueRemaining: $totalDueRemaining,
                    encoursActuel: $encoursActuel,
                    seuilCredit: $seuilCredit,
                )
            );

            Log::info("PaymentNotification: Email sent to {$partner->tp_email} for partner {$partner->tp_title}");
        } catch (\Throwable $e) {
            Log::warning("PaymentNotification: Email failed for {$partner->tp_title}: {$e->getMessage()}");
        }
    }

    private function sendWhatsApp(
        ThirdPartner $partner,
        float $totalPaid,
        string $method,
        ?string $reference,
        array $affectedInvoices,
        float $totalDueRemaining,
        float $encoursActuel,
        float $seuilCredit,
    ): void {
        if (!$partner->tp_phone) {
            Log::info("PaymentNotification: No phone for partner {$partner->tp_title}, skipping WhatsApp.");
            return;
        }

        $companyName = Setting::get('company', 'name', 'Mon Entreprise');
        $companyPhone = Setting::get('company', 'phone', '');

        $methodLabel = match ($method) {
            'cash'          => 'Espèces',
            'bank_transfer' => 'Virement bancaire',
            'cheque'        => 'Chèque',
            'effet'         => 'Effet',
            default         => $method,
        };

        $message = "*Paiement reçu - {$companyName}*\n\n";
        $message .= "Bonjour {$partner->tp_title},\n\n";
        $message .= "Nous confirmons la réception de votre paiement :\n";
        $message .= "💰 Montant : " . number_format($totalPaid, 2, ',', ' ') . " DH\n";
        $message .= "📋 Méthode : {$methodLabel}\n";

        if ($reference) {
            $message .= "📝 Réf : {$reference}\n";
        }

        $message .= "\nFactures concernées :\n";
        foreach ($affectedInvoices as $inv) {
            $status = $inv['is_paid'] ? '✅ Soldée' : number_format($inv['amount_due'], 2, ',', ' ') . ' DH restant';
            $message .= "• {$inv['reference']} : " . number_format($inv['amount_applied'], 2, ',', ' ') . " DH — {$status}\n";
        }

        $message .= "\n📊 Votre situation :\n";
        $message .= "Total dû : " . number_format($totalDueRemaining, 2, ',', ' ') . " DH\n";

        if ($seuilCredit > 0) {
            $message .= "Encours crédit : " . number_format($encoursActuel, 2, ',', ' ') . " / " . number_format($seuilCredit, 2, ',', ' ') . " DH\n";
        }

        $message .= "\nMerci pour votre confiance.\n";
        $message .= "{$companyName}";

        if ($companyPhone) {
            $message .= " - {$companyPhone}";
        }

        $this->whatsApp->send($partner->tp_phone, $message);
    }
}
