<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use Illuminate\Console\Command;

/**
 * Assigne (ou retire) le fournisseur par défaut d'un produit — utilisé par
 * achats:draft-daily-po quand un produit vendu n'a encore aucun historique
 * d'achat pour en inférer un automatiquement (voir Product::defaultSupplier()).
 *
 * Recherche du produit par SKU exact, et du fournisseur par ICE, puis code,
 * puis raison sociale exacte (même ordre de priorité que
 * PurchaseImportService::resolveSupplier()) — jamais de rapprochement flou ni
 * de création : en cas d'ambiguïté ou d'absence, la commande échoue plutôt
 * que de deviner.
 */
class SetProductDefaultSupplier extends Command
{
    protected $signature = 'products:set-supplier
        {sku : SKU exact du produit}
        {supplier? : ICE (15 chiffres), code, ou raison sociale exacte du fournisseur}
        {tenant=jadema : ID du tenant}
        {--clear : retire le fournisseur par défaut au lieu d\'en assigner un}';

    protected $description = "Assigne le fournisseur par défaut d'un produit (pour achats:draft-daily-po)";

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->assign();
        });

        return $exit;
    }

    private function assign(): int
    {
        $sku = $this->argument('sku');
        $product = Product::where('p_sku', $sku)->first();
        if (!$product) {
            $this->error("Produit introuvable : SKU exact « {$sku} » attendu.");
            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $product->update(['default_supplier_id' => null]);
            $this->info("Fournisseur par défaut retiré pour {$product->p_sku} — {$product->p_title}.");
            return self::SUCCESS;
        }

        $supplierArg = $this->argument('supplier');
        if (!$supplierArg) {
            $this->error('Indiquez un fournisseur (ICE, code ou raison sociale), ou --clear pour retirer.');
            return self::FAILURE;
        }

        $q = fn () => ThirdPartner::whereIn('tp_Role', ['supplier', 'both']);

        $supplier = ctype_digit($supplierArg) && strlen($supplierArg) === 15
            ? $q()->where('tp_Ice_Number', $supplierArg)->first()
            : ($q()->where('tp_code', $supplierArg)->first() ?? $q()->where('tp_title', $supplierArg)->first());

        if (!$supplier) {
            $this->error("Fournisseur introuvable pour « {$supplierArg} » (ICE, code ou raison sociale exacte attendus). Aucune création — vérifiez la fiche dans O3.");
            return self::FAILURE;
        }

        $product->update(['default_supplier_id' => $supplier->id]);
        $this->info("Fournisseur par défaut de {$product->p_sku} — {$product->p_title} : {$supplier->tp_title} (id {$supplier->id}).");

        return self::SUCCESS;
    }
}
