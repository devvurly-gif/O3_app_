<?php

namespace App\Notifications;

use App\Models\DocumentHeader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60; // retry after 60 seconds

    private static array $typeLabels = [
        'QuoteSale'            => 'Devis',
        'CustomerOrder'        => 'Bon de Commande Client',
        'DeliveryNote'         => 'Bon de Livraison',
        'InvoiceSale'          => 'Facture',
        'CreditNoteSale'       => 'Avoir Client',
        'ReturnSale'           => 'Bon de Retour Client',
        'PurchaseOrder'        => 'Bon de Commande',
        'ReceiptNotePurchase'  => 'Bon de Réception',
        'InvoicePurchase'      => 'Facture Achat',
        'CreditNotePurchase'   => 'Avoir Fournisseur',
        'ReturnPurchase'       => 'Bon de Retour Fournisseur',
    ];

    public function __construct(
        private DocumentHeader $document,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = self::$typeLabels[$this->document->document_type] ?? $this->document->document_type;
        $partner   = $this->document->thirdPartner?->tp_title ?? '—';
        $total     = $this->document->footer?->total_ttc ?? 0;

        return (new MailMessage)
            ->subject("{$typeLabel} confirmé(e) — {$this->document->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le document **{$this->document->reference}** ({$typeLabel}) a été confirmé.")
            ->line("Client / Fournisseur : **{$partner}**")
            ->line("Montant TTC : **" . number_format($total, 2, ',', ' ') . " MAD**")
            ->action('Voir le document', url($this->documentUrl()))
            ->line('Ce document est maintenant actif.');
    }

    private function documentUrl(): string
    {
        $prefix = in_array($this->document->document_type, ['PurchaseOrder', 'ReceiptNotePurchase', 'InvoicePurchase', 'CreditNotePurchase', 'ReturnPurchase'])
            ? '/achats/documents/'
            : '/ventes/documents/';

        return $prefix . $this->document->id;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'order_confirmation',
            'document_id'  => $this->document->id,
            'reference'    => $this->document->reference,
            'document_type'=> $this->document->document_type,
            'total_ttc'    => $this->document->footer?->total_ttc,
        ];
    }
}
