<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cinquième lot de lectures de l'orchestrateur : les fiches (produit, client ou fournisseur, document), la marge
 * réalisée, le panier moyen, l'évolution du chiffre d'affaires, les ventes par catégorie ou marque, le taux de
 * transformation des devis, les commandes clients en attente, les retours, les ruptures à venir, les tickets annulés,
 * le flux de trésorerie du mois et la prévision de trésorerie à 30 jours.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. Les codes PIN, mots de passe et jetons ne sont jamais lus.
 */
class DeepDiveAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];
    private const TYPES = [
        'InvoiceSale' => 'Facture de vente', 'TicketSale' => 'Ticket de caisse', 'QuoteSale' => 'Devis', 'CustomerOrder' => 'Commande client', 'DeliveryNote' => 'Bon de livraison',
        'CreditNoteSale' => 'Avoir client', 'ReturnSale' => 'Retour client', 'InvoicePurchase' => "Facture d'achat", 'PurchaseOrder' => 'Bon de commande', 'ReceiptNotePurchase' => 'Bon de réception',
        'CreditNotePurchase' => 'Avoir fournisseur', 'ReturnPurchase' => 'Retour fournisseur', 'StockEntry' => 'Entrée de stock', 'StockExit' => 'Sortie de stock', 'StockTransfer' => 'Transfert de stock', 'StockAdjustmentNote' => 'Ajustement de stock',
    ];
    private const STATUSES = [
        'draft' => 'brouillon', 'confirmed' => 'confirmé', 'sent' => 'envoyé', 'delivered' => 'livré', 'received' => 'reçu', 'pending' => 'en attente', 'paid' => 'payé',
        'partial' => 'partiellement payé', 'cancelled' => 'annulé', 'converted' => 'converti', 'applied' => 'appliqué',
    ];

    // ── Fiches ───────────────────────────────────────────────────────

    /** « fiche du produit perceuse », « stock de PRC1 ». @param string $n phrase normalisée */
    public function productCard(string $n): array
    {
        $term = $this->term($n, '/(?:fiche|infos?|details?|stock|prix)\s+(?:du |de la |de l.|des |de |d.)?(?:produit |article )?(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quel produit ? Par exemple : « fiche du produit PRC1 » ou « stock de perceuse ».', [], true);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $matches = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_sku', $term)->orWhere('p_ean13', $term)->orWhere('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->orderByRaw('p_sku = ? DESC', [$term])->orderBy('p_title')->limit(6)->get();
        if ($matches->isEmpty()) {
            return $this->reply("Je ne trouve aucun produit correspondant à « {$term} ».", [], true);
        }
        $exact = $matches->first(fn ($p) => mb_strtolower((string) $p->p_sku) === $term || (string) $p->p_ean13 === $term);
        if ($matches->count() > 1 && !$exact) {
            return $this->reply("Plusieurs produits correspondent à « {$term} » :\n\n" . $matches->take(5)->map(fn ($p) => "• {$p->p_title} ({$p->p_sku})")->implode("\n") . "\n\nPrécisez avec la référence, par exemple « fiche du produit {$matches->first()->p_sku} ».");
        }

        $p = $exact ?? $matches->first();
        $category = $p->category_id ? DB::table('categories')->where('id', $p->category_id)->value('ctg_title') : null;
        $brand = $p->brand_id ? DB::table('brands')->where('id', $p->brand_id)->value('br_title') : null;
        $stocks = DB::table('warehouse_has_stock as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')->where('s.product_id', $p->id)->orderBy('w.wh_title')->get(['w.wh_title', 's.stockLevel', 's.wh_average']);
        $photos = DB::table('product_images')->where('product_id', $p->id)->count();
        $lastBuy = $this->lastLine($p->id, ['InvoicePurchase']);
        $lastSale = $this->lastLine($p->id, self::SALES_TYPES);
        $sold30 = $this->saleLines($this->today()->copy()->subDays(29), $this->today())->where('l.product_id', $p->id)->sum('l.quantity');
        $margin = (float) $p->p_salePrice > 0 ? round(((float) $p->p_salePrice - (float) $p->p_purchasePrice) / (float) $p->p_salePrice * 100, 1) : null;

        $lines = [
            "{$p->p_title} ({$p->p_sku})" . ($p->p_status ? '' : ' — INACTIF') . ($p->is_ecom ? ' — sur la boutique en ligne' : ''),
            '• Catégorie : ' . ($category ?? '—') . ' ; marque : ' . ($brand ?? '—') . ($p->p_ean13 ? " ; code-barres {$p->p_ean13}" : ''),
            '• Prix d\'achat ' . $this->money((float) $p->p_purchasePrice) . ', prix de vente ' . $this->money((float) $p->p_salePrice) . ' (TVA ' . round((float) $p->p_taxRate, 1) . ' %)' . ($margin !== null ? ", marge {$margin} %" : ''),
            '• Stock : ' . ($stocks->isEmpty() ? 'aucun enregistrement' : $stocks->map(fn ($s) => "{$s->wh_title} " . $this->qty((float) $s->stockLevel))->implode(', ') . ' — total ' . $this->qty((float) $stocks->sum('stockLevel'))),
            "• Photos : {$photos}" . ' ; vendu sur 30 jours : ' . $this->qty((float) $sold30),
            '• Dernier achat : ' . ($lastBuy ? $this->money((float) $lastBuy->unit_price) . ' le ' . Carbon::parse($lastBuy->issued_at)->format('d/m/Y') : 'aucun') . ' ; dernière vente : ' . ($lastSale ? $this->money((float) $lastSale->unit_price) . ' le ' . Carbon::parse($lastSale->issued_at)->format('d/m/Y') : 'aucune'),
        ];

        return $this->reply(implode("\n", $lines));
    }

    /** « fiche du client Atlas », « fiche du fournisseur Bati ». @param string $n phrase normalisée */
    public function thirdPartyCard(string $n): array
    {
        $supplier = (bool) preg_match('/fournisseur/', $n);
        $term = $this->term($n, '/(?:fiche|infos?|details?|solde|situation|compte)\s+(?:du |de la |de l.|des |de |d.)?(?:client |fournisseur |tiers )?(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quel ' . ($supplier ? 'fournisseur' : 'client') . ' ? Par exemple : « fiche du client Atlas ».', [], true);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $roles = $supplier ? ['supplier', 'both'] : (preg_match('/client/', $n) ? ['customer', 'both'] : ['customer', 'supplier', 'both']);
        $matches = DB::table('third_partners')->whereNull('deleted_at')->whereIn('tp_Role', $roles)->where(fn ($w) => $w->where('tp_title', 'like', $like)->orWhere('tp_code', $term)->orWhere('tp_Ice_Number', $term))->orderBy('tp_title')->limit(6)->get();
        if ($matches->isEmpty()) {
            return $this->reply("Je ne trouve aucun tiers correspondant à « {$term} ».", [], true);
        }
        if ($matches->count() > 1) {
            return $this->reply("Plusieurs tiers correspondent à « {$term} » :\n\n" . $matches->take(5)->map(fn ($t) => "• {$t->tp_title}")->implode("\n") . "\n\nPrécisez le nom complet.");
        }

        $t = $matches->first();
        $isSupplier = $supplier || $t->tp_Role === 'supplier';
        $type = $isSupplier ? 'InvoicePurchase' : 'InvoiceSale';
        $docs = fn () => DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.thirdPartner_id', $t->id)->where('d.document_type', $type)->whereNotIn('d.status', ['draft', 'cancelled']);
        $due = $docs()->where('f.amount_due', '>', 0)->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.amount_due), 0) AS du')->first();
        $year = $docs()->where('d.issued_at', '>=', $this->today()->copy()->subYear()->toDateString())->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->first();
        $recent = $docs()->orderByDesc('d.issued_at')->limit(5)->get(['d.reference', 'd.issued_at', 'd.status', 'f.total_ttc', 'f.amount_due']);

        $lines = [
            "{$t->tp_title}" . ($t->tp_status ? '' : ' — INACTIF') . ' (' . ['customer' => 'client', 'supplier' => 'fournisseur', 'both' => 'client et fournisseur'][$t->tp_Role] . ')',
            '• Contact : ' . ($t->tp_phone ?: 'téléphone —') . ', ' . ($t->tp_email ?: 'e-mail —') . ($t->tp_city ? ", {$t->tp_city}" : '') . ($t->tp_Ice_Number ? " ; ICE {$t->tp_Ice_Number}" : ''),
            '• Compte ' . ($t->type_compte === 'en_compte' ? 'en compte' . ($t->frequence_facturation ? " (facturation {$t->frequence_facturation})" : '') : 'comptant') . ' ; encours ' . $this->money((float) $t->encours_actuel) . ((float) $t->seuil_credit > 0 ? ' pour un seuil de ' . $this->money((float) $t->seuil_credit) : ''),
            '• ' . ($isSupplier ? 'Factures d\'achat' : 'Factures de vente') . " impayées : {$due->n} pour " . $this->money((float) $due->du) . " ; sur 12 mois : {$year->n} facture(s), " . $this->money((float) $year->ttc) . ' TTC',
        ];
        if ($recent->isNotEmpty()) {
            $lines[] = 'Dernières factures :';
            foreach ($recent as $r) {
                $lines[] = "• {$r->reference} — " . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc) . ((float) $r->amount_due > 0 ? ', reste ' . $this->money((float) $r->amount_due) : ', soldée');
            }
        }

        return $this->reply(implode("\n", $lines));
    }

    /** « montre la facture FV-001 ». @param string $n phrase normalisée */
    public function documentCard(string $n): array
    {
        $ref = $this->term($n, '/(?:facture|devis|bon de \w+|bon|commande|avoir|ticket|document|retour)\s+(?:n°\s*|no\s*|numero\s*)?([a-z0-9][a-z0-9\-\/_.]{1,40})$/');
        if ($ref === null) {
            return $this->reply('Quel document ? Par exemple : « montre la facture FV-001 ».', [], true);
        }
        $d = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->whereNull('d.deleted_at')->where('d.reference', $ref)->first(['d.id', 'd.reference', 'd.document_type', 'd.status', 'd.issued_at', 'd.due_at', 'd.notes', 't.tp_title', 'u.name AS par', 'f.total_ht', 'f.total_tax', 'f.total_ttc', 'f.amount_paid', 'f.amount_due']);
        if (!$d) {
            return $this->reply("Je ne trouve aucun document de référence « {$ref} ».", [], true);
        }

        $lignes = DB::table('document_lignes')->where('document_header_id', $d->id)->where('status', 'active')->orderBy('sort_order')->orderBy('id')->get(['line_type', 'designation', 'quantity', 'unit_price', 'discount_percent', 'total_ligne_ht']);
        $payments = DB::table('payments')->where('document_header_id', $d->id)->orderBy('paid_at')->get(['paid_at', 'method', 'amount']);
        $lines = [
            (self::TYPES[$d->document_type] ?? $d->document_type) . " {$d->reference} — " . (self::STATUSES[$d->status] ?? $d->status),
            '• ' . ($d->tp_title ?? 'sans tiers') . ' ; du ' . Carbon::parse($d->issued_at)->format('d/m/Y') . ($d->due_at ? ", échéance le " . Carbon::parse($d->due_at)->format('d/m/Y') : '') . ($d->par ? " ; établi par {$d->par}" : ''),
            '• Total ' . $this->money((float) $d->total_ht) . ' HT, TVA ' . $this->money((float) $d->total_tax) . ', ' . $this->money((float) $d->total_ttc) . ' TTC ; payé ' . $this->money((float) $d->amount_paid) . ', reste ' . $this->money((float) $d->amount_due),
        ];
        if ($lignes->isNotEmpty()) {
            $lines[] = "Lignes ({$lignes->count()}) :";
            foreach ($lignes->take(self::LIST) as $l) {
                $lines[] = $l->line_type === 'comment' ? "• {$l->designation}" : "• {$l->designation} — " . $this->qty((float) $l->quantity) . ' × ' . $this->money((float) $l->unit_price) . ((float) $l->discount_percent > 0 ? ' (-' . round((float) $l->discount_percent, 1) . ' %)' : '') . ' = ' . $this->money((float) $l->total_ligne_ht) . ' HT';
            }
            $lignes->count() > self::LIST && $lines[] = '… et ' . ($lignes->count() - self::LIST) . ' autre(s) ligne(s).';
        }
        if ($payments->isNotEmpty()) {
            $lines[] = 'Paiements :';
            foreach ($payments->take(self::LIST) as $p) {
                $lines[] = '• ' . Carbon::parse($p->paid_at)->format('d/m/Y') . ' — ' . $p->method . ' — ' . $this->money((float) $p->amount);
            }
        }

        return $this->reply(implode("\n", $lines));
    }

    // ── Chiffres de l'activité ───────────────────────────────────────

    /** « marge réalisée du mois ». @param string $n phrase normalisée */
    public function realizedMargin(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $cost = 'COALESCE(NULLIF(p.p_cost, 0), p.p_purchasePrice)';
        $base = fn () => $this->saleLines($from, $to)->join('products as p', 'p.id', '=', 'l.product_id');
        $tot = $base()->selectRaw("COUNT(*) AS n, COALESCE(SUM(l.total_ligne_ht), 0) AS ca, COALESCE(SUM(l.quantity * {$cost}), 0) AS cout")->first();
        if ((int) $tot->n === 0) {
            return $this->reply("Aucune vente de produit {$label}.");
        }
        $top = $base()->groupBy('p.id', 'p.p_title')->selectRaw("p.p_title AS produit, SUM(l.total_ligne_ht) - SUM(l.quantity * {$cost}) AS marge")->orderByDesc('marge')->limit(5)->get();
        $low = $base()->groupBy('p.id', 'p.p_title')->selectRaw("p.p_title AS produit, SUM(l.total_ligne_ht) - SUM(l.quantity * {$cost}) AS marge, SUM(l.total_ligne_ht) AS ca")->havingRaw('SUM(l.total_ligne_ht) > 0')->orderByRaw("(SUM(l.total_ligne_ht) - SUM(l.quantity * {$cost})) / SUM(l.total_ligne_ht)")->limit(3)->get();
        $ca = (float) $tot->ca;
        $marge = $ca - (float) $tot->cout;

        return $this->reply("Marge réalisée {$label} : " . $this->money($marge) . ' sur ' . $this->money($ca) . ' HT, soit ' . ($ca > 0 ? round($marge / $ca * 100, 1) : 0) . " %.\n\nProduits les plus rentables :\n"
            . $top->map(fn ($r) => "• {$r->produit} — " . $this->money((float) $r->marge))->implode("\n") . "\n\nMarges les plus faibles :\n"
            . $low->map(fn ($r) => "• {$r->produit} — " . round((float) $r->marge / max((float) $r->ca, 0.01) * 100, 1) . ' %')->implode("\n")
            . "\n\nLe coût est celui de la fiche produit aujourd'hui (coût, sinon prix d'achat), pas celui du jour de la vente ; les lignes sans produit sont ignorées.");
    }

    /** « panier moyen du mois ». @param string $n phrase normalisée */
    public function averageBasket(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = $this->salesDocs($from, $to)->groupBy('d.document_type')->selectRaw('d.document_type AS type, COUNT(*) AS n, AVG(f.total_ttc) AS moyen, MAX(f.total_ttc) AS maxi, SUM(f.total_ttc) AS total')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }
        $n2 = (int) $rows->sum('n');

        return $this->reply("Panier moyen {$label} :\n\n" . $rows->map(fn ($r) => '• ' . (self::TYPES[$r->type] ?? $r->type) . "s — {$r->n} document(s), moyenne " . $this->money((float) $r->moyen) . ', maximum ' . $this->money((float) $r->maxi))->implode("\n")
            . "\n\nTous documents : " . $this->money((float) $rows->sum('total') / max($n2, 1)) . " en moyenne sur {$n2} vente(s).");
    }

    /** « évolution du chiffre d'affaires sur 6 mois ». @param string $n phrase normalisée */
    public function monthlyTrend(string $n): array
    {
        $months = preg_match('/(\d{1,2})\s*mois/', $n, $m) ? max(2, min(24, (int) $m[1])) : 6;
        $start = $this->today()->copy()->startOfMonth()->subMonths($months - 1);
        $rows = $this->salesDocs($start, $this->today())->groupByRaw("DATE_FORMAT(d.issued_at, '%Y-%m')")->selectRaw("DATE_FORMAT(d.issued_at, '%Y-%m') AS mois, COUNT(*) AS n, SUM(f.total_ttc) AS ttc")->pluck('ttc', 'mois');
        $counts = $this->salesDocs($start, $this->today())->groupByRaw("DATE_FORMAT(d.issued_at, '%Y-%m')")->selectRaw("DATE_FORMAT(d.issued_at, '%Y-%m') AS mois, COUNT(*) AS n")->pluck('n', 'mois');
        $max = max(1.0, (float) $rows->max());

        $lines = [];
        $previous = null;
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $v = (float) ($rows[$key] ?? 0);
            $lines[] = Carbon::parse("{$key}-01")->locale('fr')->isoFormat('MMM YYYY') . ' ' . str_repeat('█', (int) round($v / $max * 14)) . ' ' . $this->money($v) . ' (' . (int) ($counts[$key] ?? 0) . ')'
                . ($previous !== null && $previous > 0 ? ' ' . (($d = round(($v - $previous) / $previous * 100)) >= 0 ? '+' : '') . $d . ' %' : '');
            $previous = $v;
        }

        return $this->reply("Chiffre d'affaires TTC des {$months} derniers mois (le mois en cours est partiel) :\n\n" . implode("\n", $lines));
    }

    /** « ventes du mois par catégorie / par marque ». @param string $n phrase normalisée */
    public function salesByGroup(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $brand = (bool) preg_match('/marque/', $n);
        $q = $this->saleLines($from, $to)->join('products as p', 'p.id', '=', 'l.product_id');
        $q = $brand ? $q->leftJoin('brands as g', 'g.id', '=', 'p.brand_id')->groupBy('g.id', 'g.br_title')->selectRaw('COALESCE(g.br_title, \'Sans marque\') AS groupe')
            : $q->leftJoin('categories as g', 'g.id', '=', 'p.category_id')->groupBy('g.id', 'g.ctg_title')->selectRaw('COALESCE(g.ctg_title, \'Sans catégorie\') AS groupe');
        $rows = $q->selectRaw('SUM(l.quantity) AS qte, SUM(l.total_ligne_ht) AS ht')->orderByDesc('ht')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }
        $total = max((float) $rows->sum('ht'), 0.01);

        return $this->reply('Ventes ' . $label . ($brand ? ' par marque' : ' par catégorie') . ' (HT, ' . $this->money($total) . " au total) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->groupe} — " . $this->money((float) $r->ht) . ' (' . round((float) $r->ht / $total * 100) . ' %), ' . $this->qty((float) $r->qte) . ' vendu(s)')->implode("\n"));
    }

    /** « taux de transformation des devis ». @param string $n phrase normalisée */
    public function quoteConversion(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'quarter');
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.document_type', 'QuoteSale')->where('d.status', '!=', 'draft')
            ->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])->groupBy('d.status')->selectRaw('d.status, COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->get()->keyBy('status');
        $total = (int) $rows->sum('n');
        if ($total === 0) {
            return $this->reply("Aucun devis émis {$label}.");
        }
        $won = (int) ($rows['converted']->n ?? 0);
        $lost = (int) ($rows['cancelled']->n ?? 0);
        $open = $total - $won - $lost;

        return $this->reply("Devis {$label} : {$total} émis, " . $this->money((float) $rows->sum('ttc')) . " TTC.\n\n• Transformés : {$won} (" . $this->money((float) ($rows['converted']->ttc ?? 0)) . ")\n• Annulés : {$lost}\n• Encore ouverts : {$open}\n\nTaux de transformation : "
            . round($won / $total * 100) . ' % de tous les devis' . ($total - $open > 0 ? ', ' . round($won / ($total - $open) * 100) . ' % des devis clos' : '') . '.', [['label' => 'Devis sans suite', 'text' => 'devis sans suite depuis 10 jours']]);
    }

    public function pendingCustomerOrders(): array
    {
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')
            ->where('d.document_type', 'CustomerOrder')->whereIn('d.status', ['confirmed', 'sent', 'pending'])->orderBy('d.issued_at')->get(['d.reference', 'd.issued_at', 't.tp_title AS client', 'f.total_ttc']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune commande client en attente de livraison.');
        }

        return $this->reply("{$rows->count()} commande(s) client en attente de livraison, pour " . $this->money((float) $rows->sum('total_ttc')) . " TTC. Les plus anciennes :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->client ?? 'sans client') . ' — du ' . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc))->implode("\n"));
    }

    /** « retours et avoirs du mois ». @param string $n phrase normalisée */
    public function returns(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->whereIn('d.document_type', ['ReturnSale', 'CreditNoteSale', 'ReturnPurchase', 'CreditNotePurchase'])
            ->whereNotIn('d.status', ['draft', 'cancelled'])->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])->groupBy('d.document_type')->selectRaw('d.document_type AS type, COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucun retour ni avoir {$label}.");
        }

        return $this->reply("Retours et avoirs {$label} :\n\n" . $rows->map(fn ($r) => '• ' . (self::TYPES[$r->type] ?? $r->type) . " — {$r->n} document(s), " . $this->money((float) $r->ttc))->implode("\n"));
    }

    // ── Stock et caisse ──────────────────────────────────────────────

    /** « produits bientôt en rupture dans 14 jours ». @param string $n phrase normalisée */
    public function runoutSoon(string $n): array
    {
        $horizon = preg_match('/(\d{1,3})\s*j/', $n, $m) ? max(1, min(180, (int) $m[1])) : 14;
        $sold = $this->saleLines($this->today()->copy()->subDays(29), $this->today())->whereNotNull('l.product_id')->groupBy('l.product_id')->selectRaw('l.product_id, SUM(l.quantity) / 30 AS par_jour');
        $stock = DB::table('warehouse_has_stock')->groupBy('product_id')->selectRaw('product_id, SUM(stockLevel) AS qty');
        $rows = DB::table('products as p')->joinSub($sold, 'v', 'v.product_id', '=', 'p.id')->leftJoinSub($stock, 's', 's.product_id', '=', 'p.id')->whereNull('p.deleted_at')->where('p.p_status', true)->where('v.par_jour', '>', 0)
            ->whereRaw('COALESCE(s.qty, 0) / v.par_jour <= ?', [$horizon])->selectRaw('p.p_title, p.p_sku, COALESCE(s.qty, 0) AS qty, v.par_jour, COALESCE(s.qty, 0) / v.par_jour AS jours')->orderByRaw('COALESCE(s.qty, 0) / v.par_jour')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucun produit vendu récemment n'est menacé de rupture dans les {$horizon} jours (au rythme des 30 derniers jours).");
        }

        return $this->reply("{$rows->count()} produit(s) en rupture dans {$horizon} jours ou moins, au rythme de vente des 30 derniers jours :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — stock " . $this->qty((float) $r->qty) . ', ' . $this->qty(round((float) $r->par_jour, 2)) . ' vendu(s) par jour → ' . ((float) $r->qty <= 0 ? 'déjà en rupture' : 'environ ' . max(1, (int) floor((float) $r->jours)) . ' jour(s)'))->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''), [['label' => 'Produits à stock bas', 'text' => 'quels produits ont un stock bas']]);
    }

    /** « tickets annulés ce mois ». @param string $n phrase normalisée */
    public function cancelledTickets(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->whereNull('d.deleted_at')
            ->where('d.document_type', 'TicketSale')->where('d.status', 'cancelled')->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])->orderByDesc('d.issued_at')->get(['d.reference', 'd.issued_at', 'u.name AS par', 'f.total_ttc']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun ticket annulé {$label}.");
        }

        return $this->reply("{$rows->count()} ticket(s) annulé(s) {$label}, pour " . $this->money((float) $rows->sum('total_ttc')) . " :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc) . ($r->par ? " — {$r->par}" : ''))->implode("\n"));
    }

    // ── Trésorerie ───────────────────────────────────────────────────

    /** « flux de trésorerie du mois ». @param string $n phrase normalisée */
    public function cashFlow(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $base = fn () => DB::table('cash_transactions as x')->whereNull('x.deleted_at')->where('x.ct_status', 'active')->whereNull('x.ct_transfer_group')->whereBetween('x.ct_date', [$from->toDateString(), $to->toDateString()]);
        $totals = $base()->groupBy('x.ct_direction')->selectRaw('x.ct_direction AS sens, COUNT(*) AS n, SUM(x.ct_amount) AS total')->get()->keyBy('sens');
        if ($totals->isEmpty()) {
            return $this->reply("Aucune opération de trésorerie {$label}.");
        }
        $in = (float) ($totals['in']->total ?? 0);
        $out = (float) ($totals['out']->total ?? 0);
        $cat = fn (string $dir) => $base()->where('x.ct_direction', $dir)->leftJoin('cash_categories as c', 'c.id', '=', 'x.cash_category_id')->groupBy('c.id', 'c.cc_title')
            ->selectRaw('COALESCE(c.cc_title, \'Sans catégorie\') AS categorie, SUM(x.ct_amount) AS total')->orderByDesc('total')->limit(3)->get();

        return $this->reply("Flux de trésorerie {$label} (virements entre comptes exclus) :\n\n• Entrées : " . $this->money($in) . ' (' . (int) ($totals['in']->n ?? 0) . ")\n• Sorties : " . $this->money($out) . ' (' . (int) ($totals['out']->n ?? 0) . ")\n• Solde net : " . ($in - $out >= 0 ? '+' : '') . $this->money($in - $out)
            . "\n\nPrincipales entrées : " . ($cat('in')->map(fn ($r) => "{$r->categorie} " . $this->money((float) $r->total))->implode(', ') ?: '—')
            . "\nPrincipales sorties : " . ($cat('out')->map(fn ($r) => "{$r->categorie} " . $this->money((float) $r->total))->implode(', ') ?: '—'));
    }

    public function cashForecast(): array
    {
        $today = $this->today();
        $horizon = $today->copy()->addDays(30)->toDateString();
        $accounts = DB::table('cash_accounts')->whereNull('deleted_at')->where('ca_status', true)->sum('ca_initial_balance');
        $moves = DB::table('cash_transactions')->whereNull('deleted_at')->where('ct_status', 'active')->selectRaw("COALESCE(SUM(CASE WHEN ct_direction = 'in' THEN ct_amount ELSE -ct_amount END), 0) AS net")->value('net');
        $balance = (float) $accounts + (float) $moves;

        $due = fn (string $type) => DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.document_type', $type)
            ->whereNotIn('d.status', ['draft', 'cancelled', 'paid'])->where('f.amount_due', '>', 0)->whereNotNull('d.due_at');
        $inSoon = (float) $due('InvoiceSale')->whereBetween('d.due_at', [$today->toDateString(), $horizon])->sum('f.amount_due');
        $inLate = (float) $due('InvoiceSale')->where('d.due_at', '<', $today->toDateString())->sum('f.amount_due');
        $outSoon = (float) $due('InvoicePurchase')->where('d.due_at', '<=', $horizon)->sum('f.amount_due');
        $rec = DB::table('cash_recurrences')->whereNull('deleted_at')->where('cr_status', true)->whereBetween('cr_next_run_at', [$today->toDateString(), $horizon])->groupBy('cr_direction')->selectRaw('cr_direction AS sens, SUM(cr_amount) AS total')->pluck('total', 'sens');
        $recIn = (float) ($rec['in'] ?? 0);
        $recOut = (float) ($rec['out'] ?? 0);
        $projected = $balance + $inSoon - $outSoon + $recIn - $recOut;

        return $this->reply("Prévision de trésorerie à 30 jours (jusqu'au " . $today->copy()->addDays(30)->format('d/m/Y') . ") :\n\n• Solde actuel des comptes : " . $this->money($balance)
            . "\n• À encaisser (factures clients échéant dans 30 jours) : +" . $this->money($inSoon) . "\n• À payer (factures fournisseurs échues ou à échoir) : -" . $this->money($outSoon)
            . "\n• Opérations récurrentes : +" . $this->money($recIn) . ' / -' . $this->money($recOut) . "\n\nSolde prévisible : " . $this->money($projected)
            . "\n\nNon compté : " . $this->money($inLate) . ' de factures clients déjà échues (encaissement incertain) et les ventes à venir. Estimation à partir des échéances saisies, pas une garantie.');
    }

    /** « TVA collectée du mois », « TVA à payer » : la TVA des ventes, celle des achats et la différence (estimation). @param string $n phrase normalisée */
    public function vatSummary(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $sum = fn (array $types, bool $credit = false) => (float) DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->whereIn('d.document_type', $types)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])->sum('f.total_tax');
        $collected = $sum(self::SALES_TYPES) - $sum(['CreditNoteSale']);
        $deductible = $sum(['InvoicePurchase']) - $sum(['CreditNotePurchase']);
        if ($collected == 0.0 && $deductible == 0.0) {
            return $this->reply("Aucune TVA enregistrée {$label}.");
        }

        return $this->reply("TVA {$label} :\n\n• Collectée sur les ventes (factures et tickets, avoirs déduits) : " . $this->money($collected) . "\n• Déductible sur les achats (factures d'achat, avoirs déduits) : " . $this->money($deductible)
            . "\n• Différence : " . $this->money($collected - $deductible) . ($collected - $deductible >= 0 ? ' à payer' : ' de crédit de TVA')
            . "\n\nEstimation à partir des montants de TVA des documents ; ce n'est pas la déclaration (régime, prorata et opérations hors documents ne sont pas pris en compte).");
    }
    // ── Outils ───────────────────────────────────────────────────────

    /** L'objet de la phrase (produit, tiers, référence…), nettoyé. @param string $n phrase normalisée */
    private function term(string $n, string $regex): ?string
    {
        if (!preg_match($regex, trim($n), $m)) {
            return null;
        }
        $t = trim($m[1], " ?.!");

        return mb_strlen($t) >= 2 ? $t : null;
    }

    private function lastLine(int $productId, array $types): ?object
    {
        return DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereNull('d.deleted_at')->whereIn('d.document_type', $types)->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('l.status', 'active')->where('l.product_id', $productId)->orderByDesc('d.issued_at')->orderByDesc('d.id')->first(['l.unit_price', 'd.issued_at']);
    }

    private function salesDocs(Carbon $from, Carbon $to): Builder
    {
        return DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()]);
    }

    private function saleLines(Carbon $from, Carbon $to): Builder
    {
        return DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('l.line_type', 'product')->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()]);
    }

    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} début, fin (incluse), libellé */
    private function period(string $n, string $default): array
    {
        return ReportPeriod::resolve($n, $default, $this->today());
    }

    private function qty(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',') ?: '0';
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'deepdive', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
