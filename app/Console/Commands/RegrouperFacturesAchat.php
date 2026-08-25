<?php

namespace App\Console\Commands;

use App\Models\DocumentHeader;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Services\PurchaseInvoiceGroupingService;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rattrapage en ligne de commande : fusionne les factures d'achat deja emises
 * d'un fournisseur en une facture unique.
 *
 * Le cas courant — cocher des bons de reception et les facturer d'un coup — se
 * fait depuis la liste des achats. Cette commande sert au rattrapage, quand les
 * bons ont deja ete factures un par un et qu'il faut recoller les morceaux sur
 * plusieurs mois d'historique.
 *
 * Toute la regle metier vit dans PurchaseInvoiceGroupingService, partage avec
 * l'ecran : la dupliquer ici aurait laisse les deux chemins diverger.
 */
class RegrouperFacturesAchat extends Command
{
    protected $signature = 'achats:regrouper-factures
        {tenant? : ID du tenant ; omis, la commande travaille sur la connexion courante}
        {--supplier= : Raison sociale du fournisseur (tp_title)}
        {--date= : Date de la facture groupee (defaut : la plus recente des sources)}
        {--supplier-ref= : Numero de la facture du fournisseur, inscrit en note}
        {--dry-run : Montrer ce qui serait fait, sans rien ecrire}';

    protected $description = 'Fusionne les factures d\'achat d\'un fournisseur en une facture groupee';

    public function handle(PurchaseInvoiceGroupingService $grouping): int
    {
        if (!$this->argument('tenant')) {
            return $this->run_($grouping);
        }

        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $this->info("Tenant : {$tenant->name} ({$tenant->id})");

        $exit = self::SUCCESS;
        $tenant->run(function () use (&$exit, $grouping) {
            $exit = $this->run_($grouping);
        });

        return $exit;
    }

    private function run_(PurchaseInvoiceGroupingService $grouping): int
    {
        $supplier = null;
        if ($this->option('supplier')) {
            $supplier = ThirdPartner::where('tp_title', $this->option('supplier'))->first();
            if (!$supplier) {
                $this->error("Fournisseur '{$this->option('supplier')}' introuvable.");
                return self::FAILURE;
            }
        }

        $sources = DocumentHeader::where('document_type', 'InvoicePurchase')
            ->when($supplier, fn ($q) => $q->where('thirdPartner_id', $supplier->id))
            ->where('status', '!=', 'cancelled')
            ->with(['lignes', 'footer', 'parent', 'payments'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        if ($sources->count() < 2) {
            $this->error('Il faut au moins deux factures a regrouper (trouve : ' . $sources->count() . ').');
            return self::FAILURE;
        }

        $totalTtc  = $sources->sum(fn ($i) => (float) ($i->footer?->total_ttc ?? 0));
        $totalPaid = $sources->sum(fn ($i) => (float) ($i->footer?->amount_paid ?? 0));

        $this->newLine();
        $this->line('── Sources ──');
        foreach ($sources as $invoice) {
            $this->line(sprintf(
                '  %s | %s | %s MAD | paye %s MAD | %d ligne(s)',
                $invoice->reference,
                $invoice->issued_at?->format('d/m/Y'),
                number_format((float) ($invoice->footer?->total_ttc ?? 0), 2, ',', ' '),
                number_format((float) ($invoice->footer?->amount_paid ?? 0), 2, ',', ' '),
                $invoice->lignes->count()
            ));
        }

        $this->newLine();
        $this->line('── Facture groupee ──');
        $this->line('  Date        : ' . date('d/m/Y', strtotime($this->option('date') ?: $sources->max('issued_at')?->toDateString())));
        $this->line('  Total TTC   : ' . number_format($totalTtc, 2, ',', ' ') . ' MAD');
        $this->line('  Deja regle  : ' . number_format($totalPaid, 2, ',', ' ') . ' MAD');
        $this->line('  Reste du    : ' . number_format($totalTtc - $totalPaid, 2, ',', ' ') . ' MAD');
        $this->line('  Lignes      : ' . $sources->sum(fn ($i) => $i->lignes->count()));

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('[dry-run] Terminé — rien n\'a été écrit.');
            return self::SUCCESS;
        }

        try {
            $result = $grouping->fromInvoices(
                $sources,
                $this->option('date') ?: null,
                $this->option('supplier-ref') ?: null,
            );
        } catch (HttpException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'OK  %s créée : %s MAD, %d ligne(s), %d règlement(s) repris, %d facture(s) remplacée(s).',
            $result['invoice']->reference,
            number_format($totalTtc, 2, ',', ' '),
            $result['invoice']->lignes()->count(),
            $result['payments_moved'],
            $result['replaced']
        ));

        if ($supplier) {
            $this->info('Encours ' . $supplier->tp_title . ' : '
                . number_format($supplier->fresh()->encours_actuel, 2, ',', ' ') . ' MAD');
        }

        return self::SUCCESS;
    }
}
