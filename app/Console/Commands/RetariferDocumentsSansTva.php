<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repasse des documents en prix « tout compris », sans ligne de TVA.
 *
 * Sur un tenant qui a la TVA desactivee, un document saisi TVA incluse affiche
 * un PU HT que rien n'explique a l'ecran : la colonne TVA y est masquee, et
 * l'utilisateur lit 833,33 la ou son papier dit 1 000. Les totaux de pied,
 * eux, sont justes — c'est donc l'affichage ligne a ligne qui ment.
 *
 * La commande remet le PU du document papier et met la TVA a zero. Elle repose
 * sur un invariant verifiable plutot que sur une source externe : le TTC de
 * chaque document ne doit pas bouger. S'il bouge, la transaction est annulee —
 * mieux vaut ne rien corriger que deplacer une dette fournisseur.
 */
class RetariferDocumentsSansTva extends Command
{
    protected $signature = 'documents:retarifer-sans-tva
        {tenant? : ID du tenant ; omis, la commande travaille sur la connexion courante}
        {--supplier= : Ne traiter que les documents de ce tiers (tp_title)}
        {--reference=* : Limiter a ces references de documents}
        {--dry-run : Montrer les lignes avant/apres, sans rien ecrire}';

    protected $description = 'Repasse des documents en prix tout compris (TVA a 0), a TTC constant';

    public function handle(): int
    {
        if (!$this->argument('tenant')) {
            return $this->run_();
        }

        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $this->info("Tenant : {$tenant->name} ({$tenant->id})");

        $exit = self::SUCCESS;
        $tenant->run(function () use (&$exit) {
            $exit = $this->run_();
        });

        return $exit;
    }

    private function run_(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $partner = null;
        if ($this->option('supplier')) {
            $partner = ThirdPartner::where('tp_title', $this->option('supplier'))->first();
            if (!$partner) {
                $this->error("Tiers '{$this->option('supplier')}' introuvable.");
                return self::FAILURE;
            }
        }

        $documents = DocumentHeader::query()
            ->when($partner, fn ($q) => $q->where('thirdPartner_id', $partner->id))
            ->when($this->option('reference'), fn ($q, $refs) => $q->whereIn('reference', $refs))
            ->whereHas('lignes', fn ($q) => $q->where('tax_percent', '>', 0))
            ->with(['lignes', 'footer'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            $this->warn('Aucun document a retarifer.');
            return self::SUCCESS;
        }

        $this->newLine();
        $touchedLines = 0;

        foreach ($documents as $document) {
            $ttcAvant = round((float) ($document->footer?->total_ttc ?? 0), 2);

            $this->line(sprintf(
                '── %s (%s) — TTC %s MAD, %d ligne(s)',
                $document->reference,
                $document->document_type,
                number_format($ttcAvant, 2, ',', ' '),
                $document->lignes->count()
            ));

            if ($dryRun) {
                foreach ($document->lignes->take(3) as $ligne) {
                    $this->line(sprintf(
                        '     %-14s %s x %s HT (TVA %s%%)  ->  %s x %s tout compris',
                        \Illuminate\Support\Str::limit($ligne->reference ?? '', 13, ''),
                        $ligne->quantity,
                        number_format((float) $ligne->unit_price, 2, ',', ' '),
                        rtrim(rtrim((string) $ligne->tax_percent, '0'), '.'),
                        $ligne->quantity,
                        number_format($this->allInPrice($ligne), 2, ',', ' ')
                    ));
                }
                if ($document->lignes->count() > 3) {
                    $this->line('     … ' . ($document->lignes->count() - 3) . ' autre(s) ligne(s)');
                }
                $touchedLines += $document->lignes->count();
                continue;
            }

            $touchedLines += $this->repriceDocument($document, $ttcAvant);
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . sprintf(
            'Termine — %d document(s), %d ligne(s).',
            $documents->count(),
            $touchedLines
        ));

        return self::SUCCESS;
    }

    /**
     * Le prix tout compris d'une ligne : celui que porte le document papier.
     */
    private function allInPrice($ligne): float
    {
        return round((float) $ligne->unit_price * (1 + (float) $ligne->tax_percent / 100), 2);
    }

    private function repriceDocument(DocumentHeader $document, float $ttcAvant): int
    {
        return DB::transaction(function () use ($document, $ttcAvant) {
            $count = 0;

            foreach ($document->lignes as $ligne) {
                $prix = $this->allInPrice($ligne);

                // L'observeur de DocumentLigne recalcule ht/tva/ttc au save :
                // poser le prix et le taux suffit.
                $ligne->update([
                    'unit_price'  => $prix,
                    'tax_percent' => 0,
                ]);

                $count++;
            }

            if ($document->footer) {
                $totalHt = round($document->lignes()->sum('total_ligne_ht'), 2);

                $document->footer->update([
                    'total_ht'  => $totalHt,
                    'total_tax' => 0,
                    'total_ttc' => $totalHt,
                ]);
            }

            // Le garde-fou : un TTC qui bouge, c'est une dette deplacee.
            $ttcApres = round((float) $document->footer?->fresh()?->total_ttc, 2);

            if (abs($ttcApres - $ttcAvant) > 0.01) {
                throw new \RuntimeException(sprintf(
                    '%s : le TTC passerait de %s a %s. Rien n\'a ete ecrit.',
                    $document->reference,
                    number_format($ttcAvant, 2, ',', ' '),
                    number_format($ttcApres, 2, ',', ' ')
                ));
            }

            $this->info(sprintf(
                '     OK — %d ligne(s) retarifee(s), TTC inchange a %s MAD',
                $count,
                number_format($ttcApres, 2, ',', ' ')
            ));

            return $count;
        });
    }
}
