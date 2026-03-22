<?php

namespace Database\Seeders;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = User::where('email', 'admin@o2app.ma')->first();
        $cashier = User::where('email', 'cashier@o2app.ma')->first();
        $mainWh  = Warehouse::first();

        $incInvoice  = DocumentIncrementor::where('di_model', 'InvoiceSale')->first();
        $incBC       = DocumentIncrementor::where('di_model', 'PurchaseOrder')->first();
        $incDevis    = DocumentIncrementor::where('di_model', 'QuoteSale')->first();

        $customer1 = ThirdPartner::where('tp_Ice_Number', '001234567890123')->first();
        $customer2 = ThirdPartner::where('tp_Ice_Number', '002345678901234')->first();
        $supplier1 = ThirdPartner::where('tp_Ice_Number', '004567890123456')->first();

        $laptop   = Product::where('p_sku', 'HP-PB450-I5')->first();
        $dellLap  = Product::where('p_sku', 'DELL-LAT5530-I7')->first();
        $printer  = Product::where('p_sku', 'CAN-PIXMA-G3420')->first();
        $ink      = Product::where('p_sku', 'CAN-INK-PG545')->first();
        $paper    = Product::where('p_sku', 'PAP-A4-80G')->first();
        $mouse    = Product::where('p_sku', 'LOG-MXM3')->first();

        // Abort early if required dependencies are missing
        if (! $admin || ! $cashier || ! $mainWh || ! $incInvoice || ! $incBC || ! $incDevis
            || ! $customer1 || ! $customer2 || ! $supplier1
            || ! $laptop || ! $dellLap || ! $printer || ! $ink || ! $paper || ! $mouse
        ) {
            $this->command->warn('DocumentSeeder: skipped — one or more required records not found.');
            return;
        }

        // ── 1. PAID INVOICE ───────────────────────────────────────
        DB::transaction(function () use (
            $incInvoice, $customer1, $admin, $mainWh,
            $laptop, $printer, $ink
        ) {
            if (DocumentHeader::where('reference', 'FAC-2025-0001')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incInvoice->id,
                'reference'               => 'FAC-2025-0001',
                'document_type'           => 'Invoice',
                'document_title'          => 'Factures',
                'thirdPartner_id'         => $customer1->id,
                'company_role'            => 'customer',
                'user_id'                 => $admin->id,
                'warehouse_id'            => $mainWh->id,
                'status'                  => 'paid',
                'issued_at'               => now()->subDays(30),
                'due_at'                  => now()->subDays(0),
                'notes'                   => 'Commande client Alpha SARL',
            ]);

            $lignes = [
                [
                    'product_id'       => $laptop->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $laptop->p_title,
                    'reference'        => $laptop->p_sku,
                    'quantity'         => 2,
                    'unit'             => 'pièce',
                    'unit_price'       => $laptop->p_salePrice,
                    'discount_percent' => 5,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $printer->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $printer->p_title,
                    'reference'        => $printer->p_sku,
                    'quantity'         => 1,
                    'unit'             => 'pièce',
                    'unit_price'       => $printer->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $ink->id,
                    'sort_order'       => 3,
                    'line_type'        => 'product',
                    'designation'      => $ink->p_title,
                    'reference'        => $ink->p_sku,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $ink->p_salePrice,
                    'discount_percent' => 10,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
            ];

            $totalHt = 0; $totalDiscount = 0; $totalTax = 0; $totalTtc = 0;

            foreach ($lignes as $ligneData) {
                $base          = $ligneData['quantity'] * $ligneData['unit_price'];
                $discountAmt   = $base * ($ligneData['discount_percent'] / 100);
                $ht            = $base - $discountAmt;
                $tax           = $ht * ($ligneData['tax_percent'] / 100);
                $ttc           = $ht + $tax;

                $ligneData['total_ligne_ht'] = $ht;
                $ligneData['total_tax']      = $tax;
                $ligneData['total_ttc']      = $ttc;
                $ligneData['document_header_id'] = $header->id;

                DocumentLigne::create($ligneData);

                $totalHt       += $ht;
                $totalDiscount += $discountAmt;
                $totalTax      += $tax;
                $totalTtc      += $ttc;
            }

            DocumentFooter::create([
                'document_header_id' => $header->id,
                'total_ht'           => $totalHt,
                'total_discount'     => $totalDiscount,
                'total_tax'          => $totalTax,
                'total_ttc'          => $totalTtc,
                'amount_paid'        => $totalTtc,
                'amount_due'         => 0,
                'payment_method'     => 'bank_transfer',
                'payment_date'       => now()->subDays(5),
                'total_in_words'     => 'Voir montant ci-dessus',
                'is_signed'          => true,
                'is_printed'         => true,
                'is_sent'            => true,
                'sent_via'           => json_encode(['email' => $customer1->tp_email]),
                'legal_mentions'     => 'Merci pour votre confiance.',
            ]);

            Payment::create([
                'document_header_id' => $header->id,
                'amount'             => $totalTtc,
                'method'             => 'bank_transfer',
                'paid_at'            => now()->subDays(5),
                'reference'          => 'VIR-2025-001',
                'user_id'            => $admin->id,
                'notes'              => 'Virement reçu',
            ]);

            $incInvoice->increment('nextTrick');
        });

        // ── 2. PARTIAL INVOICE ────────────────────────────────────
        DB::transaction(function () use (
            $incInvoice, $customer2, $cashier, $mainWh,
            $dellLap, $mouse, $paper
        ) {
            if (DocumentHeader::where('reference', 'FAC-2025-0002')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incInvoice->id,
                'reference'               => 'FAC-2025-0002',
                'document_type'           => 'Invoice',
                'document_title'          => 'Factures',
                'thirdPartner_id'         => $customer2->id,
                'company_role'            => 'customer',
                'user_id'                 => $cashier->id,
                'warehouse_id'            => $mainWh->id,
                'status'                  => 'partial',
                'issued_at'               => now()->subDays(15),
                'due_at'                  => now()->addDays(15),
            ]);

            $lignes = [
                [
                    'product_id'   => $dellLap->id,
                    'sort_order'   => 1,
                    'line_type'    => 'product',
                    'designation'  => $dellLap->p_title,
                    'reference'    => $dellLap->p_sku,
                    'quantity'     => 3,
                    'unit'         => 'pièce',
                    'unit_price'   => $dellLap->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'  => 20,
                    'status'       => 'active',
                ],
                [
                    'product_id'   => $mouse->id,
                    'sort_order'   => 2,
                    'line_type'    => 'product',
                    'designation'  => $mouse->p_title,
                    'reference'    => $mouse->p_sku,
                    'quantity'     => 3,
                    'unit'         => 'pièce',
                    'unit_price'   => $mouse->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'  => 20,
                    'status'       => 'active',
                ],
                [
                    'product_id'   => $paper->id,
                    'sort_order'   => 3,
                    'line_type'    => 'product',
                    'designation'  => $paper->p_title,
                    'reference'    => $paper->p_sku,
                    'quantity'     => 10,
                    'unit'         => 'ramette',
                    'unit_price'   => $paper->p_salePrice,
                    'discount_percent' => 5,
                    'tax_percent'  => 20,
                    'status'       => 'active',
                ],
            ];

            $totalHt = 0; $totalDiscount = 0; $totalTax = 0; $totalTtc = 0;

            foreach ($lignes as $ligneData) {
                $base        = $ligneData['quantity'] * $ligneData['unit_price'];
                $discountAmt = $base * ($ligneData['discount_percent'] / 100);
                $ht          = $base - $discountAmt;
                $tax         = $ht * ($ligneData['tax_percent'] / 100);
                $ttc         = $ht + $tax;

                $ligneData['total_ligne_ht']      = $ht;
                $ligneData['total_tax']           = $tax;
                $ligneData['total_ttc']           = $ttc;
                $ligneData['document_header_id']  = $header->id;

                DocumentLigne::create($ligneData);
                $totalHt       += $ht;
                $totalDiscount += $discountAmt;
                $totalTax      += $tax;
                $totalTtc      += $ttc;
            }

            $amountPaid = round($totalTtc * 0.5, 2); // 50% paid

            DocumentFooter::create([
                'document_header_id' => $header->id,
                'total_ht'           => $totalHt,
                'total_discount'     => $totalDiscount,
                'total_tax'          => $totalTax,
                'total_ttc'          => $totalTtc,
                'amount_paid'        => $amountPaid,
                'amount_due'         => $totalTtc - $amountPaid,
                'payment_method'     => 'cheque',
                'total_in_words'     => 'Voir montant ci-dessus',
                'is_printed'         => true,
                'is_sent'            => false,
            ]);

            Payment::create([
                'document_header_id' => $header->id,
                'amount'             => $amountPaid,
                'method'             => 'cheque',
                'paid_at'            => now()->subDays(10),
                'reference'          => 'CHQ-2025-045',
                'user_id'            => $cashier->id,
                'notes'              => 'Acompte 50%',
            ]);

            $incInvoice->increment('nextTrick');
        });

        // ── 3. DRAFT QUOTE ────────────────────────────────────────
        DB::transaction(function () use (
            $incDevis, $customer1, $admin, $mainWh, $dellLap, $mouse
        ) {
            if (DocumentHeader::where('reference', 'DEV-2025-0001')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incDevis->id,
                'reference'               => 'DEV-2025-0001',
                'document_type'           => 'Quote',
                'document_title'          => 'Devis',
                'thirdPartner_id'         => $customer1->id,
                'company_role'            => 'customer',
                'user_id'                 => $admin->id,
                'warehouse_id'            => $mainWh->id,
                'status'                  => 'draft',
                'issued_at'               => now()->subDays(5),
                'due_at'                  => now()->addDays(25),
                'notes'                   => 'Devis à valider par le client',
            ]);

            $lignes = [
                [
                    'product_id'       => $dellLap->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $dellLap->p_title,
                    'reference'        => $dellLap->p_sku,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $dellLap->p_salePrice,
                    'discount_percent' => 8,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $mouse->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $mouse->p_title,
                    'reference'        => $mouse->p_sku,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $mouse->p_salePrice,
                    'discount_percent' => 8,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
            ];

            $totalHt = 0; $totalDiscount = 0; $totalTax = 0; $totalTtc = 0;

            foreach ($lignes as $ligneData) {
                $base        = $ligneData['quantity'] * $ligneData['unit_price'];
                $discountAmt = $base * ($ligneData['discount_percent'] / 100);
                $ht          = $base - $discountAmt;
                $tax         = $ht * ($ligneData['tax_percent'] / 100);
                $ttc         = $ht + $tax;

                $ligneData['total_ligne_ht']     = $ht;
                $ligneData['total_tax']          = $tax;
                $ligneData['total_ttc']          = $ttc;
                $ligneData['document_header_id'] = $header->id;

                DocumentLigne::create($ligneData);
                $totalHt       += $ht;
                $totalDiscount += $discountAmt;
                $totalTax      += $tax;
                $totalTtc      += $ttc;
            }

            DocumentFooter::create([
                'document_header_id' => $header->id,
                'total_ht'           => $totalHt,
                'total_discount'     => $totalDiscount,
                'total_tax'          => $totalTax,
                'total_ttc'          => $totalTtc,
                'amount_paid'        => 0,
                'amount_due'         => $totalTtc,
            ]);

            $incDevis->increment('nextTrick');
        });

        // ── 4. PURCHASE ORDER ─────────────────────────────────────
        DB::transaction(function () use (
            $incBC, $supplier1, $admin, $mainWh, $laptop, $dellLap
        ) {
            if (DocumentHeader::where('reference', 'BC-2025-0001')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incBC->id,
                'reference'               => 'BC-2025-0001',
                'document_type'           => 'PurchaseOrder',
                'document_title'          => 'Bons de Commande',
                'thirdPartner_id'         => $supplier1->id,
                'company_role'            => 'supplier',
                'user_id'                 => $admin->id,
                'warehouse_id'            => $mainWh->id,
                'status'                  => 'confirmed',
                'issued_at'               => now()->subDays(10),
                'due_at'                  => now()->addDays(20),
                'notes'                   => 'Commande réapprovisionnement',
            ]);

            $lignes = [
                [
                    'product_id'       => $laptop->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $laptop->p_title,
                    'reference'        => $laptop->p_sku,
                    'quantity'         => 10,
                    'unit'             => 'pièce',
                    'unit_price'       => $laptop->p_purchasePrice,
                    'discount_percent' => 3,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $dellLap->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $dellLap->p_title,
                    'reference'        => $dellLap->p_sku,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $dellLap->p_purchasePrice,
                    'discount_percent' => 0,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
            ];

            $totalHt = 0; $totalDiscount = 0; $totalTax = 0; $totalTtc = 0;

            foreach ($lignes as $ligneData) {
                $base        = $ligneData['quantity'] * $ligneData['unit_price'];
                $discountAmt = $base * ($ligneData['discount_percent'] / 100);
                $ht          = $base - $discountAmt;
                $tax         = $ht * ($ligneData['tax_percent'] / 100);
                $ttc         = $ht + $tax;

                $ligneData['total_ligne_ht']     = $ht;
                $ligneData['total_tax']          = $tax;
                $ligneData['total_ttc']          = $ttc;
                $ligneData['document_header_id'] = $header->id;

                DocumentLigne::create($ligneData);
                $totalHt       += $ht;
                $totalDiscount += $discountAmt;
                $totalTax      += $tax;
                $totalTtc      += $ttc;
            }

            DocumentFooter::create([
                'document_header_id' => $header->id,
                'total_ht'           => $totalHt,
                'total_discount'     => $totalDiscount,
                'total_tax'          => $totalTax,
                'total_ttc'          => $totalTtc,
                'amount_paid'        => 0,
                'amount_due'         => $totalTtc,
                'payment_method'     => 'bank_transfer',
            ]);

            $incBC->increment('nextTrick');
        });
    }
}
