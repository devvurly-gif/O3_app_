<?php

namespace App\Console\Commands;

use App\Models\CashAccount;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Observers\DocumentNotificationObserver;
use App\Services\DocumentIncrementorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Régularise des Bons de Réception : les facture, puis impute un règlement.
 *
 * Trois raisons de ne pas passer par l'écran :
 *   1. Huit BR à convertir un par un, c'est huit fois la même manipulation.
 *   2. `confirmer_facture` date la facture du jour ; ici la facture reprend la
 *      date du BR, pour que le journal d'achats colle au relevé fournisseur.
 *   3. Le bouton « paiement groupé » de la fiche fournisseur force lui aussi la
 *      date du jour, alors qu'un règlement se date du jour où il a eu lieu.
 *
 * L'option --apport couvre le cas où le fournisseur a été payé sur les deniers
 * personnels du dirigeant : sans elle, la caisse afficherait une sortie qui
 * n'en est pas une. L'apport entre, le règlement sort, la caisse est à zéro et
 * la dette fournisseur baisse — ce qui est exactement ce qui s'est passé.
 */
class FacturerBonsReception extends Command
{
    protected $signature = 'achats:facturer-br
        {tenant? : ID du tenant ; omis, la commande travaille sur la connexion courante}
        {--supplier= : Ne traiter que les BR de ce fournisseur (tp_title)}
        {--payment=0 : Montant à imputer sur les factures, de la plus ancienne à la plus récente}
        {--payment-date= : Date du règlement (défaut : aujourd\'hui)}
        {--payment-method=cash : cash, bank_transfer, cheque, effet}
        {--apport : Contrepasser le règlement par un apport de même montant (fonds personnels)}
        {--apport-label= : Libellé de l\'apport}
        {--dry-run : Montrer ce qui serait fait, sans rien écrire}';

    protected $description = 'Convertit des Bons de Réception en Factures d\'Achat et impute un règlement';

    public function handle(): int
    {
        // Sans argument, on reste sur la connexion en place : c'est ce qui
        // permet de lancer la commande depuis un contexte tenant déjà ouvert,
        // et de la tester sans monter une base tenant.
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
        $dryRun      = (bool) $this->option('dry-run');
        $amount      = (float) $this->option('payment');
        $paymentDate = $this->option('payment-date') ?: now()->toDateString();
        $method      = $this->option('payment-method');

        $supplier = null;
        if ($this->option('supplier')) {
            $supplier = ThirdPartner::where('tp_title', $this->option('supplier'))->first();
            if (!$supplier) {
                $this->error("Fournisseur '{$this->option('supplier')}' introuvable.");
                return self::FAILURE;
            }
        }

        // Un BR déjà facturé a une facture pour enfant : le refacturer
        // doublerait la dette fournisseur.
        $brs = DocumentHeader::where('document_type', 'ReceiptNotePurchase')
            ->whereIn('status', ['confirmed', 'received'])
            ->when($supplier, fn ($q) => $q->where('thirdPartner_id', $supplier->id))
            ->whereDoesntHave('children', fn ($q) => $q->where('document_type', 'InvoicePurchase'))
            ->with(['lignes', 'footer', 'thirdPartner'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        if ($brs->isEmpty()) {
            $this->warn('Aucun BR à facturer (tous déjà facturés ?).');
        }

        $incrementor = DocumentIncrementor::where('di_model', 'InvoicePurchase')->first();
        if (!$incrementor) {
            $this->error("Aucun incrémenteur 'InvoicePurchase' configuré.");
            return self::FAILURE;
        }

        $user = User::orderBy('id')->first();
        $incrService = app(DocumentIncrementorService::class);

        $this->newLine();
        $this->line('── Factures d\'achat ──');

        $invoices = collect();
        $total    = 0.0;

        // Ces factures ont plusieurs semaines : personne n'a besoin d'un mail
        // ni d'une notification push « nouvelle facture » pour chacune.
        DocumentNotificationObserver::$silent = true;

        try {

        foreach ($brs as $br) {
            $ttc = (float) ($br->footer->total_ttc ?? 0);
            $total += $ttc;

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] %s (%s) → facture | %s MAD',
                    $br->reference,
                    $br->issued_at?->format('d/m/Y'),
                    number_format($ttc, 2, ',', ' ')
                ));
                continue;
            }

            $invoice = DB::transaction(function () use ($br, $incrementor, $incrService, $user) {
                $reference = $incrService->consumeNext($incrementor);

                $invoice = DocumentHeader::create([
                    'document_incrementor_id' => $incrementor->id,
                    'reference'               => $reference,
                    'document_type'           => 'InvoicePurchase',
                    'document_title'          => 'Facture Achat',
                    'parent_id'               => $br->id,
                    'thirdPartner_id'         => $br->thirdPartner_id,
                    'company_role'            => $br->company_role,
                    'warehouse_id'            => $br->warehouse_id,
                    'user_id'                 => $user?->id ?? $br->user_id,
                    'status'                  => 'pending',
                    // La facture porte la date du BR, pas celle du traitement.
                    'issued_at'               => $br->issued_at,
                    'due_at'                  => $br->issued_at?->copy()->addDays(60),
                    'notes'                   => $br->notes,
                ]);

                $now = now();
                $lignes = $br->lignes->map(fn ($ligne) => [
                    'document_header_id' => $invoice->id,
                    'product_id'         => $ligne->product_id,
                    'variant_id'         => $ligne->variant_id,
                    'sort_order'         => $ligne->sort_order,
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
                ])->toArray();

                if ($lignes) {
                    DocumentLigne::insert($lignes);
                }

                if ($br->footer) {
                    DocumentFooter::create([
                        'document_header_id' => $invoice->id,
                        'total_ht'           => $br->footer->total_ht,
                        'total_discount'     => $br->footer->total_discount,
                        'total_tax'          => $br->footer->total_tax,
                        'total_ttc'          => $br->footer->total_ttc,
                        'amount_paid'        => 0,
                        'amount_due'         => $br->footer->total_ttc,
                        'payment_method'     => 'credit',
                    ]);
                }

                // Le stock est déjà entré à la réception : la facture ne le
                // remouvemente pas. Le BR passe simplement en « reçu ».
                $br->update(['status' => 'received']);

                return $invoice;
            });

            $invoices->push($invoice);

            $this->info(sprintf(
                '  OK  %s → %s | %s | %s MAD',
                $br->reference,
                $invoice->reference,
                $br->issued_at?->format('d/m/Y'),
                number_format($ttc, 2, ',', ' ')
            ));
        }

        } finally {
            DocumentNotificationObserver::$silent = false;
        }

        $this->line('  Total facturé : ' . number_format($total, 2, ',', ' ') . ' MAD');

        if ($amount <= 0) {
            $this->newLine();
            $this->info('Terminé (aucun règlement demandé).');
            return self::SUCCESS;
        }

        // ── Règlement ─────────────────────────────────────────────
        $this->newLine();
        $this->line('── Règlement ──');

        // En simulation les factures n'existent pas encore : on impute sur les
        // BR qui les produiraient, sinon l'aperçu s'arrêterait sur une erreur
        // au lieu de montrer la répartition.
        $payables = $dryRun
            ? $brs
            : DocumentHeader::where('document_type', 'InvoicePurchase')
                ->when($supplier, fn ($q) => $q->where('thirdPartner_id', $supplier->id))
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
                ->with('footer')
                ->orderBy('issued_at')
                ->orderBy('id')
                ->get();

        if ($payables->isEmpty()) {
            $this->error('Aucune facture impayée à régler.');
            return self::FAILURE;
        }

        $remaining = $amount;

        // Un règlement fournisseur ne se notifie pas au fournisseur : ces mails
        // et WhatsApp partiraient chez LEADER STAR pour une régularisation
        // interne, des mois après les faits.
        Payment::$skipNotification = true;

        try {
            DB::transaction(function () use ($payables, &$remaining, $paymentDate, $method, $dryRun, $user) {
                foreach ($payables as $invoice) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $due     = (float) $invoice->footer->amount_due;
                    $applied = min($remaining, $due);

                    if ($dryRun) {
                        $this->line(sprintf(
                            '  [dry-run] %s : %s MAD sur %s MAD dus',
                            $invoice->reference,
                            number_format($applied, 2, ',', ' '),
                            number_format($due, 2, ',', ' ')
                        ));
                    } else {
                        Payment::create([
                            'document_header_id' => $invoice->id,
                            'amount'             => round($applied, 2),
                            'method'             => $method,
                            'paid_at'            => $paymentDate,
                            'user_id'            => $user?->id ?? $invoice->user_id,
                            'notes'              => 'Régularisation — règlement du ' . date('d/m/Y', strtotime($paymentDate)),
                        ]);

                        $this->info(sprintf(
                            '  OK  %s : %s MAD imputés (restait %s MAD dus)',
                            $invoice->reference,
                            number_format($applied, 2, ',', ' '),
                            number_format($due, 2, ',', ' ')
                        ));
                    }

                    $remaining -= $applied;
                }
            });
        } finally {
            Payment::$skipNotification = false;
        }

        if ($remaining > 0.01) {
            $this->warn('  ' . number_format($remaining, 2, ',', ' ') . ' MAD non imputés (plus de facture due).');
        }

        // ── Apport ────────────────────────────────────────────────
        if ($this->option('apport')) {
            $this->newLine();
            $this->line('── Apport ──');

            $applied = $amount - max(0, $remaining);
            $exit    = $this->recordApport($applied, $paymentDate, $method, $dryRun);
            if ($exit !== self::SUCCESS) {
                return $exit;
            }
        }

        if (!$dryRun && $supplier) {
            $supplier->recalculateEncours();
            $this->newLine();
            $this->info('Encours ' . $supplier->tp_title . ' : '
                . number_format($supplier->fresh()->encours_actuel, 2, ',', ' ') . ' MAD');
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . 'Terminé.');

        return self::SUCCESS;
    }

