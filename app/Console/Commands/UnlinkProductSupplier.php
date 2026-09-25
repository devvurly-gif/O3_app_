<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use Illuminate\Console\Command;

/** Retire un fournisseur de la liste de ceux pouvant livrer un produit (table product_suppliers). */
class UnlinkProductSupplier extends Command
{
    protected $signature = 'products:unlink-supplier
        {sku : SKU exact du produit}
        {supplier : ICE (15 chiffres), code, ou raison sociale exacte du fournisseur}
        {tenant=jadema : ID du tenant}';

    protected $description = "Retire un fournisseur de la liste de ceux pouvant livrer un produit";

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->unlink();
        });

        return $exit;
    }

    private function unlink(): int
    {
        $product = Product::where('p_sku', $this->argument('sku'))->first();
        if (!$product) {
            $this->error("Produit introuvable : SKU exact « {$this->argument('sku')} » attendu.");
            return self::FAILURE;
        }

        $supplierArg = $this->argument('supplier');
        $q = fn () => ThirdPartner::whereIn('tp_Role', ['supplier', 'both']);
        $supplier = ctype_digit($supplierArg) && strlen($supplierArg) === 15
            ? $q()->where('tp_Ice_Number', $supplierArg)->first()
            : ($q()->where('tp_code', $supplierArg)->first() ?? $q()->where('tp_title', $supplierArg)->first());

        if (!$supplier) {
            $this->error("Fournisseur introuvable pour « {$supplierArg} ».");
            return self::FAILURE;
        }

        $removed = $product->suppliers()->detach($supplier->id);
        $this->info($removed
            ? "{$supplier->tp_title} retiré de {$product->p_sku} — {$product->p_title}."
            : "{$supplier->tp_title} n'était pas lié à {$product->p_sku} — rien à faire.");

        return self::SUCCESS;
    }
}
