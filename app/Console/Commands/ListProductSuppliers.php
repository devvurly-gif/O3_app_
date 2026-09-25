<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;

/** Affiche les fournisseurs liés à un produit, du plus préféré au moins préféré. */
class ListProductSuppliers extends Command
{
    protected $signature = 'products:list-suppliers
        {sku : SKU exact du produit}
        {tenant=jadema : ID du tenant}';

    protected $description = "Liste les fournisseurs liés à un produit (priorité, prix, délai)";

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $exit = self::FAILURE;
        $tenant->run(function () use (&$exit) {
            $exit = $this->list();
        });

        return $exit;
    }

    private function list(): int
    {
        $product = Product::where('p_sku', $this->argument('sku'))->first();
        if (!$product) {
            $this->error("Produit introuvable : SKU exact « {$this->argument('sku')} » attendu.");
            return self::FAILURE;
        }

        $suppliers = $product->suppliers;
        if ($suppliers->isEmpty()) {
            $this->info("Aucun fournisseur lié à {$product->p_sku} — {$product->p_title} (historique d'achat utilisé par défaut, s'il existe).");
            return self::SUCCESS;
        }

        $this->table(
            ['Priorité', 'Fournisseur', 'Réf. fournisseur', 'Prix d\'achat', 'Délai (j)'],
            $suppliers->map(fn ($s) => [
                $s->pivot->priority,
                $s->tp_title,
                $s->pivot->supplier_sku ?? '—',
                $s->pivot->purchase_price ?? '—',
                $s->pivot->lead_time_days ?? '—',
            ])
        );

        return self::SUCCESS;
    }
}
