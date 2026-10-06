<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deuxième lot de lectures de l'orchestrateur : le stock (valeur, stock négatif, dormants, transferts en attente,
 * mouvements et pertes), la qualité du catalogue (doublons, marges, produits jamais vendus) et les tiers (clients
 * inactifs, dépassements de crédit, fiches incomplètes, doublons).
 *
 * Mêmes garde-fous que BusinessAssistant : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes plafonnées à 10 lignes, montants au centime. Les colonnes sensibles des tiers (codes PIN) ne sont jamais lues.
 */
class InsightsAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];

    // ── Stock ────────────────────────────────────────────────────────

    public function stockValue(): array
    {
        $rows = DB::table('warehouse_has_stock as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')->join('products as p', 'p.id', '=', 's.product_id')
            ->whereNull('p.deleted_at')->where('s.stockLevel', '>', 0)
            ->groupBy('w.id', 'w.wh_title')->selectRaw('w.wh_title AS entrepot, COUNT(DISTINCT s.product_id) AS produits, SUM(s.stockLevel) AS pieces, SUM(s.stockLevel * s.wh_average) AS valeur')
            ->orderByDesc('valeur')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun stock en entrepôt : rien à valoriser.');
        }

        $lines = $rows->map(fn ($r) => "• {$r->entrepot} — {$r->produits} produit(s), " . $this->qty((float) $r->pieces) . ' pièce(s), ' . $this->money((float) $r->valeur))->implode("\n");

        return $this->reply("Valeur du stock (au coût moyen) :\n\n{$lines}\n\nTotal : " . $this->money((float) $rows->sum('valeur')) . ' pour ' . $this->qty((float) $rows->sum('pieces')) . ' pièce(s).');
    }

    public function negativeStock(): array
    {
        $rows = $this->stockByProduct()->havingRaw('SUM(s.stockLevel) < 0')->orderByRaw('SUM(s.stockLevel)')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun produit à stock négatif.');
        }

        return $this->reply(
            "{$rows->count()} produit(s) à stock négatif (ventes ou sorties enregistrées avant l'entrée en stock, ou erreur de saisie) :\n\n" . $this->productLines($rows->take(self::LIST), 'quantite')
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : '') . "\n\nUn inventaire permet de corriger.",
            [['label' => 'Préparer un inventaire', 'text' => 'prépare un inventaire']],
        );
    }

    /** « produits dormants », « sans mouvement depuis 120 jours ». @param string $n phrase normalisée */
    public function dormantStock(string $n): array
    {
        $days = $this->days($n, 90);
        $cutoff = $this->today()->copy()->subDays($days)->toDateTimeString();
        $rows = $this->stockByProduct()->havingRaw('SUM(s.stockLevel) > 0')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_mouvements as m')->whereColumn('m.product_id', 'p.id')->where('m.status', '!=', 'cancelled')->where('m.created_at', '>=', $cutoff))
            ->orderByRaw('SUM(s.stockLevel * s.wh_average) DESC')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucun produit dormant : tous les produits en stock ont bougé depuis {$days} jour(s).");
        }

        return $this->reply(
            "{$rows->count()} produit(s) en stock sans aucun mouvement depuis {$days} jour(s), pour " . $this->money((float) $rows->sum('valeur')) . " immobilisés. Les plus chers d'abord :\n\n"
            . $this->productLines($rows->take(self::LIST), 'quantite', true) . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''),
        );
    }

    public function pendingTransfers(): array
    {
        $rows = DB::table('warehouse_transfers as t')->join('products as p', 'p.id', '=', 't.product_id')->join('warehouses as a', 'a.id', '=', 't.from_warehouse_id')->join('warehouses as b', 'b.id', '=', 't.to_warehouse_id')
            ->where('t.status', 'pending')->orderBy('t.id')->get(['t.id', 'p.p_title', 'p.p_sku', 't.quantity', 'a.wh_title AS de', 'b.wh_title AS vers', 't.created_at']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun transfert de stock en attente.');
        }

        return $this->reply("{$rows->count()} transfert(s) en attente :\n\n" . $rows->take(self::LIST)->map(fn ($r) => "• #{$r->id} — {$r->p_title} ({$r->p_sku}) × " . $this->qty((float) $r->quantity) . " : {$r->de} → {$r->vers}, demandé le " . Carbon::parse($r->created_at)->format('d/m/Y'))->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    /** « mouvements de stock du mois », « pertes du mois ». @param string $n phrase normalisée */
    public function movements(string $n): array
    {
        $loss = (bool) preg_match('/pertes?|casse/', $n);
        $from = $this->since($n, $loss ? 'month' : 'day');
        $rows = DB::table('stock_mouvements')->where('status', '!=', 'cancelled')->where('created_at', '>=', $from[0]->toDateTimeString())
            ->when($loss, fn ($q) => $q->where('reason', 'loss'))
            ->groupBy('reason', 'direction')->selectRaw('reason, direction, COUNT(*) AS n, SUM(quantity) AS qte')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply(($loss ? 'Aucune perte enregistrée' : 'Aucun mouvement de stock') . " {$from[1]}.");
        }

        $reasons = ['purchase' => 'achat', 'sale' => 'vente', 'return_in' => 'retour client', 'return_out' => 'retour fournisseur', 'transfer_in' => 'transfert reçu', 'transfer_out' => 'transfert envoyé', 'adjustment_in' => 'ajustement +', 'adjustment_out' => 'ajustement −', 'loss' => 'perte', 'initial' => 'stock initial', 'manual_entry' => 'entrée manuelle', 'manual_exit' => 'sortie manuelle', 'inventory_adjustment' => "ajustement d'inventaire", 'purchase_receipt' => 'réception', 'sale_delivery' => 'livraison', 'pos_sale' => 'vente caisse', 'pos_void' => 'annulation caisse', 'cancellation' => 'annulation'];
        $lines = $rows->map(fn ($r) => '• ' . ($reasons[$r->reason] ?? $r->reason) . ' (' . ($r->direction === 'in' ? 'entrée' : 'sortie') . ") — {$r->n} mouvement(s), " . $this->qty((float) $r->qte) . ' pièce(s)')->implode("\n");

        $extra = '';
        if ($loss) {
            $top = DB::table('stock_mouvements as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.status', '!=', 'cancelled')->where('m.reason', 'loss')->where('m.created_at', '>=', $from[0]->toDateTimeString())
                ->groupBy('p.id', 'p.p_title', 'p.p_sku')->selectRaw('p.p_title, p.p_sku, SUM(m.quantity) AS qte, SUM(m.quantity * m.unit_cost) AS cout')->orderByDesc('cout')->limit(5)->get();
            $extra = "\n\nLes plus coûteuses :\n" . $top->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — " . $this->qty((float) $r->qte) . ' pièce(s), ' . $this->money((float) $r->cout))->implode("\n");
        }

        return $this->reply('Mouvements de stock ' . $from[1] . " :\n\n{$lines}{$extra}");
    }

    // ── Catalogue ────────────────────────────────────────────────────

    public function duplicateProducts(): array
    {
        $byEan = DB::table('products')->whereNull('deleted_at')->whereNotNull('p_ean13')->where('p_ean13', '!=', '')->groupBy('p_ean13')->havingRaw('COUNT(*) > 1')->selectRaw('p_ean13 AS cle, COUNT(*) AS n')->get();
        $byTitle = DB::table('products')->whereNull('deleted_at')->groupByRaw('LOWER(TRIM(p_title))')->havingRaw('COUNT(*) > 1')->selectRaw('LOWER(TRIM(p_title)) AS cle, COUNT(*) AS n')->get();
        if ($byEan->isEmpty() && $byTitle->isEmpty()) {
            return $this->reply('Aucun doublon : pas de code-barres partagé ni de titre identique.');
        }

        $lines = [];
        foreach ($byEan->take(5) as $g) {
            $skus = DB::table('products')->whereNull('deleted_at')->where('p_ean13', $g->cle)->limit(6)->pluck('p_sku')->implode(', ');
            $lines[] = "• Code-barres {$g->cle} partagé par {$g->n} produits : {$skus}";
        }
        foreach ($byTitle->take(self::LIST - count($lines)) as $g) {
            $skus = DB::table('products')->whereNull('deleted_at')->whereRaw('LOWER(TRIM(p_title)) = ?', [$g->cle])->limit(6)->pluck('p_sku')->implode(', ');
            $lines[] = "• Titre « {$g->cle} » : {$g->n} produits ({$skus})";
        }

        return $this->reply(
            $byEan->count() . ' code(s)-barres partagé(s) et ' . $byTitle->count() . " titre(s) identique(s) :\n\n" . implode("\n", $lines)
            . "\n\nCes fiches sont à vérifier dans l'écran Produits : je ne fusionne ni ne supprime rien.",
            [['label' => 'Ouvrir les produits', 'text' => 'où sont les produits']],
        );
    }

    /** « marge par catégorie », « marge par marque ». @param string $n phrase normalisée */
    public function margins(string $n): array
    {
        $brand = (bool) preg_match('/marques?/', $n);
        $base = fn () => DB::table('products as p')->whereNull('p.deleted_at')->where('p.p_status', true)->where('p.p_salePrice', '>', 0)->where('p.p_purchasePrice', '>', 0);
        $all = $base()->selectRaw('COUNT(*) AS n, AVG((p.p_salePrice - p.p_purchasePrice) / p.p_salePrice * 100) AS marge, SUM(CASE WHEN p.p_salePrice < p.p_purchasePrice THEN 1 ELSE 0 END) AS neg')->first();
        if ((int) $all->n === 0) {
            return $this->reply('Aucun produit actif avec un prix d\'achat et un prix de vente : marge impossible à calculer.');
        }

        $q = $brand
            ? $base()->leftJoin('brands as g', 'g.id', '=', 'p.brand_id')->groupBy('g.id', 'g.br_title')->selectRaw('COALESCE(g.br_title, \'Sans marque\') AS groupe')
            : $base()->leftJoin('categories as g', 'g.id', '=', 'p.category_id')->groupBy('g.id', 'g.ctg_title')->selectRaw('COALESCE(g.ctg_title, \'Sans catégorie\') AS groupe');
        $rows = $q->selectRaw('COUNT(*) AS n, AVG((p.p_salePrice - p.p_purchasePrice) / p.p_salePrice * 100) AS marge')->orderBy('marge')->limit(self::LIST)->get();

        return $this->reply(
            'Marge brute moyenne ' . ($brand ? 'par marque' : 'par catégorie') . ' (prix de vente moins prix d\'achat, en % du prix de vente, tels que saisis dans les fiches) : ' . round((float) $all->marge, 1) . " % sur {$all->n} produit(s) actif(s)"
            . ((int) $all->neg > 0 ? ", dont {$all->neg} vendu(s) sous le prix d'achat" : '') . ".\n\nLes marges les plus basses :\n"
            . $rows->map(fn ($r) => "• {$r->groupe} — " . round((float) $r->marge, 1) . " % ({$r->n} produit(s))")->implode("\n"),
            [['label' => 'Proposer des prix (25 %)', 'text' => 'révise les prix des fiches produits avec une marge de 25 %']],
        );
    }

    /** « produits jamais vendus depuis 90 jours ». @param string $n phrase normalisée */
    public function neverSold(string $n): array
    {
        $days = $this->days($n, 90);
        $cutoff = $this->today()->copy()->subDays($days)->toDateString();
        $rows = DB::table('products as p')->leftJoinSub(DB::table('warehouse_has_stock')->selectRaw('product_id, SUM(stockLevel) AS qty')->groupBy('product_id'), 's', 's.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')->where('p.p_status', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereColumn('l.product_id', 'p.id')
                ->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('d.issued_at', '>=', $cutoff))
            ->orderByRaw('COALESCE(s.qty, 0) DESC')->get(['p.p_title', 'p.p_sku', 's.qty']);
        if ($rows->isEmpty()) {
            return $this->reply("Tous les produits actifs se sont vendus au moins une fois depuis {$days} jour(s).");
        }

        return $this->reply(
            "{$rows->count()} produit(s) actif(s) sans aucune vente depuis {$days} jour(s). Ceux qui ont le plus de stock d'abord :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — stock " . $this->qty((float) ($r->qty ?? 0)))->implode("\n") . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''),
        );
    }

    // ── Tiers ────────────────────────────────────────────────────────

    /** « clients inactifs depuis 60 jours ». @param string $n phrase normalisée */
    public function inactiveCustomers(string $n): array
    {
        $days = $this->days($n, 60);
        $cutoff = $this->today()->copy()->subDays($days)->toDateString();
        $last = DB::table('document_headers')->whereNull('deleted_at')->whereIn('document_type', self::SALES_TYPES)->whereNotIn('status', ['draft', 'cancelled'])->groupBy('thirdPartner_id')->selectRaw('thirdPartner_id, MAX(issued_at) AS derniere');
        $rows = DB::table('third_partners as t')->leftJoinSub($last, 'v', 'v.thirdPartner_id', '=', 't.id')
            ->whereNull('t.deleted_at')->where('t.tp_status', true)->whereIn('t.tp_Role', ['customer', 'both'])
            ->where(fn ($w) => $w->whereNull('v.derniere')->orWhere('v.derniere', '<', $cutoff))
            ->orderByRaw('v.derniere IS NULL')->orderBy('v.derniere')->get(['t.tp_title', 'v.derniere']);
        if ($rows->isEmpty()) {
            return $this->reply("Tous les clients actifs ont acheté depuis {$days} jour(s).");
        }
        $never = $rows->whereNull('derniere')->count();

        return $this->reply(
            "{$rows->count()} client(s) actif(s) sans achat depuis {$days} jour(s)" . ($never > 0 ? " (dont {$never} sans aucun achat)" : '') . ". Les plus récents d'abord :\n\n"
            . $rows->filter(fn ($r) => $r->derniere !== null)->take(self::LIST)->map(fn ($r) => "• {$r->tp_title} — dernier achat le " . Carbon::parse($r->derniere)->format('d/m/Y'))->implode("\n"),
        );
    }

    public function creditLimits(): array
    {
        $rows = DB::table('third_partners')->whereNull('deleted_at')->where('seuil_credit', '>', 0)->whereColumn('encours_actuel', '>', 'seuil_credit')
            ->orderByRaw('encours_actuel - seuil_credit DESC')->get(['tp_title', 'encours_actuel', 'seuil_credit']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun client ne dépasse son seuil de crédit.');
        }

        return $this->reply("{$rows->count()} client(s) au-dessus de leur seuil de crédit :\n\n" . $rows->take(self::LIST)->map(fn ($r) => "• {$r->tp_title} — encours " . $this->money((float) $r->encours_actuel) . ' pour un seuil de ' . $this->money((float) $r->seuil_credit) . ' (+' . $this->money((float) $r->encours_actuel - (float) $r->seuil_credit) . ')')->implode("\n"));
    }

    /** « clients sans téléphone, e-mail ou ICE ». @param string $n phrase normalisée */
    public function incompleteThirdParties(string $n): array
    {
        $roles = preg_match('/fournisseurs?/', $n) ? ['supplier', 'both'] : (preg_match('/clients?/', $n) ? ['customer', 'both'] : ['customer', 'supplier', 'both']);
        $who = preg_match('/fournisseurs?/', $n) ? 'fournisseur(s)' : (preg_match('/clients?/', $n) ? 'client(s)' : 'client(s) et fournisseur(s)');
        $base = fn () => DB::table('third_partners')->whereNull('deleted_at')->where('tp_status', true)->whereIn('tp_Role', $roles);
        $blank = fn (string $col) => fn ($q) => $q->whereNull($col)->orWhere($col, '');
        $total = $base()->count();
        $noPhone = $base()->where($blank('tp_phone'))->count();
        $noEmail = $base()->where($blank('tp_email'))->count();
        $noIce = $base()->where($blank('tp_Ice_Number'))->count();
        if ($total === 0) {
            return $this->reply("Aucun {$who} actif.");
        }

        $names = $base()->where(fn ($q) => $q->where($blank('tp_phone'))->orWhere($blank('tp_Ice_Number')))->orderBy('tp_title')->limit(self::LIST)->pluck('tp_title');

        return $this->reply(
            "Fiches de {$total} {$who} actif(s) :\n\n• sans téléphone : {$noPhone}\n• sans e-mail : {$noEmail}\n• sans ICE : {$noIce}"
            . ($names->isNotEmpty() ? "\n\nPremières fiches à compléter (téléphone ou ICE manquant) :\n" . $names->map(fn ($t) => "• {$t}")->implode("\n") : ''),
        );
    }

    public function duplicateThirdParties(): array
    {
        $groups = [];
        foreach (['tp_Ice_Number' => 'ICE', 'tp_phone' => 'téléphone'] as $col => $label) {
            foreach (DB::table('third_partners')->whereNull('deleted_at')->whereNotNull($col)->where($col, '!=', '')->groupBy($col)->havingRaw('COUNT(*) > 1')->selectRaw("{$col} AS cle, COUNT(*) AS n")->limit(5)->get() as $g) {
                $names = DB::table('third_partners')->whereNull('deleted_at')->where($col, $g->cle)->limit(5)->pluck('tp_title')->implode(', ');
                $groups[] = "• {$label} {$g->cle} — {$g->n} fiches : {$names}";
            }
        }
        if ($groups === []) {
            return $this->reply('Aucun doublon : pas d\'ICE ni de téléphone partagé entre deux fiches.');
        }

        return $this->reply(count($groups) . " doublon(s) possible(s) :\n\n" . implode("\n", array_slice($groups, 0, self::LIST)) . "\n\nÀ vérifier dans l'écran concerné : je ne fusionne ni ne supprime rien.");
    }

    // ── Questions courantes ──────────────────────────────────────────

    /** « stock faible », « qu'est-ce qui manque en stock », « quoi commander » : les produits au seuil d'alerte ou en dessous. */
    public function lowStock(): array
    {
        $threshold = max(0, min(100, (int) Setting::get('stock', 'seuil_alerte_stock', '5')));
        $r = (new AgentDataTools())->run('stock_bas', ['seuil' => $threshold], ['stock']);
        if (($r['produits_concernes'] ?? 0) === 0) {
            return $this->reply("Aucun produit n'est au seuil d'alerte ({$threshold} pièce(s)) ou en dessous : le stock est correct.");
        }

        return $this->reply("{$r['produits_concernes']} produit(s) à {$threshold} pièce(s) ou moins (seuil d'alerte du stock), dont {$r['en_rupture']} en rupture. Les plus bas :\n\n"
            . collect($r['plus_bas'])->take(self::LIST)->map(fn ($p) => "• {$p['titre']} ({$p['reference']}) — " . $this->qty((float) $p['quantite']) . ' pièce(s)')->implode("\n")
            . ($r['produits_concernes'] > self::LIST ? "\n… et " . ($r['produits_concernes'] - self::LIST) . ' autre(s).' : ''),
            [['label' => 'Produits bientôt en rupture', 'text' => 'produits bientôt en rupture'], ['label' => 'Préparer un inventaire', 'text' => 'prépare un inventaire']]);
    }

    /** « articles vendus à perte », « prix trop bas » : les produits dont le prix de vente est inférieur au prix d'achat. */
    public function belowCost(): array
    {
        $rows = DB::table('products')->whereNull('deleted_at')->where('p_status', true)->where('p_purchasePrice', '>', 0)->where('p_salePrice', '>', 0)->whereColumn('p_salePrice', '<', 'p_purchasePrice')
            ->orderByRaw('p_purchasePrice - p_salePrice DESC')->get(['p_title', 'p_sku', 'p_salePrice', 'p_purchasePrice']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun produit actif n'est vendu sous son prix d'achat.");
        }

        return $this->reply("{$rows->count()} produit(s) actif(s) dont le prix de vente est inférieur au prix d'achat (fiches produits) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — vendu " . $this->money((float) $r->p_salePrice) . ' pour un achat à ' . $this->money((float) $r->p_purchasePrice) . ' (-' . $this->money((float) $r->p_purchasePrice - (float) $r->p_salePrice) . ' par pièce)')->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''), [['label' => 'Proposer des prix (25 %)', 'text' => 'révise les prix des fiches produits avec une marge de 25 %']]);
    }

    /** « combien de clients j'ai », « combien de factures aujourd'hui », « quel est mon stock total ». @param string $n phrase normalisée */
    public function count(string $n): array
    {
        if (preg_match('/stock total|total du stock|quantite totale|combien de pieces/', $n)) {
            $r = DB::table('warehouse_has_stock as s')->join('products as p', 'p.id', '=', 's.product_id')->whereNull('p.deleted_at')->where('s.stockLevel', '>', 0)->selectRaw('COUNT(DISTINCT s.product_id) AS produits, COALESCE(SUM(s.stockLevel), 0) AS pieces, COALESCE(SUM(s.stockLevel * s.wh_average), 0) AS valeur')->first();

            return $this->reply("Stock total : {$r->produits} produit(s) en stock, " . $this->qty((float) $r->pieces) . ' pièce(s), ' . $this->money((float) $r->valeur) . ' au coût moyen.', [['label' => 'Valeur par entrepôt', 'text' => 'valeur du stock']]);
        }

        $noun = preg_match('/(clients?|fournisseurs?|produits?|articles?|factures?|devis|tickets?|commandes?|utilisateurs?|entrepots?|bons? de livraison)/', $n, $m) ? $m[1] : '';
        $period = ReportPeriod::resolve($n, 'day', Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay());
        $docs = fn (array $types) => DB::table('document_headers')->whereNull('deleted_at')->whereIn('document_type', $types)->whereNotIn('status', ['draft', 'cancelled'])->whereBetween('issued_at', [$period[0]->toDateString(), $period[1]->toDateString()])->count();

        return match (true) {
            (bool) preg_match('/^clients?$/', $noun)       => $this->reply(($c = DB::table('third_partners')->whereNull('deleted_at')->whereIn('tp_Role', ['customer', 'both']))->count() . ' client(s), dont ' . (clone $c)->where('tp_status', true)->count() . ' actif(s).'),
            (bool) preg_match('/^fournisseurs?$/', $noun)  => $this->reply(($c = DB::table('third_partners')->whereNull('deleted_at')->whereIn('tp_Role', ['supplier', 'both']))->count() . ' fournisseur(s), dont ' . (clone $c)->where('tp_status', true)->count() . ' actif(s).'),
            (bool) preg_match('/^(produits?|articles?)$/', $noun) => $this->reply(($c = DB::table('products')->whereNull('deleted_at'))->count() . ' produit(s), dont ' . (clone $c)->where('p_status', true)->count() . ' actif(s) et '
                . DB::table('products as p')->whereNull('p.deleted_at')->whereExists(fn ($q) => $q->selectRaw('1')->from('warehouse_has_stock as s')->whereColumn('s.product_id', 'p.id')->where('s.stockLevel', '>', 0))->count() . ' en stock.'),
            (bool) preg_match('/^utilisateurs?$/', $noun)  => $this->reply(($c = DB::table('users')->whereNull('deleted_at'))->count() . ' utilisateur(s), dont ' . (clone $c)->where('is_active', true)->count() . ' actif(s).'),
            (bool) preg_match('/^entrepots?$/', $noun)     => $this->reply(($c = DB::table('warehouses'))->count() . ' entrepôt(s), dont ' . (clone $c)->where('wh_status', true)->count() . ' actif(s).'),
            (bool) preg_match('/^factures?$/', $noun)      => $this->reply($docs(['InvoiceSale']) . " facture(s) de vente {$period[2]}."),
            (bool) preg_match('/^tickets?$/', $noun)       => $this->reply($docs(['TicketSale']) . " ticket(s) de caisse {$period[2]}."),
            (bool) preg_match('/^devis$/', $noun)          => $this->reply($docs(['QuoteSale']) . " devis {$period[2]}."),
            (bool) preg_match('/^commandes?$/', $noun)     => $this->reply($docs(['CustomerOrder']) . " commande(s) client {$period[2]}."),
            (bool) preg_match('/^bons? de livraison$/', $noun) => $this->reply($docs(['DeliveryNote']) . " bon(s) de livraison {$period[2]}."),
            default => $this->reply('Que dois-je compter ? Par exemple : « combien de clients », « combien de factures ce mois », « stock total ».', [], true),
        };
    }
    // ── Outils ───────────────────────────────────────────────────────

    private function stockByProduct(): Builder
    {
        return DB::table('warehouse_has_stock as s')->join('products as p', 'p.id', '=', 's.product_id')->whereNull('p.deleted_at')->groupBy('p.id', 'p.p_title', 'p.p_sku')
            ->selectRaw('p.p_title AS titre, p.p_sku AS reference, SUM(s.stockLevel) AS quantite, SUM(s.stockLevel * s.wh_average) AS valeur');
    }

    /** @param \Illuminate\Support\Collection<int, \stdClass> $rows */
    private function productLines($rows, string $qty, bool $withValue = false): string
    {
        return $rows->map(fn ($r) => "• {$r->titre} ({$r->reference}) — " . $this->qty((float) $r->$qty) . ' pièce(s)' . ($withValue ? ', ' . $this->money((float) $r->valeur) : ''))->implode("\n");
    }

    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    /** « depuis 120 jours » → 120, sinon la valeur par défaut. */
    private function days(string $n, int $default): int
    {
        return preg_match('/(\d{1,3})\s*j/', $n, $m) ? max(1, min(730, (int) $m[1])) : $default;
    }

    /** @return array{0: Carbon, 1: string} début de période et libellé */
    private function since(string $n, string $default): array
    {
        $today = $this->today();

        return match (true) {
            (bool) preg_match('/semaine/', $n) => [$today->copy()->startOfWeek(), 'de la semaine (depuis lundi)'],
            (bool) preg_match('/\bmois\b/', $n) => [$today->copy()->startOfMonth(), 'du mois'],
            (bool) preg_match('/aujourd|du jour|journee/', $n) => [$today, "d'aujourd'hui"],
            default => $default === 'month' ? [$today->copy()->startOfMonth(), 'du mois'] : [$today, "d'aujourd'hui"],
        };
    }

    private function qty(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',');
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'insights', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
