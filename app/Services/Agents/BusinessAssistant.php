<?php

namespace App\Services\Agents;

use App\Models\AgentEvent;
use App\Models\PaymentReminder;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Les commandes de LECTURE de l'orchestrateur sur l'activité de l'entreprise : le point de la journée, ce qui attend
 * une validation, les ventes, les factures échues, les devis sans suite, les bons de livraison non facturés, les
 * encaissements, les soldes de trésorerie et les sessions de caisse.
 *
 * Garde-fous : lecture seule (aucune écriture, aucun brouillon), aucun appel à un modèle de langage (les chiffres
 * viennent directement de la base, rien n'est envoyé à Anthropic), listes plafonnées à 10 lignes, montants arrondis
 * au centime. Les colonnes sensibles (mots de passe, jetons, codes PIN, coordonnées bancaires) ne sont jamais lues.
 */
class BusinessAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];
    private const INCOME_TYPES = ['InvoiceSale', 'TicketSale', 'DeliveryNote', 'CustomerOrder'];
    private const OUTGOING_TYPES = ['InvoicePurchase', 'PurchaseOrder', 'ReceiptNotePurchase'];

    // ── Le point de la journée ───────────────────────────────────────

    public function daySummary(): array
    {
        $today = $this->today();
        $sales = $this->salesTotals($today, $today);
        $cashIn = $this->incomeByMethod($today, $today);
        $overdue = $this->overdue(0);
        $low = (new AgentDataTools())->run('stock_bas', [], ['stock']);
        $pending = $this->pendingEvents();
        $reminders = PaymentReminder::where('status', PaymentReminder::STATUS_DRAFT)->count();
        $openSessions = DB::table('pos_sessions')->whereNull('closed_at')->count();

        $lines = [
            'Le point du ' . $today->locale('fr')->isoFormat('dddd D MMMM YYYY') . ' :',
            '',
            "• Ventes : {$sales['n']} facture(s) ou ticket(s) pour " . $this->money($sales['ttc']) . ' TTC',
            '• Encaissements : ' . $this->money($cashIn['total']) . ($cashIn['by'] !== [] ? ' (' . implode(', ', array_map(fn ($m, $v) => self::method($m) . ' ' . $this->money($v), array_keys($cashIn['by']), $cashIn['by'])) . ')' : ''),
            '• Factures échues : ' . ($overdue['n'] === 0 ? 'aucune' : "{$overdue['n']} pour " . $this->money($overdue['due']) . " dus (la plus ancienne : {$overdue['oldest']})"),
            '• Stock bas : ' . ($low['produits_concernes'] === 0 ? 'aucun produit' : "{$low['produits_concernes']} produit(s) à {$low['seuil']} pièce(s) ou moins, dont {$low['en_rupture']} en rupture"),
            '• À valider : ' . (count($pending) + $reminders === 0 ? 'rien en attente' : count($pending) . ' proposition(s)' . ($reminders > 0 ? " et {$reminders} relance(s)" : '')),
            '• Caisses ouvertes : ' . ($openSessions === 0 ? 'aucune' : $openSessions),
        ];

        $suggestions = [];
        $count = count($pending) + $reminders;
        $count > 0 && $suggestions[] = ['label' => 'Que dois-je valider ?', 'text' => 'que dois-je valider ?'];
        $overdue['n'] > 0 && $suggestions[] = ['label' => 'Voir les factures échues', 'text' => 'factures échues'];
        $suggestions[] = ['label' => 'Chiffre d\'affaires du mois', 'text' => 'chiffre d\'affaires du mois'];

        return $this->reply(implode("\n", $lines), $suggestions);
    }

    // ── Ce qui attend une validation ─────────────────────────────────

    public function pendingValidations(): array
    {
        $events = $this->pendingEvents();
        $reminders = PaymentReminder::where('status', PaymentReminder::STATUS_DRAFT)->count();
        if ($events === [] && $reminders === 0) {
            return $this->reply('Rien n\'attend votre validation.');
        }

        $lines = [];
        foreach (array_slice($events, 0, self::LIST) as $e) {
            $studio = in_array($e['type'], ['agent_recrutement', 'routine_proposition', 'consigne_proposition', 'comptes_agents', 'conception_proposition'], true);
            $lines[] = "• #{$e['id']} — {$e['text']} ({$e['age']}) → « applique " . ($studio ? 'la proposition' : 'le lot') . " #{$e['id']} » ou « ignore … »";
        }
        $rest = count($events) - count($lines);
        $body = (count($events) > 0 ? count($events) . " proposition(s) en attente, les plus anciennes d'abord :\n\n" . implode("\n", $lines) . ($rest > 0 ? "\n… et {$rest} autre(s)." : '') : '')
            . ($reminders > 0 ? ($events !== [] ? "\n\n" : '') . "{$reminders} relance(s) de paiement en brouillon à valider." : '');

        return $this->reply($body, $reminders > 0 ? [['label' => 'Relances à valider', 'text' => 'relances à valider']] : []);
    }

    // ── Ventes ───────────────────────────────────────────────────────

    /** « chiffre d'affaires du mois », « ventes de la semaine », « ventes d'hier », « ventes des 15 derniers jours ». @param string $n phrase normalisée */
    public function sales(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $days = $from->diffInDays($to) + 1;
        $now = $this->salesTotals($from, $to);
        $before = $this->salesTotals($from->copy()->subDays($days), $from->copy()->subDay());

        $top = $this->invoiceBase(self::SALES_TYPES, $from, $to)->join('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->groupBy('t.id', 't.tp_title')->selectRaw('t.tp_title AS client, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('ttc')->limit(5)->get();
        $topProducts = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')
            ->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('l.status', 'active')->where('l.line_type', 'product')->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])
            ->groupBy('l.designation')->selectRaw('l.designation AS produit, SUM(l.quantity) AS qte, SUM(l.total_ligne_ht) AS ht')->orderByDesc('ht')->limit(5)->get();
        $credit = $this->invoiceBase(['CreditNoteSale'], $from, $to)->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->first();

        $lines = ["Ventes {$label} :", '', "• {$now['n']} facture(s) ou ticket(s) : " . $this->money($now['ht']) . ' HT, ' . $this->money($now['ttc']) . ' TTC'];
        if ($before['ttc'] > 0) {
            $delta = round(($now['ttc'] - $before['ttc']) / $before['ttc'] * 100);
            $lines[] = '• Période précédente (même durée) : ' . $this->money($before['ttc']) . ' TTC, soit ' . ($delta >= 0 ? '+' : '') . $delta . ' %';
        }
        if ((int) $credit->n > 0) {
            $lines[] = "• Avoirs sur la période : {$credit->n} pour " . $this->money((float) $credit->ttc) . ' (non déduits ci-dessus)';
        }
        if ($top->isNotEmpty()) {
            $lines[] = "\nPrincipaux clients :";
            foreach ($top as $r) {
                $lines[] = "• {$r->client} — {$r->n} vente(s), " . $this->money((float) $r->ttc);
            }
        }
        if ($topProducts->isNotEmpty()) {
            $lines[] = "\nPrincipaux produits (HT) :";
            foreach ($topProducts as $r) {
                $lines[] = "• {$r->produit} — " . rtrim(rtrim(number_format((float) $r->qte, 2, ',', ' '), '0'), ',') . ' vendu(s), ' . $this->money((float) $r->ht);
            }
        }
        if ($now['n'] === 0) {
            $lines[] = "\nAucune vente sur cette période (brouillons et annulées exclus).";
        }

        return $this->reply(implode("\n", $lines));
    }

    /** « factures échues », « factures impayées depuis plus de 30 jours ». @param string $n phrase normalisée */
    public function overdueInvoices(string $n): array
    {
        $min = preg_match('/(?:plus de|depuis|au moins)\s*(\d{1,3})\s*j/', $n, $m) ? max(0, min(365, (int) $m[1])) : 0;
        $tot = $this->overdue($min);
        if ($tot['n'] === 0) {
            return $this->reply($min > 0 ? "Aucune facture échue depuis plus de {$min} jour(s)." : 'Aucune facture de vente échue : tout est à jour.');
        }

        $rows = $this->overdueBase($min)->join('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->selectRaw('d.reference, t.tp_title AS client, d.due_at AS echeance, f.amount_due AS du')->orderByDesc('f.amount_due')->limit(self::LIST)->get();
        $lines = $rows->map(fn ($r) => "• {$r->reference} — {$r->client} — échue le " . Carbon::parse($r->echeance)->format('d/m/Y') . ' — ' . $this->money((float) $r->du))->implode("\n");

        return $this->reply(
            "{$tot['n']} facture(s) échue(s)" . ($min > 0 ? " depuis plus de {$min} jour(s)" : '') . ', ' . $this->money($tot['due']) . " dus (la plus ancienne : {$tot['oldest']}).\n\nLes plus importantes :\n{$lines}",
            [['label' => 'Contrôler les encaissements', 'text' => 'contrôle les encaissements']],
        );
    }

    /** « devis sans suite depuis 10 jours ». @param string $n phrase normalisée */
    public function staleQuotes(string $n): array
    {
        $days = preg_match('/(\d{1,3})\s*j/', $n, $m) ? max(1, min(365, (int) $m[1])) : 10;
        $rows = $this->openDocs('QuoteSale', ['confirmed', 'sent', 'pending'], $days);

        return $this->docList($rows, "devis sans suite depuis plus de {$days} jour(s)", 'Aucun devis sans suite : tous les devis récents sont traités ou convertis.');
    }

    public function unbilledDeliveries(): array
    {
        $rows = $this->openDocs('DeliveryNote', ['confirmed', 'sent', 'delivered', 'pending'], 0);

        return $this->docList($rows, 'bon(s) de livraison non facturé(s)', 'Tous les bons de livraison sont facturés (ou annulés).');
    }

    // ── Trésorerie et caisse ─────────────────────────────────────────

    /** « encaissements du jour / de la semaine / du mois ». @param string $n phrase normalisée */
    public function income(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'day');
        $in = $this->incomeByMethod($from, $to);
        $out = DB::table('payments as p')->join('document_headers as d', 'd.id', '=', 'p.document_header_id')->whereNull('d.deleted_at')
            ->whereIn('d.document_type', self::OUTGOING_TYPES)->whereBetween('p.paid_at', [$from->toDateString(), $to->toDateString()])->sum('p.amount');

        $lines = ["Encaissements {$label} : " . $this->money($in['total']) . " ({$in['n']} paiement(s))"];
        foreach ($in['by'] as $method => $v) {
            $lines[] = '• ' . self::method($method) . ' : ' . $this->money($v);
        }
        $lines[] = "\nPaiements fournisseurs sur la même période : " . $this->money((float) $out) . '.';
        $in['n'] === 0 && $lines[] = 'Aucun paiement de client enregistré sur cette période.';

        return $this->reply(implode("\n", $lines));
    }

    public function balances(): array
    {
        $accounts = DB::table('cash_accounts')->whereNull('deleted_at')->where('ca_status', true)->orderBy('id')->get(['id', 'ca_title', 'ca_type', 'ca_initial_balance']);
        if ($accounts->isEmpty()) {
            return $this->reply('Aucun compte de trésorerie actif.', [['label' => 'Ouvrir la trésorerie', 'text' => 'où est la trésorerie']]);
        }
        $moves = DB::table('cash_transactions')->whereNull('deleted_at')->where('ct_status', 'active')
            ->groupBy('cash_account_id')->selectRaw("cash_account_id, SUM(CASE WHEN ct_direction = 'in' THEN ct_amount ELSE -ct_amount END) AS net")->pluck('net', 'cash_account_id');

        $total = 0.0;
        $lines = [];
        foreach ($accounts as $a) {
            $balance = round((float) $a->ca_initial_balance + (float) ($moves[$a->id] ?? 0), 2);
            $total += $balance;
            $lines[] = "• {$a->ca_title} — " . $this->money($balance);
        }

        return $this->reply("Soldes de trésorerie :\n\n" . implode("\n", $lines) . "\n\nTotal : " . $this->money($total) . '.');
    }

    public function cashSessions(): array
    {
        $now = $this->today();
        $base = fn () => DB::table('pos_sessions as s')->leftJoin('users as u', 'u.id', '=', 's.user_id');
        $stale = $base()->whereNull('s.closed_at')->where('s.opened_at', '<', now()->subDay())->orderBy('s.opened_at')->limit(self::LIST)->get(['s.id', 's.opened_at', 'u.name']);
        $open = $base()->whereNull('s.closed_at')->count();
        $toValidate = $base()->whereNotNull('s.closed_at')->whereNull('s.validated_at')->orderBy('s.closed_at')->limit(self::LIST)->get(['s.id', 's.closed_at', 's.cash_difference', 'u.name']);
        $gaps = $base()->whereNotNull('s.closed_at')->where('s.cash_difference', '!=', 0)->where('s.closed_at', '>=', $now->copy()->startOfMonth())->orderByDesc(DB::raw('ABS(s.cash_difference)'))->limit(self::LIST)->get(['s.id', 's.closed_at', 's.cash_difference', 's.variance_reason', 'u.name']);

        $lines = ["Sessions de caisse : {$open} ouverte(s)."];
        if ($stale->isNotEmpty()) {
            $lines[] = "\nOuvertes depuis plus de 24 h :";
            foreach ($stale as $s) {
                $lines[] = "• Session #{$s->id} — " . ($s->name ?? '?') . ' — ouverte le ' . Carbon::parse($s->opened_at)->format('d/m H:i');
            }
        }
        if ($toValidate->isNotEmpty()) {
            $lines[] = "\nFermées, à valider :";
            foreach ($toValidate as $s) {
                $lines[] = "• Session #{$s->id} — " . ($s->name ?? '?') . ' — fermée le ' . Carbon::parse($s->closed_at)->format('d/m H:i') . ($s->cash_difference != 0 ? ', écart ' . $this->money((float) $s->cash_difference) : '');
            }
        }
        if ($gaps->isNotEmpty()) {
            $lines[] = "\nÉcarts de caisse du mois :";
            foreach ($gaps as $s) {
                $lines[] = "• Session #{$s->id} — " . ($s->name ?? '?') . ' — ' . $this->money((float) $s->cash_difference) . ($s->variance_reason ? " ({$s->variance_reason})" : '');
            }
        }
        if ($stale->isEmpty() && $toValidate->isEmpty() && $gaps->isEmpty()) {
            $lines[] = 'Rien à signaler : pas de session oubliée, rien à valider, aucun écart ce mois-ci.';
        }

        return $this->reply(implode("\n", $lines));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** Le fuseau de l'entreprise (réglage locale.timezone). */
    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    /**
     * La période demandée dans la phrase. @return array{0: Carbon, 1: Carbon, 2: string} début, fin (inclus), libellé
     */
    private function period(string $n, string $default): array
    {
        $today = $this->today();

        return match (true) {
            (bool) preg_match('/\bhier\b/', $n)                                => [$today->copy()->subDay(), $today->copy()->subDay(), 'd\'hier'],
            (bool) preg_match('/(\d{1,3})\s*derniers?\s*jours|(\d{1,3})\s*jours/', $n, $m) => (function () use ($m, $today) {
                $d = max(1, min(366, (int) ($m[1] !== '' ? $m[1] : $m[2])));

                return [$today->copy()->subDays($d - 1), $today, "des {$d} derniers jours"];
            })(),
            (bool) preg_match('/semaine/', $n)                                 => [$today->copy()->startOfWeek(), $today, 'de la semaine (depuis lundi)'],
            (bool) preg_match('/\bmois\b/', $n)                                => [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'],
            (bool) preg_match('/\ban(nee)?\b/', $n)                            => [$today->copy()->startOfYear(), $today, 'de l\'année'],
            (bool) preg_match('/aujourd|du jour|journee|\bjour\b/', $n)        => [$today, $today, 'd\'aujourd\'hui'],
            default => $default === 'month' ? [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'] : [$today, $today, 'd\'aujourd\'hui'],
        };
    }

    /** @param array<int, string> $types */
    private function invoiceBase(array $types, Carbon $from, Carbon $to): \Illuminate\Database\Query\Builder
    {
        return DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('d.deleted_at')->whereIn('d.document_type', $types)->whereNotIn('d.status', ['draft', 'cancelled'])
            ->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()]);
    }

    /** @return array{n: int, ht: float, ttc: float} */
    private function salesTotals(Carbon $from, Carbon $to): array
    {
        $r = $this->invoiceBase(self::SALES_TYPES, $from, $to)->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.total_ht), 0) AS ht, COALESCE(SUM(f.total_ttc), 0) AS ttc')->first();

        return ['n' => (int) $r->n, 'ht' => round((float) $r->ht, 2), 'ttc' => round((float) $r->ttc, 2)];
    }

    /** @return array{n: int, total: float, by: array<string, float>} */
    private function incomeByMethod(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('payments as p')->join('document_headers as d', 'd.id', '=', 'p.document_header_id')->whereNull('d.deleted_at')
            ->whereIn('d.document_type', self::INCOME_TYPES)->whereBetween('p.paid_at', [$from->toDateString(), $to->toDateString()])
            ->groupBy('p.method')->selectRaw('p.method, COUNT(*) AS n, SUM(p.amount) AS total')->orderByDesc('total')->get();

        return [
            'n'     => (int) $rows->sum('n'),
            'total' => round((float) $rows->sum('total'), 2),
            'by'    => $rows->mapWithKeys(fn ($r) => [(string) $r->method => round((float) $r->total, 2)])->all(),
        ];
    }

    private function overdueBase(int $minDays): \Illuminate\Database\Query\Builder
    {
        return DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoiceSale')->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('f.amount_due', '>', 0)->whereNotNull('d.due_at')
            ->where('d.due_at', '<', $this->today()->copy()->subDays($minDays)->addDay()->toDateString());
    }

    /** @return array{n: int, due: float, oldest: string} */
    private function overdue(int $minDays): array
    {
        $r = $this->overdueBase($minDays)->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.amount_due), 0) AS due, MIN(d.due_at) AS oldest')->first();

        return ['n' => (int) $r->n, 'due' => round((float) $r->due, 2), 'oldest' => $r->oldest ? Carbon::parse($r->oldest)->format('d/m/Y') : '—'];
    }

    /** @param array<int, string> $statuses */
    private function openDocs(string $type, array $statuses, int $olderThanDays): \Illuminate\Support\Collection
    {
        return DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->where('d.document_type', $type)->whereIn('d.status', $statuses)
            ->when($olderThanDays > 0, fn ($q) => $q->where('d.issued_at', '<', $this->today()->copy()->subDays($olderThanDays)->addDay()->toDateString()))
            ->orderBy('d.issued_at')->limit(200)->get(['d.reference', 'd.issued_at', 'd.status', 't.tp_title AS client', 'f.total_ttc']);
    }

    private function docList(\Illuminate\Support\Collection $rows, string $what, string $none): array
    {
        if ($rows->isEmpty()) {
            return $this->reply($none);
        }
        $lines = $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans client') . ' — du ' . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc))->implode("\n");

        return $this->reply("{$rows->count()} {$what}, pour " . $this->money((float) $rows->sum('total_ttc')) . " TTC. Les plus anciens :\n\n{$lines}" . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    /** @return array<int, array{id: int, type: string, text: string, age: string}> propositions en attente, les plus anciennes d'abord */
    private function pendingEvents(): array
    {
        return AgentEvent::where('status', AgentEvent::STATUS_ROUTED)
            ->where(fn ($q) => $q->where('type', 'like', 'catalogue\_%')->orWhereIn('type', ['agent_recrutement', 'routine_proposition', 'consigne_proposition', 'comptes_agents', 'conception_proposition']))
            ->orderBy('id')->get()
            ->map(fn (AgentEvent $e) => ['id' => $e->id, 'type' => $e->type, 'text' => (string) ($e->payload['text'] ?? $e->type), 'age' => 'depuis ' . $e->created_at->locale('fr')->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE])])->all();
    }

    private static function method(string $m): string
    {
        return ['cash' => 'espèces', 'bank_transfer' => 'virement', 'cheque' => 'chèque', 'effet' => 'effet', 'credit' => 'crédit', 'card' => 'carte'][$m] ?? $m;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'business', 'suggestions' => $suggestions ?: null], fn ($v) => $v !== null)];
    }
}
