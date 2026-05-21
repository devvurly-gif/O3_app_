<?php
/**
 * Add more transactions for Jan–Apr 2026 to demo tenant.
 * Run: php seed-demo-extra.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;

$tenant = Tenant::find('demo');
if (!$tenant) { echo "❌ No demo tenant.\n"; exit(1); }

echo "🚀 Adding extra transactions (Jan–Apr 2026)...\n\n";

$tenant->run(function () {

    $warehouse = \App\Models\Warehouse::first();
    $admin = \App\Models\User::first();
    $products = \App\Models\Product::all()->values();
    $customerIds = \App\Models\ThirdPartner::where('tp_Role', 'customer')->pluck('id')->toArray();
    $supplierIds = \App\Models\ThirdPartner::where('tp_Role', 'supplier')->pluck('id')->toArray();
    $incrementorService = app(\App\Services\DocumentIncrementorService::class);

    if (!$warehouse || !$admin || $products->isEmpty() || empty($customerIds)) {
        echo "❌ Missing base data.\n"; return;
    }

    function makeDoc($svc, $type, $tpId, $whId, $userId, $lines, $status, $date, $due = null, $method = null, $paid = null) {
        $inc = \App\Models\DocumentIncrementor::where('di_model', $type)->first();
        if (!$inc) return null;
        $r = $svc->reserveNext($inc);
        $h = \App\Models\DocumentHeader::create([
            'document_incrementor_id' => $inc->id,
            'reference' => $r['reference'],
            'document_type' => $type,
            'document_title' => $type,
            'thirdPartner_id' => $tpId,
            'warehouse_id' => $whId,
            'user_id' => $userId,
            'status' => 'draft',
            'issued_at' => $date,
            'due_at' => $due,
        ]);
        $svc->confirmNext($inc, $r['token']);

        $tHt = 0; $tDisc = 0; $tTax = 0; $tTtc = 0;
        foreach ($lines as $i => $l) {
            $lHt = $l['qty'] * $l['price'] * (1 - ($l['disc'] ?? 0) / 100);
            $lTax = $lHt * ($l['tax'] ?? 20) / 100;
            $lTtc = $lHt + $lTax;
            \App\Models\DocumentLigne::create([
                'document_header_id' => $h->id, 'product_id' => $l['pid'], 'sort_order' => $i+1,
                'line_type' => 'product', 'designation' => $l['name'], 'reference' => $l['ref'] ?? '',
                'quantity' => $l['qty'], 'unit' => 'pcs', 'unit_price' => $l['price'],
                'discount_percent' => $l['disc'] ?? 0, 'tax_percent' => $l['tax'] ?? 20,
                'total_ligne_ht' => round($lHt,2), 'total_tax' => round($lTax,2), 'total_ttc' => round($lTtc,2),
            ]);
            $tHt += $lHt; $tDisc += ($l['qty']*$l['price']) - $lHt; $tTax += $lTax; $tTtc += $lTtc;
        }

        $ap = $paid ?? 0;
        \App\Models\DocumentFooter::create([
            'document_header_id' => $h->id,
            'total_ht' => round($tHt,2), 'total_discount' => round($tDisc,2),
            'total_tax' => round($tTax,2), 'total_ttc' => round($tTtc,2),
            'amount_paid' => round($ap,2), 'amount_due' => round($tTtc - $ap,2),
            'payment_method' => $method,
        ]);

        if ($status !== 'draft') $h->update(['status' => $status]);

        if ($paid && $paid > 0 && $method && $method !== 'credit') {
            \App\Models\Payment::create([
                'document_header_id' => $h->id, 'amount' => round($paid,2),
                'method' => $method, 'paid_at' => $date, 'user_id' => $userId,
            ]);
        }
        return $h;
    }

    function pickLines($products, $count) {
        $indices = (array) array_rand($products->toArray(), min($count, $products->count()));
        $lines = [];
        foreach ($indices as $i) {
            $p = $products[$i];
            $disc = rand(0,3) === 0 ? rand(5,15) : 0;
            $lines[] = [
                'pid' => $p->id, 'name' => $p->p_title, 'ref' => $p->p_code ?? '',
                'qty' => rand(1, 6), 'price' => $p->p_salePrice, 'disc' => $disc, 'tax' => 20,
            ];
        }
        return $lines;
    }

    function pickPurchaseLines($products, $count) {
        $indices = (array) array_rand($products->toArray(), min($count, $products->count()));
        $lines = [];
        foreach ($indices as $i) {
            $p = $products[$i];
            $lines[] = [
                'pid' => $p->id, 'name' => $p->p_title, 'ref' => $p->p_code ?? '',
                'qty' => rand(5, 25), 'price' => $p->p_purchasePrice, 'disc' => 0, 'tax' => 20,
            ];
        }
        return $lines;
    }

    $methods = ['cash', 'bank_transfer', 'cheque', 'effet'];
    $docCount = 0;

    $months = [
        '2026-01' => ['invoices' => 12, 'bl' => 8, 'devis' => 5, 'bc' => 3, 'po' => 3],
        '2026-02' => ['invoices' => 14, 'bl' => 9, 'devis' => 6, 'bc' => 4, 'po' => 3],
        '2026-03' => ['invoices' => 16, 'bl' => 10, 'devis' => 7, 'bc' => 4, 'po' => 4],
        '2026-04' => ['invoices' => 18, 'bl' => 12, 'devis' => 8, 'bc' => 5, 'po' => 4],
    ];

    foreach ($months as $month => $counts) {
        echo "📅 $month\n";
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)substr($month,5), (int)substr($month,0,4));

        // ── Invoices ──
        for ($i = 0; $i < $counts['invoices']; $i++) {
            $day = str_pad(rand(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
            $date = "$month-$day";
            $lines = pickLines($products, rand(1,5));
            $ttc = array_sum(array_map(fn($l) => $l['qty']*$l['price']*(1-$l['disc']/100)*1.2, $lines));

            $roll = rand(1,10);
            if ($roll <= 6) { $st='paid'; $m=$methods[rand(0,3)]; $p=round($ttc,2); }
            elseif ($roll <= 8) { $st='partial'; $m='bank_transfer'; $p=round($ttc*rand(25,65)/100,2); }
            else { $st='confirmed'; $m=null; $p=0; }

            $due = date('Y-m-d', strtotime("$date +30 days"));
            $d = makeDoc($incrementorService, 'InvoiceSale', $customerIds[array_rand($customerIds)], $warehouse->id, $admin->id, $lines, $st, $date, $due, $m, $p);
            if ($d) $docCount++;
        }

        // ── Delivery Notes ──
        for ($i = 0; $i < $counts['bl']; $i++) {
            $day = str_pad(rand(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
            $date = "$month-$day";
            $lines = pickLines($products, rand(1,4));
            $ttc = array_sum(array_map(fn($l) => $l['qty']*$l['price']*(1-$l['disc']/100)*1.2, $lines));

            $paid = rand(0,1);
            $st = $paid ? 'paid' : 'confirmed';
            $m = $paid ? $methods[rand(0,2)] : null;
            $p = $paid ? round($ttc,2) : 0;

            $d = makeDoc($incrementorService, 'DeliveryNote', $customerIds[array_rand($customerIds)], $warehouse->id, $admin->id, $lines, $st, $date, null, $m, $p);
            if ($d) $docCount++;
        }

        // ── Quotes ──
        for ($i = 0; $i < $counts['devis']; $i++) {
            $day = str_pad(rand(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
            $date = "$month-$day";
            $lines = pickLines($products, rand(2,6));
            $st = ['draft','confirmed','confirmed'][rand(0,2)];
            $d = makeDoc($incrementorService, 'QuoteSale', $customerIds[array_rand($customerIds)], $warehouse->id, $admin->id, $lines, $st, $date);
            if ($d) $docCount++;
        }

        // ── Customer Orders ──
        for ($i = 0; $i < $counts['bc']; $i++) {
            $day = str_pad(rand(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
            $date = "$month-$day";
            $lines = pickLines($products, rand(2,4));
            $d = makeDoc($incrementorService, 'CustomerOrder', $customerIds[array_rand($customerIds)], $warehouse->id, $admin->id, $lines, 'confirmed', $date);
            if ($d) $docCount++;
        }

        // ── Purchase Orders ──
        if (count($supplierIds)) {
            for ($i = 0; $i < $counts['po']; $i++) {
                $day = str_pad(rand(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
                $date = "$month-$day";
                $lines = pickPurchaseLines($products, rand(3,6));
                $d = makeDoc($incrementorService, 'PurchaseOrder', $supplierIds[array_rand($supplierIds)], $warehouse->id, $admin->id, $lines, 'confirmed', $date);
                if ($d) $docCount++;
            }
        }
    }

    echo "\n✅ Extra documents created: $docCount\n";
});

echo "🎉 Done!\n";
