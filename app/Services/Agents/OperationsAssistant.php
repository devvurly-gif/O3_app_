<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Troisième lot de lectures de l'orchestrateur : les achats (par fournisseur, commandes en attente, factures à payer,
 * prix d'achat en hausse, produits sans fournisseur, fournisseur le moins cher), les remises et prix de vente
 * inhabituels, les factures annulées, la trésorerie (dépenses par catégorie, échéances récurrentes, dépenses sans
 * justificatif), l'activité récente, les utilisateurs, la boutique en ligne et les promotions.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. Le journal d'activité n'est lu que par son libellé : jamais ses
 * propriétés (qui peuvent contenir des valeurs sensibles) ; les mots de passe et jetons ne sont jamais lus.
 */
class OperationsAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];

    // ── Achats ───────────────────────────────────────────────────────

    /** « achats du mois par fournisseur ». @param string $n phrase normalisée */
    public function purchasesBySupplier(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoicePurchase')->whereNotIn('d.status', ['draft', 'cancelled'])->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])
            ->groupBy('t.id', 't.tp_title')->selectRaw('COALESCE(t.tp_title, \'Sans fournisseur\') AS tiers, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune facture d'achat {$label} (brouillons et annulées exclus).");
        }

        return $this->reply("Achats {$label} : {$rows->sum('n')} facture(s), " . $this->money((float) $rows->sum('ttc')) . " TTC.\n\nPar fournisseur :\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->tiers} — {$r->n} facture(s), " . $this->money((float) $r->ttc))->implode("\n") . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    public function pendingPurchaseOrders(): array
    {
        $rows = $this->docs('PurchaseOrder', ['confirmed', 'sent', 'pending']);

        return $this->docList($rows, 'bon(s) de commande fournisseur en attente de réception', 'Aucun bon de commande fournisseur en attente de réception.');
    }

    /** « factures fournisseurs à payer », « échéances fournisseurs cette semaine ». @param string $n phrase normalisée */
    public function supplierInvoicesDue(string $n): array
    {
        $today = $this->today();
        $week = (bool) preg_match('/semaine|7 jours/', $n);
        $rows = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoicePurchase')->whereNotIn('d.status', ['draft', 'cancelled', 'paid'])->where('f.amount_due', '>', 0)
            ->when($week, fn ($q) => $q->whereNotNull('d.due_at')->where('d.due_at', '<=', $today->copy()->addDays(7)->toDateString()))
            ->orderByRaw('d.due_at IS NULL')->orderBy('d.due_at')->get(['d.reference', 'd.due_at', 't.tp_title AS tiers', 'f.amount_due']);
        if ($rows->isEmpty()) {
            return $this->reply($week ? 'Aucune facture fournisseur à payer dans les 7 jours (échues comprises).' : 'Aucune facture fournisseur à payer.');
        }
        $late = $rows->filter(fn ($r) => $r->due_at !== null && Carbon::parse($r->due_at)->lt($today))->count();

        return $this->reply("{$rows->count()} facture(s) fournisseur à payer" . ($week ? " d'ici 7 jours" : '') . ', ' . $this->money((float) $rows->sum('amount_due')) . " dus, dont {$late} échue(s). Les plus proches d'abord :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->tiers ?? 'sans fournisseur') . ' — ' . ($r->due_at ? 'échéance ' . Carbon::parse($r->due_at)->format('d/m/Y') : 'sans échéance') . ' — ' . $this->money((float) $r->amount_due))->implode("\n"));
    }

    public function purchasePriceIncreases(): array
    {
        $today = $this->today();
        $avg = fn (int $fromDays, int $toDays) => DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoicePurchase')->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('l.unit_price', '>', 0)->whereNotNull('l.product_id')
            ->whereBetween('d.issued_at', [$today->copy()->subDays($fromDays)->toDateString(), $today->copy()->subDays($toDays)->toDateString()])
            ->groupBy('l.product_id')->selectRaw('l.product_id, AVG(l.unit_price) AS prix');
        $rows = DB::table('products as p')->joinSub($avg(30, 0), 'r', 'r.product_id', '=', 'p.id')->joinSub($avg(120, 31), 'a', 'a.product_id', '=', 'p.id')
            ->whereRaw('r.prix > a.prix * 1.05')->orderByRaw('r.prix / a.prix DESC')->get(['p.p_title', 'p.p_sku', 'r.prix as recent', 'a.prix as avant']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucune hausse de prix d'achat de plus de 5 % (30 derniers jours comparés aux 90 jours précédents).");
        }

        return $this->reply("{$rows->count()} produit(s) dont le prix d'achat a augmenté de plus de 5 % (moyenne des factures des 30 derniers jours contre les 90 jours précédents) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — " . $this->money((float) $r->avant) . ' → ' . $this->money((float) $r->recent) . ' (+' . round(((float) $r->recent / (float) $r->avant - 1) * 100) . ' %)')->implode("\n"));
    }

    public function productsWithoutSupplier(): array
    {
        $rows = DB::table('products as p')->whereNull('p.deleted_at')->where('p.p_status', true)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('product_suppliers as s')->whereColumn('s.product_id', 'p.id'))
            ->orderBy('p.p_title')->get(['p.p_title', 'p.p_sku']);
        if ($rows->isEmpty()) {
            return $this->reply('Tous les produits actifs ont au moins un fournisseur.');
        }

        return $this->reply("{$rows->count()} produit(s) actif(s) sans fournisseur référencé (impossible de préparer un bon de commande automatique pour eux) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku})")->implode("\n") . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    /** « fournisseur le moins cher pour perceuse ». @param string $n phrase normalisée */
    public function cheapestSupplier(string $n): array
    {
        $term = preg_match('/\b(?:pour|de|du|des|sur)\s+(?:le |la |les |l.|un |une )?(?:produit |article )?(.{2,60})$/', trim($n), $m) ? trim($m[1]) : '';
        if (mb_strlen($term) < 2) {
            return $this->reply('Pour quel produit ? Par exemple : « fournisseur le moins cher pour perceuse ».', [], true);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $products = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->orderBy('p_title')->limit(3)->get(['id', 'p_title', 'p_sku']);
        if ($products->isEmpty()) {
            return $this->reply("Je ne trouve aucun produit correspondant à « {$term} ».", [], true);
        }

        $out = [];
        foreach ($products as $p) {
            $offers = DB::table('product_suppliers as s')->join('third_partners as t', 't.id', '=', 's.third_partner_id')->where('s.product_id', $p->id)->orderBy('s.purchase_price')->limit(5)->get(['t.tp_title', 's.purchase_price', 's.lead_time_days']);
            $out[] = "{$p->p_title} ({$p->p_sku}) :\n" . ($offers->isEmpty() ? '• aucun fournisseur référencé' : $offers->map(fn ($o) => "• {$o->tp_title} — " . $this->money((float) $o->purchase_price) . ($o->lead_time_days ? ", délai {$o->lead_time_days} j" : ''))->implode("\n"));
        }

        return $this->reply("Fournisseurs référencés, du moins cher au plus cher :\n\n" . implode("\n\n", $out));
    }

    // ── Ventes inhabituelles ─────────────────────────────────────────

    /** « remises accordées ce mois ». @param string $n phrase normalisée */
    public function discounts(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $base = fn () => DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('l.line_type', 'product')->where('l.discount_percent', '>', 0)->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()]);
        $tot = $base()->selectRaw('COUNT(*) AS n, COALESCE(SUM(l.quantity * l.unit_price * l.discount_percent / 100), 0) AS montant, MAX(l.discount_percent) AS maxi')->first();
        if ((int) $tot->n === 0) {
            return $this->reply("Aucune remise accordée {$label}.");
        }
        $top = $base()->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->orderByRaw('l.quantity * l.unit_price * l.discount_percent DESC')->limit(5)
            ->get(['d.reference', 't.tp_title AS client', 'l.designation', 'l.discount_percent', DB::raw('l.quantity * l.unit_price * l.discount_percent / 100 AS montant')]);

        return $this->reply("Remises {$label} : {$tot->n} ligne(s) remisée(s), " . $this->money((float) $tot->montant) . ' au total, remise maximale ' . round((float) $tot->maxi, 1) . " %.\n\nLes plus importantes :\n"
            . $top->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans client') . " — {$r->designation} : -" . round((float) $r->discount_percent, 1) . ' % (' . $this->money((float) $r->montant) . ')')->implode("\n"));
    }

    /** « lignes vendues sous le prix de référence ». @param string $n phrase normalisée */
    public function belowReferencePrice(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('l.line_type', 'product')
            ->where('l.reference_price', '>', 0)->whereColumn('l.unit_price', '<', 'l.reference_price')->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])
            ->orderByRaw('(l.reference_price - l.unit_price) * l.quantity DESC')->get(['d.reference', 't.tp_title AS client', 'l.designation', 'l.unit_price', 'l.reference_price', 'l.quantity']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucune ligne vendue sous son prix de référence {$label}.");
        }

        return $this->reply("{$rows->count()} ligne(s) vendue(s) sous le prix de référence {$label}, pour un manque à gagner de " . $this->money((float) $rows->sum(fn ($r) => ($r->reference_price - $r->unit_price) * $r->quantity)) . " :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans client') . " — {$r->designation} : " . $this->money((float) $r->unit_price) . ' au lieu de ' . $this->money((float) $r->reference_price))->implode("\n"));
    }

    public function cancelledInvoices(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'InvoiceSale')->where('d.status', 'cancelled')->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('d.issued_at')->get(['d.reference', 'd.issued_at', 't.tp_title AS client', 'u.name AS par', 'f.total_ttc']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucune facture de vente annulée {$label}.");
        }

        return $this->reply("{$rows->count()} facture(s) de vente annulée(s) {$label}, pour " . $this->money((float) $rows->sum('total_ttc')) . " :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans client') . ' — du ' . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc) . ($r->par ? " — établie par {$r->par}" : ''))->implode("\n"));
    }

    // ── Trésorerie ───────────────────────────────────────────────────

    /** « dépenses du mois par catégorie ». @param string $n phrase normalisée */
    public function expensesByCategory(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = $this->outflows($from, $to)->leftJoin('cash_categories as c', 'c.id', '=', 'x.cash_category_id')->groupBy('c.id', 'c.cc_title')
            ->selectRaw('COALESCE(c.cc_title, \'Sans catégorie\') AS categorie, COUNT(*) AS n, SUM(x.ct_amount) AS total')->orderByDesc('total')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune dépense de trésorerie {$label}.");
        }

        return $this->reply("Dépenses {$label} : " . $this->money((float) $rows->sum('total')) . " ({$rows->sum('n')} opération(s)).\n\nPar catégorie :\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->categorie} — {$r->n} opération(s), " . $this->money((float) $r->total))->implode("\n"));
    }

    public function expensesWithoutReceipt(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = $this->outflows($from, $to)->where(fn ($w) => $w->whereNull('x.ct_attachment_path')->orWhere('x.ct_attachment_path', ''))->orderByDesc('x.ct_amount')->get(['x.ct_date', 'x.ct_label', 'x.ct_amount']);
        if ($rows->isEmpty()) {
            return $this->reply("Toutes les dépenses {$label} ont un justificatif joint.");
        }

        return $this->reply("{$rows->count()} dépense(s) sans justificatif {$label}, pour " . $this->money((float) $rows->sum('ct_amount')) . " :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => '• ' . Carbon::parse($r->ct_date)->format('d/m/Y') . " — {$r->ct_label} — " . $this->money((float) $r->ct_amount))->implode("\n"));
    }

    public function upcomingRecurrences(): array
    {
        $today = $this->today();
        $rows = DB::table('cash_recurrences')->whereNull('deleted_at')->where('cr_status', true)->whereNotNull('cr_next_run_at')
            ->whereBetween('cr_next_run_at', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->orderBy('cr_next_run_at')->get(['cr_label', 'cr_direction', 'cr_amount', 'cr_next_run_at']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune opération récurrente prévue dans les 30 prochains jours.');
        }
        $out = (float) $rows->where('cr_direction', 'out')->sum('cr_amount');
        $in = (float) $rows->where('cr_direction', 'in')->sum('cr_amount');

        return $this->reply("{$rows->count()} opération(s) récurrente(s) dans les 30 prochains jours : " . $this->money($out) . ' à payer, ' . $this->money($in) . " à encaisser.\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => '• ' . Carbon::parse($r->cr_next_run_at)->format('d/m/Y') . " — {$r->cr_label} — " . ($r->cr_direction === 'out' ? '-' : '+') . $this->money((float) $r->cr_amount))->implode("\n"));
    }

    // ── Activité, utilisateurs, boutique, promotions ─────────────────

    /** « activité récente », « activité de Karim ». @param string $n phrase normalisée */
    public function recentActivity(string $n): array
    {
        $who = preg_match('/activite (?:de|des|d.)\s*(.{2,40})$/', trim($n), $m) ? trim($m[1]) : null;
        $q = DB::table('activity_log as a')->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'a.causer_id')->where('a.causer_type', 'like', '%User'))
            ->when($who !== null, fn ($w) => $w->whereRaw('LOWER(u.name) like ?', ['%' . str_replace(['%', '_'], ['\%', '\_'], $who) . '%']))
            ->orderByDesc('a.id')->limit(self::LIST);
        $rows = $q->get(['a.created_at', 'a.description', 'a.event', 'a.subject_type', 'a.subject_id', 'u.name']);
        if ($rows->isEmpty()) {
            return $this->reply($who !== null ? "Aucune activité trouvée pour « {$who} »." : 'Le journal d\'activité est vide.');
        }

        return $this->reply('Activité récente' . ($who !== null ? " de « {$who} »" : '') . " (les {$rows->count()} dernières opérations enregistrées) :\n\n"
            . $rows->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . ' — ' . ($r->name ?? 'système') . ' — ' . ($r->description ?: ($r->event ?? '?')) . ($r->subject_type ? ' (' . class_basename($r->subject_type) . ' #' . $r->subject_id . ')' : ''))->implode("\n"));
    }

    public function users(): array
    {
        $rows = DB::table('users as u')->leftJoin('roles as r', 'r.id', '=', 'u.role_id')->whereNull('u.deleted_at')->groupBy('r.id', 'r.display_name', 'r.name')
            ->selectRaw('COALESCE(r.display_name, r.name, \'Sans rôle\') AS role, COUNT(*) AS n, SUM(u.is_active) AS actifs')->orderByDesc('n')->get();
        $inactive = DB::table('users')->whereNull('deleted_at')->where('is_active', false)->orderBy('name')->limit(self::LIST)->pluck('name');

        return $this->reply("Utilisateurs par rôle :\n\n" . $rows->map(fn ($r) => "• {$r->role} — {$r->n} compte(s), {$r->actifs} actif(s)")->implode("\n")
            . ($inactive->isNotEmpty() ? "\n\nComptes inactifs :\n" . $inactive->map(fn ($t) => "• {$t}")->implode("\n") : "\n\nAucun compte inactif."));
    }

    public function onlineGaps(): array
    {
        $stock = DB::table('warehouse_has_stock')->selectRaw('product_id, SUM(stockLevel) AS qty')->groupBy('product_id');
        $rows = DB::table('products as p')->leftJoinSub($stock, 's', 's.product_id', '=', 'p.id')->whereNull('p.deleted_at')->where('p.is_ecom', true)->where('p.p_status', true)
            ->selectRaw('p.p_title, p.p_sku, COALESCE(s.qty, 0) AS qty, (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) AS photos')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun produit actif sur la boutique en ligne pour le moment.', [['label' => 'Publier les produits prêts', 'text' => 'publie les produits sur le website']]);
        }
        $noStock = $rows->filter(fn ($r) => (float) $r->qty <= 0);
        $noPhoto = $rows->filter(fn ($r) => (int) $r->photos === 0);

        return $this->reply("{$rows->count()} produit(s) actif(s) sur la boutique en ligne : {$noStock->count()} sans stock, {$noPhoto->count()} sans photo."
            . ($noStock->isNotEmpty() ? "\n\nSans stock :\n" . $noStock->take(5)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku})")->implode("\n") : '')
            . ($noPhoto->isNotEmpty() ? "\n\nSans photo :\n" . $noPhoto->take(5)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku})")->implode("\n") : ''));
    }

    /** « promotions actives », « promotions qui se terminent cette semaine ». @param string $n phrase normalisée */
    public function promotions(string $n): array
    {
        $now = $this->today();
        $ending = (bool) preg_match('/termin|expir|finiss|bientot/', $n);
        $rows = DB::table('promotions')->where('is_active', true)->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now->copy()->endOfDay()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->when($ending, fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<=', $now->copy()->addDays(7)->endOfDay()))
            ->orderBy('ends_at')->get(['name', 'type', 'value', 'ends_at']);
        if ($rows->isEmpty()) {
            return $this->reply($ending ? 'Aucune promotion ne se termine dans les 7 jours.' : 'Aucune promotion active en ce moment.');
        }

        return $this->reply($rows->count() . ' promotion(s) ' . ($ending ? 'qui se terminent dans les 7 jours' : 'active(s)') . " :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->name} — " . ($r->type === 'percentage' ? round((float) $r->value, 1) . ' %' : $this->money((float) $r->value)) . ' — ' . ($r->ends_at ? "jusqu'au " . Carbon::parse($r->ends_at)->format('d/m/Y') : 'sans date de fin'))->implode("\n"));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** Les sorties de trésorerie actives de la période. */
    private function outflows(Carbon $from, Carbon $to): \Illuminate\Database\Query\Builder
    {
        return DB::table('cash_transactions as x')->whereNull('x.deleted_at')->where('x.ct_direction', 'out')->where('x.ct_status', 'active')->whereNull('x.ct_transfer_group')
            ->whereBetween('x.ct_date', [$from->toDateString(), $to->toDateString()]);
    }

    /** @param array<int, string> $statuses */
    private function docs(string $type, array $statuses): \Illuminate\Support\Collection
    {
        return DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->where('d.document_type', $type)->whereIn('d.status', $statuses)->orderBy('d.issued_at')->limit(200)->get(['d.reference', 'd.issued_at', 't.tp_title AS client', 'f.total_ttc']);
    }

    private function docList(\Illuminate\Support\Collection $rows, string $what, string $none): array
    {
        if ($rows->isEmpty()) {
            return $this->reply($none);
        }

        return $this->reply("{$rows->count()} {$what}, pour " . $this->money((float) $rows->sum('total_ttc')) . " TTC. Les plus anciens :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans tiers') . ' — du ' . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc))->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} début, fin (incluse), libellé */
    private function period(string $n, string $default): array
    {
        $today = $this->today();

        return match (true) {
            (bool) preg_match('/\bhier\b/', $n)                      => [$today->copy()->subDay(), $today->copy()->subDay(), "d'hier"],
            (bool) preg_match('/semaine/', $n)                       => [$today->copy()->startOfWeek(), $today, 'de la semaine (depuis lundi)'],
            (bool) preg_match('/\bannee\b|\ban\b/', $n)              => [$today->copy()->startOfYear(), $today, "de l'année"],
            (bool) preg_match('/aujourd|du jour|journee/', $n)       => [$today, $today, "d'aujourd'hui"],
            default                                                  => [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'],
        };
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = [], bool $error = false): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'operations', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
