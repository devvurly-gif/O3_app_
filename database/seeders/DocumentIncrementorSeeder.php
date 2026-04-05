<?php

namespace Database\Seeders;

use App\Models\DocumentIncrementor;
use Illuminate\Database\Seeder;

class DocumentIncrementorSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            // ── Sales ───────────────────────────────────────────
            ['di_title' => 'Facture Vente',         'di_model' => 'InvoiceSale',        'di_domain' => 'sales',     'template' => 'FV-{YY}-{MM}-{NNNN}',        'operatorSens' => 'out'],
            ['di_title' => 'Devis Vente',            'di_model' => 'QuoteSale',          'di_domain' => 'sales',     'template' => 'DEV-{YYYY}-{MM}-{NNNN}',     'operatorSens' => 'neutral'],
            ['di_title' => 'Bon de Commande Client', 'di_model' => 'CustomerOrder',      'di_domain' => 'sales',     'template' => 'BC-{YY}-{MM}-{NNNN}',        'operatorSens' => 'neutral'],
            ['di_title' => 'Bon de Livraison',       'di_model' => 'DeliveryNote',       'di_domain' => 'sales',     'template' => 'BL-{YY}-{MM}-{NNNN}',        'operatorSens' => 'out'],
            ['di_title' => 'Avoir Client',           'di_model' => 'CreditNoteSale',     'di_domain' => 'sales',     'template' => 'AC-{YY}-{MM}-{NNNN}',        'operatorSens' => 'in'],
            ['di_title' => 'Bon de Retour Client',   'di_model' => 'ReturnSale',         'di_domain' => 'sales',     'template' => 'BRC-{YY}-{MM}-{NNNN}',       'operatorSens' => 'in'],

            // ── Purchases ───────────────────────────────────────
            ['di_title' => 'Bon de Commande Achat',  'di_model' => 'PurchaseOrder',      'di_domain' => 'purchases', 'template' => 'BCF-{YY}-{MM}-{NNNN}',       'operatorSens' => 'neutral'],
            ['di_title' => 'Facture Achat',          'di_model' => 'InvoicePurchase',    'di_domain' => 'purchases', 'template' => 'FA-{YYYY}-{MM}-{NNNN}',      'operatorSens' => 'in'],
            ['di_title' => 'Bon de Réception',       'di_model' => 'ReceiptNotePurchase','di_domain' => 'purchases', 'template' => 'BDR-{YYYY}-{MM}-{NNNN}',     'operatorSens' => 'in'],
            ['di_title' => 'Avoir Fournisseur',      'di_model' => 'CreditNotePurchase', 'di_domain' => 'purchases', 'template' => 'AVR-ACH-{YYYY}-{MM}-{NNNN}', 'operatorSens' => 'out'],
            ['di_title' => 'Bon de Retour Fournisseur','di_model' => 'ReturnPurchase',   'di_domain' => 'purchases', 'template' => 'RTR-ACH-{YYYY}-{MM}-{NNNN}', 'operatorSens' => 'out'],

            // ── Stock ───────────────────────────────────────────
            ['di_title' => 'Bon de Transfert',       'di_model' => 'StockTransfer',       'di_domain' => 'stock', 'template' => 'BTR-{YYYY}-{MM}-{NNNN}',    'operatorSens' => 'both'],
            ['di_title' => "Bon d'Ajustement",       'di_model' => 'StockAdjustmentNote', 'di_domain' => 'stock', 'template' => 'ADJ-{YYYY}-{MM}-{NNNN}',    'operatorSens' => 'both'],
            ['di_title' => 'Bon Entrée en Stock',    'di_model' => 'StockEntry',          'di_domain' => 'stock', 'template' => 'INV-{YYYY}-{MM}-{NNNN}',    'operatorSens' => 'in'],
            ['di_title' => 'Bon de Sortie de Stock', 'di_model' => 'StockExit',           'di_domain' => 'stock', 'template' => 'BDS-{YYYY}-{MM}-{NNNN}',    'operatorSens' => 'out'],
        ];

        foreach ($records as $record) {
            DocumentIncrementor::firstOrCreate(
                ['di_model' => $record['di_model'], 'di_domain' => $record['di_domain']],
                array_merge($record, ['nextTrick' => 1, 'status' => true])
            );
        }
    }
}
