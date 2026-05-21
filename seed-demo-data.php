<?php
/**
 * Seed demo tenant with realistic products, customers, and transactions.
 * Run: php seed-demo-data.php
 * Then delete this file.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;

$tenant = Tenant::find('demo');
if (!$tenant) {
    echo "❌ Demo tenant not found. Run create-demo-tenant.php first.\n";
    exit(1);
}

echo "🚀 Seeding demo tenant with products and transactions...\n\n";

$tenant->run(function () {

    $warehouse = \App\Models\Warehouse::first();
    $priceList = \App\Models\PriceList::where('is_default', true)->first();
    $admin = \App\Models\User::first();
    $brand = \App\Models\Brand::first();

    if (!$warehouse || !$admin) {
        echo "❌ Missing warehouse or admin user.\n";
        return;
    }

    // ── Categories ──────────────────────────────────────
    $categories = \App\Models\Category::pluck('id', 'ctg_title')->toArray();
    echo "✅ Categories: " . count($categories) . "\n";

    // ── Customers ───────────────────────────────────────
    $customers = [
        ['tp_title' => 'Hicham Bahbah', 'tp_phone' => '0661234567', 'tp_email' => 'hicham@email.com', 'tp_city' => 'Casablanca', 'tp_address' => '12 Rue Hassan II, Maarif'],
        ['tp_title' => 'Fatima Zahra Alaoui', 'tp_phone' => '0672345678', 'tp_email' => 'fatima@email.com', 'tp_city' => 'Rabat', 'tp_address' => '45 Av Mohammed V, Agdal'],
        ['tp_title' => 'Youssef Tazi', 'tp_phone' => '0653456789', 'tp_email' => 'youssef@email.com', 'tp_city' => 'Marrakech', 'tp_address' => '8 Bd Zerktouni, Guéliz'],
        ['tp_title' => 'Salma Bennani', 'tp_phone' => '0694567890', 'tp_email' => 'salma@email.com', 'tp_city' => 'Tanger', 'tp_address' => '23 Rue de Fès, Centre'],
        ['tp_title' => 'Omar Idrissi', 'tp_phone' => '0615678901', 'tp_email' => 'omar@email.com', 'tp_city' => 'Fès', 'tp_address' => '67 Av des FAR'],
        ['tp_title' => 'Nadia El Amrani', 'tp_phone' => '0676789012', 'tp_email' => 'nadia@email.com', 'tp_city' => 'Agadir', 'tp_address' => '15 Bd Hassan II'],
        ['tp_title' => 'Karim Moussaoui', 'tp_phone' => '0667890123', 'tp_email' => 'karim@email.com', 'tp_city' => 'Oujda', 'tp_address' => '3 Rue Al Andalous'],
        ['tp_title' => 'Boutique Atlas SARL', 'tp_phone' => '0528901234', 'tp_email' => 'contact@atlas.ma', 'tp_city' => 'Casablanca', 'tp_address' => '120 Bd Anfa', 'tp_Ice_Number' => '001234567000089'],
        ['tp_title' => 'TechStore Maroc', 'tp_phone' => '0539012345', 'tp_email' => 'info@techstore.ma', 'tp_city' => 'Rabat', 'tp_address' => '56 Av Allal Ben Abdellah', 'tp_Ice_Number' => '002345678000012'],
        ['tp_title' => 'Digital Shop', 'tp_phone' => '0540123456', 'tp_email' => 'contact@digitalshop.ma', 'tp_city' => 'Marrakech', 'tp_address' => '90 Rue de la Liberté'],
    ];

    $customerIds = [];
    foreach ($customers as $c) {
        $existing = \App\Models\ThirdPartner::where('tp_title', $c['tp_title'])->first();
        if ($existing) {
            $customerIds[] = $existing->id;
            continue;
        }
        $tp = \App\Models\ThirdPartner::create(array_merge($c, [
            'tp_Role' => 'customer',
            'tp_status' => true,
            'price_list_id' => $priceList?->id,
        ]));
        $customerIds[] = $tp->id;
    }
    echo "✅ Customers: " . count($customerIds) . "\n";

    // ── Suppliers ────────────────────────────────────────
    $suppliers = [
        ['tp_title' => 'Fournisseur Général SA', 'tp_phone' => '0522111222', 'tp_email' => 'contact@fg.ma', 'tp_city' => 'Casablanca', 'tp_address' => '200 Zone Industrielle Sidi Bernoussi', 'tp_Ice_Number' => '003456789000034'],
        ['tp_title' => 'Import Tech SARL', 'tp_phone' => '0522333444', 'tp_email' => 'info@importtech.ma', 'tp_city' => 'Casablanca', 'tp_address' => '45 Bd Moulay Ismail'],
        ['tp_title' => 'MegaDistrib', 'tp_phone' => '0537555666', 'tp_email' => 'commande@megadistrib.ma', 'tp_city' => 'Kénitra', 'tp_address' => '12 Zone Franche'],
    ];

    foreach ($suppliers as $s) {
        $existing = \App\Models\ThirdPartner::where('tp_title', $s['tp_title'])->first();
        if ($existing) continue;
        \App\Models\ThirdPartner::create(array_merge($s, [
            'tp_Role' => 'supplier',
            'tp_status' => true,
        ]));
    }
    echo "✅ Suppliers: " . count($suppliers) . "\n";

    // ── Products ────────────────────────────────────────
    $products = [
        // Laptops
        ['p_title' => 'MacBook Air M2', 'cat' => 'Laptops', 'p_purchasePrice' => 9500, 'p_salePrice' => 12990, 'p_taxRate' => 20],
        ['p_title' => 'MacBook Pro 14" M3', 'cat' => 'Laptops', 'p_purchasePrice' => 15000, 'p_salePrice' => 19990, 'p_taxRate' => 20],
        ['p_title' => 'Lenovo ThinkPad X1 Carbon', 'cat' => 'Laptops', 'p_purchasePrice' => 8000, 'p_salePrice' => 11500, 'p_taxRate' => 20],
        ['p_title' => 'HP EliteBook 840 G10', 'cat' => 'Laptops', 'p_purchasePrice' => 7500, 'p_salePrice' => 10900, 'p_taxRate' => 20],
        ['p_title' => 'Dell XPS 15', 'cat' => 'Laptops', 'p_purchasePrice' => 10000, 'p_salePrice' => 13990, 'p_taxRate' => 20],
        ['p_title' => 'ASUS ZenBook 14', 'cat' => 'Laptops', 'p_purchasePrice' => 5500, 'p_salePrice' => 7990, 'p_taxRate' => 20],
        // Écrans
        ['p_title' => 'Samsung 27" 4K UHD', 'cat' => 'Écrans', 'p_purchasePrice' => 2500, 'p_salePrice' => 3490, 'p_taxRate' => 20],
        ['p_title' => 'LG UltraWide 34"', 'cat' => 'Écrans', 'p_purchasePrice' => 3500, 'p_salePrice' => 4990, 'p_taxRate' => 20],
        ['p_title' => 'Dell 24" FHD IPS', 'cat' => 'Écrans', 'p_purchasePrice' => 1500, 'p_salePrice' => 2190, 'p_taxRate' => 20],
        ['p_title' => 'BenQ 27" Design', 'cat' => 'Écrans', 'p_purchasePrice' => 4000, 'p_salePrice' => 5490, 'p_taxRate' => 20],
        // Accessoires
        ['p_title' => 'Clavier Logitech MX Keys', 'cat' => 'Accessoires', 'p_purchasePrice' => 600, 'p_salePrice' => 990, 'p_taxRate' => 20],
        ['p_title' => 'Souris Logitech MX Master 3S', 'cat' => 'Accessoires', 'p_purchasePrice' => 500, 'p_salePrice' => 850, 'p_taxRate' => 20],
        ['p_title' => 'Webcam Logitech C920', 'cat' => 'Accessoires', 'p_purchasePrice' => 400, 'p_salePrice' => 690, 'p_taxRate' => 20],
        ['p_title' => 'Casque Sony WH-1000XM5', 'cat' => 'Accessoires', 'p_purchasePrice' => 2200, 'p_salePrice' => 3290, 'p_taxRate' => 20],
        ['p_title' => 'Hub USB-C 7-en-1', 'cat' => 'Accessoires', 'p_purchasePrice' => 200, 'p_salePrice' => 390, 'p_taxRate' => 20],
        ['p_title' => 'Support Laptop Aluminium', 'cat' => 'Accessoires', 'p_purchasePrice' => 150, 'p_salePrice' => 290, 'p_taxRate' => 20],
        ['p_title' => 'Câble HDMI 2.1 3m', 'cat' => 'Accessoires', 'p_purchasePrice' => 50, 'p_salePrice' => 120, 'p_taxRate' => 20],
        ['p_title' => 'Tapis de Souris XXL', 'cat' => 'Accessoires', 'p_purchasePrice' => 80, 'p_salePrice' => 190, 'p_taxRate' => 20],
        // Téléphones
        ['p_title' => 'iPhone 15 Pro 256GB', 'cat' => 'Téléphones', 'p_purchasePrice' => 11000, 'p_salePrice' => 14990, 'p_taxRate' => 20],
        ['p_title' => 'Samsung Galaxy S24 Ultra', 'cat' => 'Téléphones', 'p_purchasePrice' => 10000, 'p_salePrice' => 13490, 'p_taxRate' => 20],
        ['p_title' => 'Xiaomi 14 Pro', 'cat' => 'Téléphones', 'p_purchasePrice' => 4500, 'p_salePrice' => 6490, 'p_taxRate' => 20],
        ['p_title' => 'Google Pixel 8 Pro', 'cat' => 'Téléphones', 'p_purchasePrice' => 7000, 'p_salePrice' => 9990, 'p_taxRate' => 20],
        ['p_title' => 'OnePlus 12', 'cat' => 'Téléphones', 'p_purchasePrice' => 5000, 'p_salePrice' => 7490, 'p_taxRate' => 20],
        // Imprimantes
        ['p_title' => 'HP LaserJet Pro M404dn', 'cat' => 'Imprimantes', 'p_purchasePrice' => 2000, 'p_salePrice' => 2990, 'p_taxRate' => 20],
        ['p_title' => 'Epson EcoTank L3250', 'cat' => 'Imprimantes', 'p_purchasePrice' => 1500, 'p_salePrice' => 2290, 'p_taxRate' => 20],
        ['p_title' => 'Canon PIXMA G3420', 'cat' => 'Imprimantes', 'p_purchasePrice' => 1200, 'p_salePrice' => 1890, 'p_taxRate' => 20],
        ['p_title' => 'Brother MFC-L2710DW', 'cat' => 'Imprimantes', 'p_purchasePrice' => 1800, 'p_salePrice' => 2690, 'p_taxRate' => 20],
        ['p_title' => 'Cartouche HP 305 Noir', 'cat' => 'Imprimantes', 'p_purchasePrice' => 80, 'p_salePrice' => 150, 'p_taxRate' => 20],
        ['p_title' => 'Cartouche HP 305 Couleur', 'cat' => 'Imprimantes', 'p_purchasePrice' => 100, 'p_salePrice' => 180, 'p_taxRate' => 20],
        ['p_title' => 'Ramette Papier A4 500f', 'cat' => 'Imprimantes', 'p_purchasePrice' => 30, 'p_salePrice' => 55, 'p_taxRate' => 20],
    ];

    $productIds = [];
    foreach ($products as $p) {
        $existing = \App\Models\Product::where('p_title', $p['p_title'])->first();
        if ($existing) {
            $productIds[] = $existing;
            continue;
        }
        $catId = $categories[$p['cat']] ?? null;
        $prod = \App\Models\Product::create([
            'p_title' => $p['p_title'],
            'p_purchasePrice' => $p['p_purchasePrice'],
            'p_salePrice' => $p['p_salePrice'],
            'p_taxRate' => $p['p_taxRate'],
            'p_unit' => 'pcs',
            'p_status' => true,
            'category_id' => $catId,
            'brand_id' => $brand?->id,
        ]);
        $productIds[] = $prod;
    }
    echo "✅ Products: " . count($productIds) . "\n";

    // ── Stock initial ───────────────────────────────────
    foreach ($productIds as $prod) {
        $whs = \App\Models\WarehouseHasStock::where('product_id', $prod->id)
            ->where('warehouse_id', $warehouse->id)->first();
        if (!$whs) {
            \App\Models\WarehouseHasStock::create([
                'product_id' => $prod->id,
                'warehouse_id' => $warehouse->id,
                'stockLevel' => rand(10, 100),
            ]);
        }
    }
    echo "✅ Stock initialized\n";

    // ── Price list items ────────────────────────────────
    if ($priceList) {
        foreach ($productIds as $prod) {
            $exists = \App\Models\PriceListItem::where('price_list_id', $priceList->id)
                ->where('product_id', $prod->id)->first();
            if (!$exists) {
                $ht = $prod->p_salePrice;
                $ttc = round($ht * (1 + $prod->p_taxRate / 100), 2);
                \App\Models\PriceListItem::create([
                    'price_list_id' => $priceList->id,
                    'product_id' => $prod->id,
                    'price_ht' => $ht,
                    'price_ttc' => $ttc,
                    'min_qty' => 1,
                ]);
            }
        }
        echo "✅ Price list items\n";
    }

    // ── Helper: create a document ───────────────────────
    $incrementorService = app(\App\Services\DocumentIncrementorService::class);

    function createDocument(
        $incrementorService,
        string $type,
        int $customerId,
        int $warehouseId,
        int $userId,
        array $lines,
        string $status,
        string $issuedAt,
        ?string $dueAt = null,
        ?string $paymentMethod = null,
        ?float $paymentAmount = null,
        ?int $parentId = null,
    ) {
        $incrementor = \App\Models\DocumentIncrementor::where('di_model', $type)->first();
        if (!$incrementor) {
            echo "  ⚠ No incrementor for $type\n";
            return null;
        }

        $reserved = $incrementorService->reserveNext($incrementor);

        $header = \App\Models\DocumentHeader::create([
            'document_incrementor_id' => $incrementor->id,
            'reference' => $reserved['reference'],
            'document_type' => $type,
            'document_title' => $type,
            'thirdPartner_id' => $customerId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'status' => 'draft',
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'parent_id' => $parentId,
        ]);

        $incrementorService->confirmNext($incrementor, $reserved['token']);

        $totalHt = 0;
        $totalDiscount = 0;
        $totalTax = 0;
        $totalTtc = 0;

        foreach ($lines as $i => $line) {
            $qty = $line['qty'];
            $price = $line['price'];
            $disc = $line['discount'] ?? 0;
            $tax = $line['tax'] ?? 20;

            $lineHt = $qty * $price * (1 - $disc / 100);
            $lineTax = $lineHt * $tax / 100;
            $lineTtc = $lineHt + $lineTax;

            \App\Models\DocumentLigne::create([
                'document_header_id' => $header->id,
                'product_id' => $line['product_id'],
                'sort_order' => $i + 1,
                'line_type' => 'product',
                'designation' => $line['designation'],
                'reference' => $line['reference'] ?? '',
                'quantity' => $qty,
                'unit' => 'pcs',
                'unit_price' => $price,
                'discount_percent' => $disc,
                'tax_percent' => $tax,
                'total_ligne_ht' => round($lineHt, 2),
                'total_tax' => round($lineTax, 2),
                'total_ttc' => round($lineTtc, 2),
            ]);

            $totalHt += $lineHt;
            $totalDiscount += ($qty * $price) - $lineHt;
            $totalTax += $lineTax;
            $totalTtc += $lineTtc;
        }

        $amountPaid = $paymentAmount ?? 0;
        $amountDue = round($totalTtc - $amountPaid, 2);

        \App\Models\DocumentFooter::create([
            'document_header_id' => $header->id,
            'total_ht' => round($totalHt, 2),
            'total_discount' => round($totalDiscount, 2),
            'total_tax' => round($totalTax, 2),
            'total_ttc' => round($totalTtc, 2),
            'amount_paid' => round($amountPaid, 2),
            'amount_due' => round($amountDue, 2),
            'payment_method' => $paymentMethod,
        ]);

        // Update status
        if ($status !== 'draft') {
            $header->update(['status' => $status]);
        }

        // Payment record
        if ($paymentAmount && $paymentAmount > 0 && $paymentMethod && $paymentMethod !== 'credit') {
            \App\Models\Payment::create([
                'document_header_id' => $header->id,
                'amount' => round($paymentAmount, 2),
                'method' => $paymentMethod,
                'paid_at' => $issuedAt,
                'user_id' => $userId,
                'notes' => 'Paiement démo',
            ]);
        }

        return $header;
    }

    // ── Generate transactions over 6 months ─────────────
    echo "\n📄 Creating documents...\n";

    $now = now();
    $docCount = 0;

    // For each of the last 6 months, create various documents
    for ($m = 5; $m >= 0; $m--) {
        $monthDate = $now->copy()->subMonths($m);
        $monthStr = $monthDate->format('Y-m');
        echo "  📅 $monthStr\n";

        // Number of documents per month increases toward present
        $numInvoices = rand(3, 5) + (5 - $m);
        $numBL = rand(2, 4);
        $numDevis = rand(1, 3);

        // ── Invoices (paid, partial, pending) ──
        for ($d = 0; $d < $numInvoices; $d++) {
            $day = rand(1, min(28, $monthDate->daysInMonth));
            $date = "$monthStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $custId = $customerIds[array_rand($customerIds)];

            // Pick 1-4 random products
            $numLines = rand(1, 4);
            $lineProds = array_rand(array_flip(range(0, count($productIds) - 1)), min($numLines, count($productIds)));
            if (!is_array($lineProds)) $lineProds = [$lineProds];

            $lines = [];
            $total = 0;
            foreach ($lineProds as $pi) {
                $prod = $productIds[$pi];
                $qty = rand(1, 5);
                $disc = rand(0, 1) ? rand(0, 10) : 0;
                $lineTotal = $qty * $prod->p_salePrice * (1 - $disc / 100) * 1.2;
                $total += $lineTotal;
                $lines[] = [
                    'product_id' => $prod->id,
                    'designation' => $prod->p_title,
                    'reference' => $prod->p_code ?? '',
                    'qty' => $qty,
                    'price' => $prod->p_salePrice,
                    'discount' => $disc,
                    'tax' => 20,
                ];
            }

            // Decide payment status
            $roll = rand(1, 10);
            if ($roll <= 5) {
                // Fully paid
                $status = 'paid';
                $method = ['cash', 'bank_transfer', 'cheque', 'effet'][rand(0, 3)];
                $paid = round($total, 2);
            } elseif ($roll <= 7) {
                // Partial
                $status = 'partial';
                $method = 'bank_transfer';
                $paid = round($total * rand(30, 70) / 100, 2);
            } else {
                // Confirmed (unpaid)
                $status = 'confirmed';
                $method = null;
                $paid = 0;
            }

            $dueDate = date('Y-m-d', strtotime($date . ' +30 days'));
            $doc = createDocument($incrementorService, 'InvoiceSale', $custId, $warehouse->id, $admin->id, $lines, $status, $date, $dueDate, $method, $paid);
            if ($doc) $docCount++;
        }

        // ── Delivery Notes ──
        for ($d = 0; $d < $numBL; $d++) {
            $day = rand(1, min(28, $monthDate->daysInMonth));
            $date = "$monthStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $custId = $customerIds[array_rand($customerIds)];

            $numLines = rand(1, 3);
            $lineProds = array_rand(array_flip(range(0, count($productIds) - 1)), min($numLines, count($productIds)));
            if (!is_array($lineProds)) $lineProds = [$lineProds];

            $lines = [];
            $total = 0;
            foreach ($lineProds as $pi) {
                $prod = $productIds[$pi];
                $qty = rand(1, 3);
                $lineTotal = $qty * $prod->p_salePrice * 1.2;
                $total += $lineTotal;
                $lines[] = [
                    'product_id' => $prod->id,
                    'designation' => $prod->p_title,
                    'reference' => $prod->p_code ?? '',
                    'qty' => $qty,
                    'price' => $prod->p_salePrice,
                    'tax' => 20,
                ];
            }

            $blStatus = rand(0, 1) ? 'confirmed' : 'paid';
            $paid = $blStatus === 'paid' ? round($total, 2) : 0;
            $method = $blStatus === 'paid' ? 'cash' : null;

            $doc = createDocument($incrementorService, 'DeliveryNote', $custId, $warehouse->id, $admin->id, $lines, $blStatus, $date, null, $method, $paid);
            if ($doc) $docCount++;
        }

        // ── Quotes ──
        for ($d = 0; $d < $numDevis; $d++) {
            $day = rand(1, min(28, $monthDate->daysInMonth));
            $date = "$monthStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $custId = $customerIds[array_rand($customerIds)];

            $numLines = rand(1, 5);
            $lineProds = array_rand(array_flip(range(0, count($productIds) - 1)), min($numLines, count($productIds)));
            if (!is_array($lineProds)) $lineProds = [$lineProds];

            $lines = [];
            foreach ($lineProds as $pi) {
                $prod = $productIds[$pi];
                $lines[] = [
                    'product_id' => $prod->id,
                    'designation' => $prod->p_title,
                    'reference' => $prod->p_code ?? '',
                    'qty' => rand(1, 10),
                    'price' => $prod->p_salePrice,
                    'tax' => 20,
                ];
            }

            $qStatus = ['draft', 'confirmed', 'confirmed'][rand(0, 2)];
            $doc = createDocument($incrementorService, 'QuoteSale', $custId, $warehouse->id, $admin->id, $lines, $qStatus, $date);
            if ($doc) $docCount++;
        }

        // ── Customer Orders (fewer) ──
        if (rand(0, 1)) {
            $day = rand(1, min(28, $monthDate->daysInMonth));
            $date = "$monthStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $custId = $customerIds[array_rand($customerIds)];

            $numLines = rand(2, 4);
            $lineProds = array_rand(array_flip(range(0, count($productIds) - 1)), min($numLines, count($productIds)));
            if (!is_array($lineProds)) $lineProds = [$lineProds];

            $lines = [];
            foreach ($lineProds as $pi) {
                $prod = $productIds[$pi];
                $lines[] = [
                    'product_id' => $prod->id,
                    'designation' => $prod->p_title,
                    'reference' => $prod->p_code ?? '',
                    'qty' => rand(1, 3),
                    'price' => $prod->p_salePrice,
                    'tax' => 20,
                ];
            }

            $doc = createDocument($incrementorService, 'CustomerOrder', $custId, $warehouse->id, $admin->id, $lines, 'confirmed', $date);
            if ($doc) $docCount++;
        }

        // ── Purchase Orders ──
        if (rand(0, 1)) {
            $supps = \App\Models\ThirdPartner::where('tp_Role', 'supplier')->pluck('id')->toArray();
            if (count($supps)) {
                $day = rand(1, min(28, $monthDate->daysInMonth));
                $date = "$monthStr-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                $suppId = $supps[array_rand($supps)];

                $numLines = rand(2, 5);
                $lineProds = array_rand(array_flip(range(0, count($productIds) - 1)), min($numLines, count($productIds)));
                if (!is_array($lineProds)) $lineProds = [$lineProds];

                $lines = [];
                foreach ($lineProds as $pi) {
                    $prod = $productIds[$pi];
                    $lines[] = [
                        'product_id' => $prod->id,
                        'designation' => $prod->p_title,
                        'reference' => $prod->p_code ?? '',
                        'qty' => rand(5, 20),
                        'price' => $prod->p_purchasePrice,
                        'tax' => 20,
                    ];
                }

                $doc = createDocument($incrementorService, 'PurchaseOrder', $suppId, $warehouse->id, $admin->id, $lines, 'confirmed', $date);
                if ($doc) $docCount++;
            }
        }
    }

    echo "\n✅ Total documents created: $docCount\n";
    echo "✅ Demo data seeding complete!\n";
});

echo "\n🎉 Done! Visit https://demo.o3app.ma\n";
