<?php

namespace App\Services;

use App\Models\DocumentHeader;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentPdfService
{
    private const TYPE_LABELS = [
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
        'StockEntry'           => 'Bon Entrée en Stock',
        'StockExit'            => 'Bon de Sortie de Stock',
        'StockTransfer'        => 'Bon de Transfert',
        'StockAdjustmentNote'  => "Bon d'Ajustement",
    ];

    public function generate(DocumentHeader $document): \Barryvdh\DomPDF\PDF
    {
        $document->load([
            'thirdPartner',
            'user',
            'warehouse',
            'lignes.product',
            'footer',
            'payments',
        ]);

        $company = $this->getCompanyInfo();

        return Pdf::loadView('pdf.document', [
            'doc'       => $document,
            'company'   => $company,
            'typeLabel' => self::TYPE_LABELS[$document->document_type] ?? $document->document_type,
        ])->setPaper('a4', 'portrait');
    }

    public function filename(DocumentHeader $document): string
    {
        $type = self::TYPE_LABELS[$document->document_type] ?? $document->document_type;
        $ref  = str_replace(['/', '\\', ' '], '-', $document->reference ?? 'DRAFT');

        return "{$type}_{$ref}.pdf";
    }

    private function getCompanyInfo(): array
    {
        return [
            'name'    => Setting::get('company', 'name', 'Mon Entreprise'),
            'address' => Setting::get('company', 'address', ''),
            'city'    => Setting::get('company', 'city', ''),
            'phone'   => Setting::get('company', 'phone', ''),
            'email'   => Setting::get('company', 'email', ''),
            'ice'     => Setting::get('company', 'ice', ''),
            'rc'      => Setting::get('company', 'rc', ''),
            'patente' => Setting::get('company', 'patente', ''),
            'if'      => Setting::get('company', 'if', ''),
        ];
    }
}
