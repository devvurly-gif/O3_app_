<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_incrementors', function (Blueprint $table) {
            $table->id();
            $table->string('di_title');
            $table->string('di_model');
            $table->string('di_domain');
            $table->string('template');
            $table->integer('nextTrick')->default(1);
            $table->boolean('status')->default(true);
            $table->enum('operatorSens', ['in', 'out','neutral','both'])->default('in'); 
            $table->unique(['di_model', 'di_domain']);
            $table->timestamps();
        });


        \App\Models\DocumentIncrementor::insert([

             // ── sales (missing) ──────────────────────────────────────────
            [
                'di_title' => 'Facture Vente',
                'di_model' => 'InvoiceSale',
                'di_domain' => 'sales',
                'template' => 'FV-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'out',
            ],
            [
                'di_title' => 'Devis Vente',
                'di_model' => 'QuoteSale',
                'di_domain' => 'sales',
                'template' => 'DEV-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'neutral',
            ],
              
            [
                'di_title' => 'Bon de Commande Client',
                'di_model' => 'CustomerOrder',
                'di_domain' => 'sales',
                'template' => 'BC-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'neutral',
            ],
            [
                'di_title' => 'Bon de Livraison',
                'di_model' => 'DeliveryNote',
                'di_domain' => 'sales',
                'template' => 'BL-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'out',
            ],
            [
                'di_title' => 'Avoir Client',
                'di_model' => 'CreditNoteSale',
                'di_domain' => 'sales',
                'template' => 'AC-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'in',
            ],
            [
                'di_title' => 'Bon de Retour Client',
                'di_model' => 'ReturnSale',
                'di_domain' => 'sales',
                'template' => 'BRC-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'in',
            ],
            [
                'di_title' => 'Bon de Commande Achat',
                'di_model' => 'PurchaseOrder',
                'di_domain' => 'purchases',
                'template' => 'BCF-{YY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'neutral',
            ],

             // ── Purchases  ──────────────────────────────────────────
            [
                'di_title' => 'Facture Achat',
                'di_model' => 'InvoicePurchase',
                'di_domain' => 'purchases',
                'template' => 'FA-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'in',
            ],
            [
                'di_title' => 'Bon de Réception',
                'di_model' => 'ReceiptNotePurchase',
                'di_domain' => 'purchases',
                'template' => 'BDR-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'in',
            ],
         
           [
                'di_title' => 'Avoir Fournisseur',
                'di_model' => 'CreditNotePurchase',
                'di_domain' => 'purchases',
                'template' => 'AVR-ACH-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'out',
            ],
            [
                'di_title' => 'Bon de Retour Fournisseur',
                'di_model' => 'ReturnPurchase',
                'di_domain' => 'purchases',
                'template' => 'RTR-ACH-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'out',
            ],
         

              // ── Stock (missing) ───────────────────────────────────────────
          
             [
                'di_title' => 'Bon de Transfert',
                'di_model' => 'StockTransfer',
                'di_domain' => 'stock',
                'template' => 'BTR-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'both',
            ],
           
            [
                'di_title' => "Bon d'Ajustement",
                'di_model' => 'StockAdjustmentNote',
                'di_domain' => 'stock',
                'template' => 'ADJ-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'both',
            ],
               [
                'di_title' => 'Bon Entrée en Stock',
                'di_model' => 'StockEntry',
                'di_domain' => 'stock',
                'template' => 'INV-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'in',
            ],
            [
                'di_title' => 'Bon de Sortie de Stock',
                'di_model' => 'StockExit',
                'di_domain' => 'stock',
                'template' => 'BDS-{YYYY}-{MM}-{NNNN}',
                'nextTrick' => 1,
                'status' => true,
                'operatorSens' => 'out',
            ],
    ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_incrementors');
    }
};
