<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\StockMouvement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockMouvementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Repairs documents cancelled before the generic status endpoint learned to
 * reverse stock: their movements stayed 'applied', so a cancelled BR kept
 * crediting its received quantity to the warehouse (and a cancelled BL kept
 * its exit deducted).
 */
class RepairCancelledDocumentStock extends Command
{
    protected $signature = 'documents:repair-cancelled-stock
                            {--dry-run : List affected documents without touching stock}
                            {--tenant= : Restrict the run to a single tenant id}';

    protected $description = 'Reverse stock movements left applied on cancelled documents';

    public function handle(StockMouvementService $stockService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($only, fn ($q) => $q->whereKey($only))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($only ? "No tenant '{$only}'." : 'No tenants found.');

            return self::FAILURE;
        }

        $repaired = 0;
        $failed   = 0;

        $tenants->each(function (Tenant $tenant) use ($stockService, $dryRun, &$repaired, &$failed) {
            try {
                $tenant->run(function () use ($tenant, $stockService, $dryRun, &$repaired) {
                    // Cancelled documents that still hold live movements.
                    $documentIds = StockMouvement::query()
                        ->whereIn('status', ['pending', 'applied'])
                        ->whereIn('document_header_id', DocumentHeader::query()
                            ->where('status', 'cancelled')
                            ->select('id'))
                        ->distinct()
                        ->pluck('document_header_id');

                    if ($documentIds->isEmpty()) {
                        return;
                    }

                    // cancelDocumentMovements() stamps auth()->id() on the
                    // compensating movements, and stock_mouvements.user_id is
                    // NOT NULL — so the CLI needs someone to act as.
                    $actor = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->first()
                        ?? User::first();

                    if (! $actor) {
                        $this->warn("  {$tenant->id}: no user to attribute the correction to — skipped");

                        return;
                    }

                    Auth::login($actor);

                    foreach (DocumentHeader::whereIn('id', $documentIds)->get() as $document) {
                        $applied = StockMouvement::where('document_header_id', $document->id)
                            ->whereIn('status', ['pending', 'applied'])
                            ->count();

                        $this->warn("  {$tenant->id}: {$document->reference} ({$document->document_type}) — {$applied} mouvement(s) encore actif(s)");

                        if ($dryRun) {
                            continue;
                        }

                        DB::transaction(fn () => $stockService->cancelDocumentMovements($document));
                        $this->info("    corrigé — stock rendu");
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
        if ($repaired === 0) {
            $this->info($dryRun ? 'Dry run complete — nothing written.' : 'No cancelled document was holding stock.');
        } else {
            $this->info("{$repaired} document(s) corrigé(s).");
        }
        if ($failed) {
            $this->warn("{$failed} tenant(s) could not be processed.");
        }

        return self::SUCCESS;
    }
}
