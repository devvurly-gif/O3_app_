<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use Illuminate\Console\Command;

/**
 * Ajoute ou met à jour un fournisseur pouvant livrer un produit (table
 * product_suppliers), avec sa priorité et son prix d'achat propres — un
 * produit s'achète souvent chez plusieurs fournisseurs, à des prix et délais
 * différents. Utilisé par achats:draft-daily-po pour choisir le préféré
 * (priority=1) avant de retomber sur l'historique d'achat.
 *
 * Recherche du produit par SKU exact, du fournisseur par ICE, code, puis
 * raison sociale exacte — jamais de rapprochement flou ni de création
 * automatique : en cas d'ambiguïté ou d'absence, la commande échoue.
 */
class LinkProductSupplier extends Command
{
    protected $signature = 'products:link-supplier
        {sku : SKU exact du produit}
        {supplier : ICE (15 chiffres), code, ou raison sociale exacte du fournisseur}
        {--price= : prix d\'achat HT chez ce fournisseur (optionnel)}
        {--priority=1 : 1 = préféré ; à priorité égale, le lien ajouté en premier reste préféré}
        {--lead-time= : délai de livraison habituel, en jours (optionnel)}
        {--supplier-sku= : référence de l\'article chez ce fournisseur (optionnel)}
        {tenant=jadema : ID du tenant}';

    protected $description = "Ajoute/actualise un fournisseur pour un produit (table product_suppliers)";

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->link();
        });

        return $exit;
    }

    private function link(): int
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
            $this->error("Fournisseur introuvable pour « {$supplierArg} » (ICE, code ou raison sociale exacte attendus). Aucune création — vérifiez la fiche dans O3.");
            return self::FAILURE;
        }

        $product->suppliers()->syncWithoutDetaching([
            $supplier->id => array_filter([
                'supplier_sku'   => $this->option('supplier-sku'),
                'purchase_price' => $this->option('price'),
                'priority'       => (int) $this->option('priority'),
                'lead_time_days' => $this->option('lead-time'),
            ], fn ($v) => $v !== null),
        ]);

        $this->info("{$supplier->tp_title} lié à {$product->p_sku} — {$product->p_title} (priorité " . $this->option('priority') . ").");
        return self::SUCCESS;
    }
}
