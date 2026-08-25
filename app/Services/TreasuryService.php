<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashRecurrence;
use App\Models\CashTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cœur du module Trésorerie.
 *
 * Le solde réel d'une entreprise n'est pas dans une seule table : il naît des
 * écritures saisies à la main (loyer, salaires, apports) ET des règlements déjà
 * enregistrés sur les factures et les tickets. Ce service est le seul endroit
 * où les deux sources sont réunies — les recopier dans une table unique aurait
 * fait diverger les chiffres à la première suppression de paiement.
 *
 * Deux règles gouvernent la lecture des `payments` :
 *   1. Le sens vient du type de document : tout ce qui porte "Purchase" sort de
 *      la caisse, le reste y entre.
 *   2. Les règlements de méthode `credit` sont ignorés — ce sont des
 *      reconnaissances de dette POS, pas de l'argent encaissé. Même règle que
 *      `ThirdPartner::recalculateEncours()`.
 */
class TreasuryService
{
    /** Méthodes de paiement qui ne représentent pas un mouvement d'argent. */
    private const NON_CASH_METHODS = ['credit'];

    // ── Journal ───────────────────────────────────────────────────

    /**
     * Journal unifié, paginé, du plus récent au plus ancien.
     *
     * @param array{from?:string,to?:string,direction?:string,account_id?:int,category_id?:int,source?:string,search?:string} $filters
     */
    public function journal(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $source = $filters['source'] ?? 'all';

        $queries = [];
        if ($source !== 'payment') {
            $queries[] = $this->manualJournalQuery($filters);
        }
        if ($source !== 'manual') {
            $queries[] = $this->paymentJournalQuery($filters);
        }

        if (empty($queries)) {
            $queries[] = $this->manualJournalQuery($filters);
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'journal')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function manualJournalQuery(array $filters): QueryBuilder
    {
        $query = DB::table('cash_transactions as ct')
            ->leftJoin('cash_accounts as ca', 'ca.id', '=', 'ct.cash_account_id')
            ->leftJoin('cash_categories as cc', 'cc.id', '=', 'ct.cash_category_id')
            ->leftJoin('third_partners as tp', 'tp.id', '=', 'ct.thirdPartner_id')
            ->leftJoin('document_headers as dh', 'dh.id', '=', 'ct.document_header_id')
            ->whereNull('ct.deleted_at')
            ->selectRaw("
                'manual' as source,
                ct.id as id,
                ct.ct_code as code,
                ct.ct_date as date,
                ct.ct_direction as direction,
                ct.ct_amount as amount,
                ct.ct_label as label,
                ct.ct_method as method,
                ct.cash_account_id as account_id,
                ca.ca_title as account_title,
                cc.cc_title as category_title,
                tp.tp_title as partner_title,
                dh.reference as document_reference,
                ct.ct_status as status,
                ct.ct_attachment_path as attachment_path,
                ct.ct_transfer_group as transfer_group
            ");

        $this->applyCommonFilters($query, $filters, 'ct.ct_date', 'ct.ct_direction', 'ct.cash_account_id');

        if (!empty($filters['category_id'])) {
            $query->where('ct.cash_category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('ct.ct_label', 'like', $term)
                  ->orWhere('ct.ct_code', 'like', $term)
                  ->orWhere('ct.ct_reference', 'like', $term)
                  ->orWhere('tp.tp_title', 'like', $term);
            });
        }

        return $query;
    }

    private function paymentJournalQuery(array $filters): QueryBuilder
    {
        $query = DB::table('payments as p')
            ->join('document_headers as dh', 'dh.id', '=', 'p.document_header_id')
            ->leftJoin('third_partners as tp', 'tp.id', '=', 'dh.thirdPartner_id')
            ->leftJoin('cash_accounts as ca', function ($join) {
                $join->on('ca.ca_payment_method', '=', 'p.method')
                     ->whereNull('ca.deleted_at');
            })
            ->whereNotIn('p.method', self::NON_CASH_METHODS)
            ->whereNull('dh.deleted_at')
            ->selectRaw("
                'payment' as source,
                p.id as id,
                p.payment_code as code,
                p.paid_at as date,
                " . $this->paymentDirectionSql() . " as direction,
                p.amount as amount,
                CONCAT('Règlement ', dh.reference) as label,
                p.method as method,
                ca.id as account_id,
                ca.ca_title as account_title,
                NULL as category_title,
                tp.tp_title as partner_title,
                dh.reference as document_reference,
                'active' as status,
                NULL as attachment_path,
                NULL as transfer_group
            ");

        $this->applyCommonFilters($query, $filters, 'p.paid_at', $this->paymentDirectionSql(), 'ca.id', rawDirection: true);

        // Un règlement n'a pas de catégorie de dépense : filtrer dessus
        // l'exclut, plutôt que de renvoyer des lignes sans rapport.
        if (!empty($filters['category_id'])) {
            $query->whereRaw('1 = 0');
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('dh.reference', 'like', $term)
                  ->orWhere('p.payment_code', 'like', $term)
                  ->orWhere('p.reference', 'like', $term)
                  ->orWhere('tp.tp_title', 'like', $term);
            });
        }

        return $query;
    }

