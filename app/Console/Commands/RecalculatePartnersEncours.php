<?php

namespace App\Console\Commands;

use App\Models\ThirdPartner;
use Illuminate\Console\Command;

class RecalculatePartnersEncours extends Command
{
    protected $signature = 'partners:recalculate-encours
                            {--id= : Recalculate only a specific partner id}
                            {--role=all : Filter by role (customer, supplier, both, all)}
                            {--dry-run : Show what would change without persisting}';

    protected $description = 'Recompute encours_actuel for every third partner from source data (invoices + BLs if paiement_sur_bl + purchases − payments − returns).';

    public function handle(): int
    {
        $query = ThirdPartner::query();

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }

        $role = $this->option('role');
        if ($role !== 'all') {
            $query->where('tp_Role', $role);
        }

        $partners = $query->get();

        if ($partners->isEmpty()) {
            $this->warn('No partners matched.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $unchanged = 0;

        $this->info(sprintf('%sRecalculating encours for %d partner(s)…',
            $dryRun ? '[DRY-RUN] ' : '', $partners->count()));

        foreach ($partners as $partner) {
            $before = (float) $partner->encours_actuel;
            $after = $partner->recalculateEncours(persist: !$dryRun);

            $delta = round($after - $before, 2);
            if (abs($delta) < 0.01) {
                $unchanged++;
                continue;
            }

            $changed++;
            $this->line(sprintf(
                '  %-40s  %10.2f → %10.2f  (Δ %+.2f)',
                \Illuminate\Support\Str::limit($partner->tp_title, 38),
                $before,
                $after,
                $delta
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '%sDone. %d changed, %d unchanged.',
            $dryRun ? '[DRY-RUN] ' : '',
            $changed,
            $unchanged
        ));

        return self::SUCCESS;
    }
}
