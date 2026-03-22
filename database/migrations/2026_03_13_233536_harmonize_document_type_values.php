<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Harmonize document_type values to match di_model in document_incrementors.
     */
    public function up(): void
    {
        $renames = [
            'Invoice'          => 'InvoiceSale',
            'Quote'            => 'QuoteSale',
            'PurchaseInvoice'  => 'InvoicePurchase',
            'ReceiptNote'      => 'ReceiptNotePurchase',
            'CreditNote'       => 'CreditNoteSale',
            'StockAdjustment'  => 'StockAdjustmentNote',
        ];

        foreach ($renames as $old => $new) {
            DB::table('document_headers')
                ->where('document_type', $old)
                ->update(['document_type' => $new]);

            DB::table('stock_mouvements')
                ->where('document_type', $old)
                ->update(['document_type' => $new]);
        }
    }

    public function down(): void
    {
        $renames = [
            'InvoiceSale'          => 'Invoice',
            'QuoteSale'            => 'Quote',
            'InvoicePurchase'      => 'PurchaseInvoice',
            'ReceiptNotePurchase'  => 'ReceiptNote',
            'CreditNoteSale'       => 'CreditNote',
            'StockAdjustmentNote'  => 'StockAdjustment',
        ];

        foreach ($renames as $old => $new) {
            DB::table('document_headers')
                ->where('document_type', $old)
                ->update(['document_type' => $new]);

            DB::table('stock_mouvements')
                ->where('document_type', $old)
                ->update(['document_type' => $new]);
        }
    }
};