    /** SQL du sens d'un règlement, déduit du type de document. */
    private function paymentDirectionSql(): string
    {
        return "(CASE WHEN dh.document_type LIKE '%Purchase%' THEN 'out' ELSE 'in' END)";
    }

    private function applyCommonFilters(
        QueryBuilder $query,
        array $filters,
        string $dateColumn,
        string $directionColumn,
        string $accountColumn,
        bool $rawDirection = false,
    ): void {
        if (!empty($filters['from'])) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }
        if (!empty($filters['direction'])) {
            $rawDirection
                ? $query->whereRaw("{$directionColumn} = ?", [$filters['direction']])
                : $query->where($directionColumn, $filters['direction']);
        }
        if (!empty($filters['account_id'])) {
            $query->where($accountColumn, $filters['account_id']);
        }
    }

    // ── Soldes & synthèse ─────────────────────────────────────────

    /**
     * Solde de chaque compte : solde initial + écritures + règlements imputés.
     * Le solde est toujours calculé depuis l'origine — un solde « sur la
     * période » n'aurait aucun sens en caisse.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function accountBalances(): Collection
    {
        $manual = DB::table('cash_transactions')
            ->whereNull('deleted_at')
            ->where('ct_status', 'active')
            ->groupBy('cash_account_id')
            ->selectRaw("cash_account_id, SUM(CASE WHEN ct_direction = 'in' THEN ct_amount ELSE -ct_amount END) as net")
            ->pluck('net', 'cash_account_id');

        $payments = DB::table('payments as p')
            ->join('document_headers as dh', 'dh.id', '=', 'p.document_header_id')
            ->join('cash_accounts as ca', 'ca.ca_payment_method', '=', 'p.method')
            ->whereNotIn('p.method', self::NON_CASH_METHODS)
            ->whereNull('dh.deleted_at')
            ->whereNull('ca.deleted_at')
            ->groupBy('ca.id')
            ->selectRaw("ca.id as account_id, SUM(CASE WHEN dh.document_type LIKE '%Purchase%' THEN -p.amount ELSE p.amount END) as net")
            ->pluck('net', 'account_id');

        return CashAccount::orderBy('ca_title')->get()->map(function (CashAccount $account) use ($manual, $payments) {
            $initial     = (float) $account->ca_initial_balance;
            $manualNet   = (float) ($manual[$account->id] ?? 0);
            $paymentsNet = (float) ($payments[$account->id] ?? 0);

            return [
                'id'              => $account->id,
                'ca_title'        => $account->ca_title,
                'ca_code'         => $account->ca_code,
                'ca_type'         => $account->ca_type,
                'ca_status'       => $account->ca_status,
                'initial_balance' => round($initial, 2),
                'manual_net'      => round($manualNet, 2),
                'payments_net'    => round($paymentsNet, 2),
                'balance'         => round($initial + $manualNet + $paymentsNet, 2),
            ];
        });
    }

    /**
     * Synthèse d'une période : entrées, sorties, résultat net, et la
     * ventilation par catégorie qui sert de compte de résultat simplifié.
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $manual = DB::table('cash_transactions')
            ->whereNull('deleted_at')
            ->where('ct_status', 'active')
            ->when($from, fn ($q) => $q->whereDate('ct_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('ct_date', '<=', $to))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN ct_direction = 'in'  THEN ct_amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN ct_direction = 'out' THEN ct_amount ELSE 0 END), 0) as total_out
            ")
            ->first();

        $payments = DB::table('payments as p')
            ->join('document_headers as dh', 'dh.id', '=', 'p.document_header_id')
            ->whereNotIn('p.method', self::NON_CASH_METHODS)
            ->whereNull('dh.deleted_at')
            ->when($from, fn ($q) => $q->whereDate('p.paid_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('p.paid_at', '<=', $to))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN dh.document_type LIKE '%Purchase%' THEN 0 ELSE p.amount END), 0) as total_in,
                COALESCE(SUM(CASE WHEN dh.document_type LIKE '%Purchase%' THEN p.amount ELSE 0 END), 0) as total_out
            ")
            ->first();

        $manualIn   = (float) ($manual->total_in ?? 0);
        $manualOut  = (float) ($manual->total_out ?? 0);
        $paymentIn  = (float) ($payments->total_in ?? 0);
        $paymentOut = (float) ($payments->total_out ?? 0);

        $totalIn  = $manualIn + $paymentIn;
        $totalOut = $manualOut + $paymentOut;

        return [
            'from'            => $from,
            'to'              => $to,
            'total_in'        => round($totalIn, 2),
            'total_out'       => round($totalOut, 2),
            'net'             => round($totalIn - $totalOut, 2),
            'manual_in'       => round($manualIn, 2),
            'manual_out'      => round($manualOut, 2),
            'payments_in'     => round($paymentIn, 2),
            'payments_out'    => round($paymentOut, 2),
            'accounts'        => $this->accountBalances()->values(),
            'total_balance'   => round($this->accountBalances()->sum('balance'), 2),
            'by_category'     => $this->byCategory($from, $to),
        ];
    }

    /**
     * Ventilation des écritures manuelles par poste. Les règlements documents
     * n'y figurent pas : ils n'ont pas de catégorie de dépense, et les compter
     * dans une ligne « Non catégorisé » écraserait la lecture des vrais postes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byCategory(?string $from = null, ?string $to = null): array
    {
        return DB::table('cash_transactions as ct')
            ->leftJoin('cash_categories as cc', 'cc.id', '=', 'ct.cash_category_id')
            ->whereNull('ct.deleted_at')
            ->where('ct.ct_status', 'active')
            ->when($from, fn ($q) => $q->whereDate('ct.ct_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('ct.ct_date', '<=', $to))
            ->groupBy('ct.cash_category_id', 'cc.cc_title', 'ct.ct_direction')
            ->selectRaw("
                ct.cash_category_id as category_id,
                COALESCE(cc.cc_title, 'Non catégorisé') as category_title,
                ct.ct_direction as direction,
                SUM(ct.ct_amount) as total,
                COUNT(*) as entries
            ")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id'    => $row->category_id,
                'category_title' => $row->category_title,
                'direction'      => $row->direction,
                'total'          => round((float) $row->total, 2),
                'entries'        => (int) $row->entries,
            ])
            ->all();
    }

    // ── Virements ─────────────────────────────────────────────────

    /**
     * Virement entre deux comptes : une sortie et une entrée liées par le même
     * `ct_transfer_group`, pour qu'on puisse les annuler ensemble et ne jamais
     * les compter comme un résultat (elles s'annulent dans le net).
     *
     * @return array{0: CashTransaction, 1: CashTransaction}
     */
    public function transfer(array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $group = (string) Str::uuid();
            $label = $data['label'] ?? 'Virement interne';

            $out = CashTransaction::create([
                'cash_account_id'   => $data['from_account_id'],
                'ct_direction'      => 'out',
                'ct_amount'         => $data['amount'],
                'ct_date'           => $data['date'],
                'ct_label'          => $label,
                'ct_method'         => $data['method'] ?? null,
                'ct_transfer_group' => $group,
                'ct_notes'          => $data['notes'] ?? null,
                'user_id'           => $userId,
            ]);

            $in = CashTransaction::create([
                'cash_account_id'   => $data['to_account_id'],
                'ct_direction'      => 'in',
                'ct_amount'         => $data['amount'],
                'ct_date'           => $data['date'],
                'ct_label'          => $label,
                'ct_method'         => $data['method'] ?? null,
                'ct_transfer_group' => $group,
                'ct_notes'          => $data['notes'] ?? null,
                'user_id'           => $userId,
            ]);

            return [$out, $in];
        });
    }

    // ── Récurrences ───────────────────────────────────────────────

    /**
     * Matérialise toutes les échéances dues jusqu'à $upTo.
     *
     * Une récurrence en retard rattrape ses échéances une par une plutôt que
     * d'en produire une seule : un loyer oublié trois mois doit laisser trois
     * lignes en caisse, pas une.
     *
     * @return array{created:int, recurrences:int}
     */
    public function generateDueRecurrences(?string $upTo = null, bool $dryRun = false): array
    {
        $limit   = CarbonImmutable::parse($upTo ?: now()->toDateString())->startOfDay();
        $created = 0;
        $touched = 0;

        $due = CashRecurrence::due($limit->toDateString())->get();

        foreach ($due as $recurrence) {
            $touched++;
            $next  = CarbonImmutable::parse($recurrence->cr_next_run_at)->startOfDay();
            $guard = 0;

            while ($next->lessThanOrEqualTo($limit) && $guard++ < 500) {
                if ($recurrence->cr_end_date && $next->greaterThan(CarbonImmutable::parse($recurrence->cr_end_date))) {
                    break;
                }

                if (!$dryRun) {
                    CashTransaction::create([
                        'cash_account_id'    => $recurrence->cash_account_id,
                        'cash_category_id'   => $recurrence->cash_category_id,
                        'cash_recurrence_id' => $recurrence->id,
                        'ct_direction'       => $recurrence->cr_direction,
                        'ct_amount'          => $recurrence->cr_amount,
                        'ct_date'            => $next->toDateString(),
                        'ct_label'           => $recurrence->cr_label,
                        'ct_method'          => $recurrence->cr_method,
                        'thirdPartner_id'    => $recurrence->thirdPartner_id,
                        'ct_notes'           => $recurrence->cr_notes,
                    ]);
                }

                $created++;
                $next = $recurrence->nextOccurrenceAfter($next);
            }

            if (!$dryRun) {
                $recurrence->update(['cr_next_run_at' => $next->toDateString()]);
            }
        }

        return ['created' => $created, 'recurrences' => $touched];
    }
}
