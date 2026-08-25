<?php

namespace App\Console\Commands;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Observers\DocumentNotificationObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fusionne plusieurs factures d'achat d'un fournisseur en une seule.
 *
 * Un fournisseur qui livre huit fois dans le mois n'envoie pas forcement huit
 * factures : il en envoie une, qui recapitule les huit bons. Tant que la
 * comptabilite du tenant porte huit factures la ou le fournisseur en a emis
 * une, aucun rapprochement ne tombe juste.
 *
 * Le mecanisme reprend celui de la facture periodique cote vente
 * (GeneratePeriodicInvoices) : un document sans parent, les lignes de toutes
 * les sources concatenees, les pieds additionnes, et la liste des bons rappelee
 * en note — un parent_id ne pouvant pas designer huit documents.
 *
 * Deux precautions propres a la fusion :
 *   - les reglements deja imputes suivent la nouvelle facture, sinon un
 *     paiement pointerait vers un document supprime et le reste du serait faux ;
 *   - les bons de reception passent en 'converted', sans quoi
 *     `achats:facturer-br` les verrait de nouveau sans facture et refacturerait
 *     par-dessus la facture groupee.
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

        $totalTtc = $sources->sum(fn ($i) => (float) ($i->footer?->total_ttc ?? 0));
        $totalPaid = $sources->sum(fn ($i) => (float) ($i->footer?->amount_paid ?? 0));
        $issuedAt = $this->option('date') ?: $sources->max('issued_at')?->toDateString();

        // Les bons de reception d'origine, pour la note et pour leur statut.
        $receiptRefs = $sources->map(fn ($i) => $i->parent?->reference)->filter()->values();

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
        $this->line('  Date        : ' . date('d/m/Y', strtotime($issuedAt)));
        $this->line('  Total TTC   : ' . number_format($totalTtc, 2, ',', ' ') . ' MAD');
        $this->line('  Deja regle  : ' . number_format($totalPaid, 2, ',', ' ') . ' MAD');
        $this->line('  Reste du    : ' . number_format($totalTtc - $totalPaid, 2, ',', ' ') . ' MAD');
        $this->line('  Lignes      : ' . $sources->sum(fn ($i) => $i->lignes->count()));
        $this->line('  Bons        : ' . ($receiptRefs->isNotEmpty() ? $receiptRefs->implode(', ') : '—'));

        if ($dryRun) {
            $this->newLine();
            $this->info('[dry-run] Terminé — rien n\'a été écrit.');
            return self::SUCCESS;
        }

        // Une facture groupee n'est pas une nouvelle affaire a annoncer : les
        // documents qu'elle remplace ont deja ete notifies a leur creation.
        DocumentNotificationObserver::$silent = true;

        try {
            $grouped = DB::transaction(function () use ($sources, $issuedAt, $totalTtc, $totalPaid, $receiptRefs, $supplier) {
                $first = $sources->first();

                $notes = 'Facture groupée — BR : ' . ($receiptRefs->isNotEmpty() ? $receiptRefs->implode(', ') : '—');
                if ($this->option('supplier-ref')) {
                    $notes .= ' | Facture fournisseur n° ' . $this->option('supplier-ref');
                }

                $grouped = DocumentHeader::create([
                    'document_incrementor_id' => $first->document_incrementor_id,
                    'reference'               => $this->nextReference($sources),
                    'document_type'           => 'InvoicePurchase',
                    'document_title'          => 'Facture Achat groupée',
                    // Un parent unique ne peut pas designer huit sources : le
                    // lien vit dans les notes, comme pour la facture periodique.
                    'parent_id'               => null,
                    'thirdPartner_id'         => $first->thirdPartner_id,
                    'company_role'            => $first->company_role,
                    'warehouse_id'            => $first->warehouse_id,
                    'user_id'                 => $first->user_id,
                    'status'                  => 'pending',
                    'issued_at'               => $issuedAt,
                    'due_at'                  => date('Y-m-d', strtotime($issuedAt . ' +60 days')),
                    'notes'                   => $notes,
                ]);

                $sortOrder = 0;
                $now = now();
                $lignes = [];

                foreach ($sources as $invoice) {
                    foreach ($invoice->lignes as $ligne) {
                        $lignes[] = [
                            'document_header_id' => $grouped->id,
                            'product_id'         => $ligne->product_id,
                            'variant_id'         => $ligne->variant_id,
                            'sort_order'         => ++$sortOrder,
                            'line_type'          => $ligne->line_type,
                            'designation'        => $ligne->designation,
                            'reference'          => $ligne->reference,
                            'quantity'           => $ligne->quantity,
                            'unit'               => $ligne->unit,
                            'unit_price'         => $ligne->unit_price,
                            'discount_percent'   => $ligne->discount_percent,
                            'tax_percent'        => $ligne->tax_percent,
                            'total_ligne_ht'     => $ligne->total_ligne_ht,
                            'total_tax'          => $ligne->total_tax,
                            'total_ttc'          => $ligne->total_ttc,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ];
                    }
                }

                if ($lignes) {
                    DocumentLigne::insert($lignes);
                }

                DocumentFooter::create([
                    'document_header_id' => $grouped->id,
                    'total_ht'           => $sources->sum(fn ($i) => (float) ($i->footer?->total_ht ?? 0)),
                    'total_discount'     => $sources->sum(fn ($i) => (float) ($i->footer?->total_discount ?? 0)),
                    'total_tax'          => $sources->sum(fn ($i) => (float) ($i->footer?->total_tax ?? 0)),
                    'total_ttc'          => $totalTtc,
                    'amount_paid'        => $totalPaid,
                    'amount_due'         => max(0, $totalTtc - $totalPaid),
                    'payment_method'     => 'credit',
                ]);

                // Les reglements suivent la facture : sans ca, ils pointeraient
                // vers un document supprime et le reste du serait faux.
                $moved = 0;
                foreach ($sources as $invoice) {
                    foreach ($invoice->payments as $payment) {
                        $payment->forceFill(['document_header_id' => $grouped->id])->saveQuietly();
                        $moved++;
                    }
                }

                foreach ($sources as $invoice) {
                    // Le bon de reception est desormais facture par le document
                    // groupe : 'converted' l'empeche d'etre refacture.
                    $invoice->parent?->update(['status' => 'converted']);
                    $invoice->delete();
                }

                $grouped->setRelation('footer', $grouped->footer()->first());
                $grouped->footer->syncHeaderStatus();

                if ($supplier) {
                    $supplier->recalculateEncours();
                }

                $this->paymentsMoved = $moved;

                return $grouped;
            });
        } finally {
            DocumentNotificationObserver::$silent = false;
        }

        $this->newLine();
        $this->info(sprintf(
            'OK  %s créée : %s MAD, %d ligne(s), %d règlement(s) repris, %d facture(s) remplacée(s).',
            $grouped->reference,
            number_format($totalTtc, 2, ',', ' '),
            $grouped->lignes()->count(),
            $this->paymentsMoved,
            $sources->count()
        ));

        if ($supplier) {
            $this->info('Encours ' . $supplier->tp_title . ' : '
                . number_format($supplier->fresh()->encours_actuel, 2, ',', ' ') . ' MAD');
        }

        return self::SUCCESS;
    }

    private int $paymentsMoved = 0;

    /**
     * Reference de la facture groupee.
     *
     * L'incrementeur du tenant a deja servi pour les sources ; on repart de son
     * compteur pour ne pas percuter une reference existante.
     */
    private function nextReference($sources): string
    {
        $incrementor = $sources->first()->incrementor;

        if (!$incrementor) {
            return 'FA-GROUP-' . now()->format('YmdHis');
        }

        return app(\App\Services\DocumentIncrementorService::class)->consumeNext($incrementor);
    }
}
