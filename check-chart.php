<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Models\Tenant::find('demo')->run(function () {
    $svc = app(\App\Services\DashboardService::class);
    $d = $svc->getKpis();
    echo "=== sales_purchases_chart ===\n";
    foreach ($d['sales_purchases_chart'] as $r) {
        echo $r['label'] . " => sales: " . $r['sales'] . ", purchases: " . $r['purchases'] . "\n";
    }
    echo "\n=== revenue_chart ===\n";
    foreach ($d['revenue_chart'] as $r) {
        echo $r['month'] . " => " . $r['total'] . "\n";
    }
});
