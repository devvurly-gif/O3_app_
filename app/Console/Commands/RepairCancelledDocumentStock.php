<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\StockMouvement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WarehouseHasStock;
use App\Services\StockMouvementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Repairs documents cancelled before the generic status endpoint learned to
 * reverse stock: their movements stayed 'applied', so a cancelled BR kept
 * crediting its received quantity to the warehouse (and a cancelled BL kept
 * its exit deducted).
 *
 * Two kinds of cancelled document are deliberately left alone:
 *   - those whose live movements already net to zero per product/warehouse —
 *     a voided POS ticket, for instance, carries its own compensating
 *     pos_void entry and is not holding any stock;
 *   - those whose correction would drive a stock level negative, meaning the
 *     goods were consumed after the (now cancelled) reception. That needs a
 *     human decision, so it takes --allow-negative.
 */
class RepairCancelledDocumentStock extends Command
{
    protected $signature = 'documents:repair-cancelled-stock
                            {--dry-run : List what would change without touching stock}
                            {--tenant= : Restrict the run to a single tenant id}
                            {--reference=* : Restrict the run to specific document references}
                            {--allow-negative : Also repair documents whose correction ends below zero}';

    protected $description = 'Reverse stock movements left applied on cancelled documents';

    public function handle(StockMouvementService $stockService): int
    {
        $dryRun         = (bool) $this->option('dry-run');
        $allowNegative  = (bool) $this->option('allow-negative');
        $only           = $this->option('tenant');
        $references     = (array) $this->option('reference');

        $tenants = Tenant::query()
            ->when($only, fn ($q) => $q->whereKey($only))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($only ? "No tenant '{$only}'." : 'No tenants found.');

            return self::FAILURE;
        }

        $repaired = $skipped = $failed = 0;

        $tenants->each(function (Tenant $tenant) use (
            $stockService, $dryRun, $allowNegative, $references, &$repaired, &$skipped, &$failed
        ) {
            try {
                $tenant->run(function () use (
                    $tenant, $stockService, $dryRun, $allowNegative, $references, &$repaired, &$skipped
                ) {
                    $documents = DocumentHeader::query()
                        ->where('status', 'cancelled')
                        ->when($references, fn ($q) => $q->whereIn('reference', $references))
                        ->whereIn('id', StockMouvement::query()
                            ->whereIn('status', ['pending', 'applied'])
                            ->select('document_header_id'))
                        ->get();

                    if ($documents->isEmpty()) {
                        return;
                    }

                    // cancelDocumentMovements() stamps auth()->id() on the
                    // compensating movements and stock_mouvements.user_id is
                    // NOT NULL, so the CLI needs someone to act as.
                    $actor = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->first() ?? User::first();

                    if (! $actor) {
                        $this->warn("  {$tenant->id}: no user to attribute the correction to — skipped");

                        return;
                    }

                    Auth::login($actor);

                    foreach ($documents as $document) {
                        $net = $this->netPerLocation($document);

                        if (! array_filter($net, fn ($qty) => abs($qty) > 0.0001)) {
                            $this->line("  {$tenant->id}: {$document->reference} — déjà compensé, ignoré");
                            $skipped++;
                            continue;
                        }

                        $negative = [];
                        foreach ($net as $key => $qty) {
                            [$productId, $warehouseId] = explode(':', $key);
                            $current = (float) (WarehouseHasStock::where('product_id', $productId)
                                ->where('warehouse_id', $warehouseId)
                                ->value('stockLevel') ?? 0);

                            // Undoing the document subtracts whatever it added.
                            $after = $current - $qty;
                            $this->line(sprintf(
                                '  %s: %s — produit %s / dépôt %s : %.2f → %.2f',
                                $tenant->id,
                                $document->reference,
                                $productId,
                                $warehouseId,
                                $current,
                                $after
                            ));

                            if ($after < 0) {
                                $negative[] = $key;
                            }
                        }

                        if ($negative && ! $allowNegative) {
                            $this->warn("    ↳ correction négative — ignoré (relancer avec --allow-negative)");
                            $skipped++;
                            continue;
                        }

                        if ($dryRun) {
                            continue;
                        }

                        DB::transaction(fn () => $stockService->cancelDocumentMovements($document));
                        $this->info('    ↳ corrigé');
                        $repaired++;
                    }

                    Auth::logout();
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ! skipped tenant '{$tenant->id}': {$e->getMessage()}");
            }
        });

        $this->newLine();
        if ($dryRun) {
            $this->info('Dry run terminé — rien écrit.');
        } elseif ($repaired === 0) {
            $this->info('Aucun document annulé ne retenait de stock.');
        } else {
            $this->info("{$repaired} document(s) corrigé(s).");
        }
        if ($skipped) {
            $this->warn("{$skipped} document(s) ignoré(s).");
        }
        if ($failed) {
            $this->warn("{$failed} tenant(s) could not be processed.");
        }

        return self::SUCCESS;
    }

    /**
     * Net quantity this document is still holding, keyed "productId:warehouseId".
     * Positive = it added that much to stock, negative = it took that much out.
     *
     * @return array<string, float>
     */
    private function netPerLocation(DocumentHeader $document): array
    {
        $net = [];

        $movements = StockMouvement::where('document_header_id', $document->id)
            ->whereIn('status', ['pending', 'applied'])
            ->get();

        foreach ($movements as $movement) {
            $key = $movement->product_id . ':' . $movement->warehouse_id;
            $net[$key] = ($net[$key] ?? 0) + ($movement->direction === 'in' ? 1 : -1) * (float) $movement->quantity;
        }

        return $net;
    }
}
