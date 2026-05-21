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
        $admin   = User::where('email', 'like', 'admin@%')->first();
        $cashier = User::where('email', 'like', 'cashier@%')->first();
        $mainWh  = Warehouse::first();

        $incInvoice  = DocumentIncrementor::where('di_model', 'InvoiceSale')->first();
        $incBC       = DocumentIncrementor::where('di_model', 'PurchaseOrder')->first();
        $incDevis    = DocumentIncrementor::where('di_model', 'QuoteSale')->first();
        $incFacAchat = DocumentIncrementor::where('di_model', 'InvoicePurchase')->first();

        $customer1 = ThirdPartner::where('tp_Ice_Number', '001234567890123')->first();
        $customer2 = ThirdPartner::where('tp_Ice_Number', '002345678901234')->first();
        $supplier1 = ThirdPartner::where('tp_Ice_Number', '004567890123456')->first();

        // Pick the first 6 products regardless of SKU
        $allProducts = Product::take(6)->get();
        $prod1 = $allProducts[0] ?? null;
        $prod2 = $allProducts[1] ?? null;
        $prod3 = $allProducts[2] ?? null;
        $prod4 = $allProducts[3] ?? null;
        $prod5 = $allProducts[4] ?? null;
        $prod6 = $allProducts[5] ?? null;

        // Abort early if required dependencies are missing
        if (! $admin || ! $cashier || ! $mainWh || ! $incInvoice || ! $incBC || ! $incDevis
            || ! $customer1 || ! $customer2 || ! $supplier1
            || ! $prod1 || ! $prod2 || ! $prod3 || ! $prod4 || ! $prod5 || ! $prod6
        ) {
            $this->command->warn('DocumentSeeder: skipped — one or more required records not found.');
            return;
        }

        // ── 1. PAID INVOICE ───────────────────────────────────────
        DB::transaction(function () use (
            $incInvoice, $customer1, $admin, $mainWh,
            $prod1, $prod2, $prod3
        ) {
            if (DocumentHeader::where('reference', 'FAC-2025-0001')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incInvoice->id,
                'reference'               => 'FAC-2025-0001',
                'document_type'           => 'InvoiceSale',
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
                    'product_id'       => $prod1->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $prod1->p_title,
                    'reference'        => $prod1->p_sku ?? $prod1->p_code,
                    'quantity'         => 2,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod1->p_salePrice,
                    'discount_percent' => 5,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $prod2->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $prod2->p_title,
                    'reference'        => $prod2->p_sku ?? $prod2->p_code,
                    'quantity'         => 1,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod2->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $prod3->id,
                    'sort_order'       => 3,
                    'line_type'        => 'product',
                    'designation'      => $prod3->p_title,
                    'reference'        => $prod3->p_sku ?? $prod3->p_code,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod3->p_salePrice,
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
            $prod4, $prod5, $prod6
        ) {
            if (DocumentHeader::where('reference', 'FAC-2025-0002')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incInvoice->id,
                'reference'               => 'FAC-2025-0002',
                'document_type'           => 'InvoiceSale',
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
                    'product_id'   => $prod4->id,
                    'sort_order'   => 1,
                    'line_type'    => 'product',
                    'designation'  => $prod4->p_title,
                    'reference'    => $prod4->p_sku ?? $prod4->p_code,
                    'quantity'     => 3,
                    'unit'         => 'pièce',
                    'unit_price'   => $prod4->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'  => 20,
                    'status'       => 'active',
                ],
                [
                    'product_id'   => $prod5->id,
                    'sort_order'   => 2,
                    'line_type'    => 'product',
                    'designation'  => $prod5->p_title,
                    'reference'    => $prod5->p_sku ?? $prod5->p_code,
                    'quantity'     => 3,
                    'unit'         => 'pièce',
                    'unit_price'   => $prod5->p_salePrice,
                    'discount_percent' => 0,
                    'tax_percent'  => 20,
                    'status'       => 'active',
                ],
                [
                    'product_id'   => $prod6->id,
                    'sort_order'   => 3,
                    'line_type'    => 'product',
                    'designation'  => $prod6->p_title,
                    'reference'    => $prod6->p_sku ?? $prod6->p_code,
                    'quantity'     => 10,
                    'unit'         => 'pièce',
                    'unit_price'   => $prod6->p_salePrice,
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
            $incDevis, $customer1, $admin, $mainWh, $prod2, $prod5
        ) {
            if (DocumentHeader::where('reference', 'DEV-2025-0001')->exists()) return;

            $header = DocumentHeader::create([
                'document_incrementor_id' => $incDevis->id,
                'reference'               => 'DEV-2025-0001',
                'document_type'           => 'QuoteSale',
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
                    'product_id'       => $prod2->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $prod2->p_title,
                    'reference'        => $prod2->p_sku ?? $prod2->p_code,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod2->p_salePrice,
                    'discount_percent' => 8,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $prod5->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $prod5->p_title,
                    'reference'        => $prod5->p_sku ?? $prod5->p_code,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod5->p_salePrice,
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
            $incBC, $supplier1, $admin, $mainWh, $prod1, $prod3
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
                    'product_id'       => $prod1->id,
                    'sort_order'       => 1,
                    'line_type'        => 'product',
                    'designation'      => $prod1->p_title,
                    'reference'        => $prod1->p_sku ?? $prod1->p_code,
                    'quantity'         => 10,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod1->p_purchasePrice,
                    'discount_percent' => 3,
                    'tax_percent'      => 20,
                    'status'           => 'active',
                ],
                [
                    'product_id'       => $prod3->id,
                    'sort_order'       => 2,
                    'line_type'        => 'product',
                    'designation'      => $prod3->p_title,
                    'reference'        => $prod3->p_sku ?? $prod3->p_code,
                    'quantity'         => 5,
                    'unit'             => 'pièce',
                    'unit_price'       => $prod3->p_purchasePrice,
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

        // ── 5. PURCHASE INVOICE (InvoicePurchase) ─────────────────
        if ($incFacAchat) {
            DB::transaction(function () use (
                $incFacAchat, $supplier1, $admin, $mainWh, $prod1, $prod2, $prod6
            ) {
                if (DocumentHeader::where('reference', 'FA-2025-0001')->exists()) return;

                $header = DocumentHeader::create([
                    'document_incrementor_id' => $incFacAchat->id,
                    'reference'               => 'FA-2025-0001',
                    'document_type'           => 'InvoicePurchase',
                    'document_title'          => 'Facture Achat',
                    'thirdPartner_id'         => $supplier1->id,
                    'company_role'            => 'supplier',
                    'user_id'                 => $admin->id,
                    'warehouse_id'            => $mainWh->id,
                    'status'                  => 'confirmed',
                    'issued_at'               => now()->subDays(7),
                    'due_at'                  => now()->addDays(30),
                    'notes'                   => 'Facture fournisseur Delta Import',
                ]);

                $lignes = [
                    [
                        'product_id'       => $prod1->id,
                        'sort_order'       => 1,
                        'line_type'        => 'product',
                        'designation'      => $prod1->p_title,
                        'reference'        => $prod1->p_sku ?? $prod1->p_code,
                        'quantity'         => 20,
                        'unit'             => 'pièce',
                        'unit_price'       => $prod1->p_purchasePrice,
                        'discount_percent' => 5,
                        'tax_percent'      => 20,
                        'status'           => 'active',
                    ],
                    [
                        'product_id'       => $prod2->id,
                        'sort_order'       => 2,
                        'line_type'        => 'product',
                        'designation'      => $prod2->p_title,
                        'reference'        => $prod2->p_sku ?? $prod2->p_code,
                        'quantity'         => 15,
                        'unit'             => 'pièce',
                        'unit_price'       => $prod2->p_purchasePrice,
                        'discount_percent' => 0,
                        'tax_percent'      => 20,
                        'status'           => 'active',
                    ],
                    [
                        'product_id'       => $prod6->id,
                        'sort_order'       => 3,
                        'line_type'        => 'product',
                        'designation'      => $prod6->p_title,
                        'reference'        => $prod6->p_sku ?? $prod6->p_code,
                        'quantity'         => 50,
                        'unit'             => 'pièce',
                        'unit_price'       => $prod6->p_purchasePrice,
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

                $incFacAchat->increment('nextTrick');
            });
        }
    }
}