    /**
     * Contrepartie du règlement payé sur fonds personnels : une recette de même
     * montant, même date, même compte. Les deux lignes s'annulent au solde de
     * caisse et laissent l'apport lisible dans la ventilation par poste.
     */
    private function recordApport(float $amount, string $date, string $method, bool $dryRun): int
    {
        $account = CashAccount::where('ca_payment_method', $method)->first();

        if (!$account) {
            $this->error("Aucun compte de trésorerie n'est rattaché au moyen de paiement '{$method}'.");
            return self::FAILURE;
        }

        $category = CashCategory::where('cc_title', 'Apport')->first();
        $label    = $this->option('apport-label')
            ?: 'Apport personnel — règlement fournisseur du ' . date('d/m/Y', strtotime($date));

        if ($dryRun) {
            $this->line(sprintf(
                '  [dry-run] recette %s MAD sur %s | %s',
                number_format($amount, 2, ',', ' '),
                $account->ca_title,
                $label
            ));
            return self::SUCCESS;
        }

        $transaction = CashTransaction::create([
            'cash_account_id'  => $account->id,
            'cash_category_id' => $category?->id,
            'ct_direction'     => 'in',
            'ct_amount'        => $amount,
            'ct_date'          => $date,
            'ct_label'         => $label,
            'ct_method'        => $method,
            'ct_notes'         => "Fonds personnels du dirigeant : la caisse de l'entreprise n'a pas été débitée. "
                                . 'Cette recette compense le règlement fournisseur de même montant.',
        ]);

        $this->info(sprintf(
            '  OK  %s : recette %s MAD sur %s',
            $transaction->ct_code,
            number_format($amount, 2, ',', ' '),
            $account->ca_title
        ));

        return self::SUCCESS;
    }
}
