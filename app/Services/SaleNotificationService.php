<?php

namespace App\Services;

use App\Models\DocumentHeader;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Sends the customer a WhatsApp confirmation when a sale happens.
 *
 * Sibling of PaymentNotificationService: same settings gate, same
 * WhatsAppService transport, same Moroccan phone formatting. The split is
 * by event, not by channel — that one confirms *money received*, this one
 * confirms *goods sold*, and a customer can legitimately get both for the
 * same document (a paid-on-delivery invoice fires each once).
 */
class SaleNotificationService
{
    /**
     * Document types worth telling the customer about. Quotes are excluded
     * on purpose: a devis is a proposal, not a sale, and pushing it to
     * WhatsApp reads as pressure. Purchase-side and stock documents never
     * involve a customer at all.
     */
    private const NOTIFIABLE_TYPES = [
        'InvoiceSale',
        'DeliveryNote',
        'TicketSale',
        'CustomerOrder',
    ];

    public function __construct(
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * @param string $context 'confirmed' for a validated sale, 'received'
     *                        for an ecommerce order that just landed and is
     *                        still awaiting staff confirmation.
     */
    public function send(DocumentHeader $document, string $context = 'confirmed'): void
    {
        if (!in_array($document->document_type, self::NOTIFIABLE_TYPES, true)) {
            return;
        }

        // Same key the WhatsApp settings tab actually writes — see the note
        // in PaymentNotificationService about the 'enabled' key that never was.
        if (Setting::get('whatsapp', 'whatsapp_enabled', 'true') === 'false') {
            Log::info("SaleNotification: WhatsApp disabled in settings, skipping {$document->reference}.");
            return;
        }

        $phone = $this->resolvePhone($document);

        if (!$phone) {
            Log::info("SaleNotification: no phone for document {$document->reference}, skipping.");
            return;
        }

        $this->whatsApp->send($phone, $this->buildMessage($document, $context));
    }

    /**
     * Prefer the address the buyer typed at checkout over the stored customer
     * profile: a returning customer ordering for someone else (a gift, a
     * different branch) put the reachable number in the shipping snapshot.
     */
    private function resolvePhone(DocumentHeader $document): ?string
    {
        $document->loadMissing('thirdPartner');

        return $document->ship_phone
            ?: $document->thirdPartner?->tp_phone
            ?: null;
    }

    private function buildMessage(DocumentHeader $document, string $context): string
    {
        $document->loadMissing(['thirdPartner', 'footer', 'lignes']);

        $companyName  = Setting::get('company', 'name', 'Mon Entreprise');
        $companyPhone = Setting::get('company', 'phone', '');

        $customerName = $document->ship_name
            ?: $document->thirdPartner?->tp_title
            ?: 'cher client';

        $footer    = $document->footer;
        $totalTtc  = (float) ($footer->total_ttc ?? 0);
        $amountDue = (float) ($footer->amount_due ?? 0);

        $title = $context === 'received'
            ? "*Commande reçue - {$companyName}*"
            : "*{$this->typeLabel($document)} - {$companyName}*";

        $message  = "{$title}\n\n";
        $message .= "Bonjour {$customerName},\n\n";

        $message .= $context === 'received'
            ? "Nous avons bien reçu votre commande. Elle est en cours de préparation.\n"
            : "Nous vous confirmons votre {$this->typeLabel($document)} :\n";

        $message .= "\n";
        $message .= "📄 Référence : {$document->reference}\n";
        $message .= "📅 Date : " . $document->issued_at?->format('d/m/Y') . "\n";
        $message .= "💰 Montant TTC : " . $this->money($totalTtc) . " DH\n";

        $lineCount = $document->lignes->count();
        if ($lineCount > 0) {
            $message .= "📦 Articles : {$lineCount}\n";
        }

        if ($amountDue > 0.009) {
            $message .= "\n⚠️ Reste à payer : " . $this->money($amountDue) . " DH\n";
        } elseif ($totalTtc > 0) {
            $message .= "\n✅ Réglé intégralement\n";
        }

        $message .= "\nMerci pour votre confiance.\n";
        $message .= $companyName;

        if ($companyPhone) {
            $message .= " - {$companyPhone}";
        }

        return $message;
    }

    private function typeLabel(DocumentHeader $document): string
    {
        return match ($document->document_type) {
            'InvoiceSale'   => 'Facture',
            'DeliveryNote'  => 'Bon de livraison',
            'TicketSale'    => 'Ticket de caisse',
            'CustomerOrder' => 'Commande',
            default         => 'Document',
        };
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }
}
