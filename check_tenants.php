<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

$tenants = Stancl\Tenancy\Models\Tenant::all();
echo Available tenants:n;
foreach ($tenants as $tenant) {
    $key = $tenant->getTenantKey();
    echo - ID: {- Key: {}n;
}
