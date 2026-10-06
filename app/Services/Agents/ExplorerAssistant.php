<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sixième lot de lectures de l'orchestrateur : l'exploration. La recherche d'un mot dans les produits, les tiers et
 * les documents ; les documents, les produits achetés ou les achats d'un tiers ; les mouvements d'un produit ; les
 * brouillons oubliés ; les derniers documents ; les nouveaux clients et produits ; la comparaison de deux périodes ;
 * les ventes par jour de la semaine et les heures de pointe ; la valeur du stock par catégorie ; les clients par ville ;
 * les dernières connexions.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. Les connexions ne montrent que la date d'usage d'un accès, jamais
 * le jeton ; les codes PIN et mots de passe ne sont jamais lus.
 */
class ExplorerAssistant
{
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];
    private const TYPES = [
        'InvoiceSale' => 'Facture', 'TicketSale' => 'Ticket', 'QuoteSale' => 'Devis', 'CustomerOrder' => 'Commande client', 'DeliveryNote' => 'Bon de livraison',
        'CreditNoteSale' => 'Avoir client', 'ReturnSale' => 'Retour client', 'InvoicePurchase' => "Facture d'achat", 'PurchaseOrder' => 'Bon de commande', 'ReceiptNotePurchase' => 'Bon de réception',
        'CreditNotePurchase' => 'Avoir fournisseur', 'ReturnPurchase' => 'Retour fournisseur', 'StockEntry' => 'Entrée de stock', 'StockExit' => 'Sortie de stock', 'StockTransfer' => 'Transfert', 'StockAdjustmentNote' => 'Ajustement',
    ];
    private const STATUSES = ['draft' => 'brouillon', 'confirmed' => 'confirmé', 'sent' => 'envoyé', 'delivered' => 'livré', 'received' => 'reçu', 'pending' => 'en attente', 'paid' => 'payé', 'partial' => 'partiel', 'cancelled' => 'annulé', 'converted' => 'converti', 'applied' => 'appliqué'];

    // ── Recherche ────────────────────────────────────────────────────

    /** « cherche perceuse ». @param string $n phrase normalisée */
    public function search(string $n): array
    {
        $term = $this->term($n, '/^(?:cherche|trouve|recherche)(?: moi)?\s+(?:le |la |les |l.|un |une |des |du |de la )?(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Que dois-je chercher ? Par exemple : « cherche perceuse » ou « cherche FV-0001 ».', [], true);
        }
        $like = $this->like($term);
        $products = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_title', 'like', $like)->orWhere('p_sku', 'like', $like)->orWhere('p_ean13', $term))->orderBy('p_title')->limit(5)->get(['p_title', 'p_sku', 'p_status']);
        $tiers = DB::table('third_partners')->whereNull('deleted_at')->where(fn ($w) => $w->where('tp_title', 'like', $like)->orWhere('tp_code', $term)->orWhere('tp_Ice_Number', $term)->orWhere('tp_phone', 'like', $like))->orderBy('tp_title')->limit(5)->get(['tp_title', 'tp_Role']);
        $docs = DB::table('document_headers as d')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')->where('d.reference', 'like', $like)->orderByDesc('d.issued_at')->limit(5)->get(['d.reference', 'd.document_type', 'd.status', 't.tp_title']);
        if ($products->isEmpty() && $tiers->isEmpty() && $docs->isEmpty()) {
            return $this->reply("Aucun produit, tiers ni document ne correspond à « {$term} ».");
        }

        $roles = ['customer' => 'client', 'supplier' => 'fournisseur', 'both' => 'client et fournisseur'];
        $out = ["Résultats pour « {$term} » :"];
        $products->isNotEmpty() && $out[] = "\nProduits :\n" . $products->map(fn ($p) => "• {$p->p_title} ({$p->p_sku})" . ($p->p_status ? '' : ' — inactif'))->implode("\n");
        $tiers->isNotEmpty() && $out[] = "\nTiers :\n" . $tiers->map(fn ($t) => "• {$t->tp_title} ({$roles[$t->tp_Role]})")->implode("\n");
        $docs->isNotEmpty() && $out[] = "\nDocuments :\n" . $docs->map(fn ($d) => '• ' . (self::TYPES[$d->document_type] ?? $d->document_type) . " {$d->reference} — " . (self::STATUSES[$d->status] ?? $d->status) . ($d->tp_title ? " — {$d->tp_title}" : ''))->implode("\n");

        return $this->reply(implode("\n", $out) . "\n\nPour le détail : « fiche du produit … », « fiche du client … », « montre la facture … ».");
    }

    // ── Un tiers en détail ───────────────────────────────────────────

    /** « factures du client Atlas », « factures impayées du client Atlas », « documents du fournisseur Bati ». @param string $n phrase normalisée */
    public function thirdPartyDocuments(string $n): array
    {
        $t = $this->findThirdParty($n);
        if (is_array($t)) {
            return $t;
        }
        $unpaid = (bool) preg_match('/impaye|non regle|a payer|\bdus\b|\breste\b/', $n);
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.thirdPartner_id', $t->id)->where('d.status', '!=', 'cancelled')
            ->when($unpaid, fn ($q) => $q->where('f.amount_due', '>', 0)->whereNotIn('d.status', ['draft']))->orderByDesc('d.issued_at')->orderByDesc('d.id')->limit(200)->get(['d.reference', 'd.document_type', 'd.status', 'd.issued_at', 'f.total_ttc', 'f.amount_due']);
        if ($rows->isEmpty()) {
            return $this->reply($unpaid ? "{$t->tp_title} n'a aucun document impayé." : "Aucun document pour {$t->tp_title}.");
        }

        return $this->reply(($unpaid ? "{$rows->count()} document(s) impayé(s) de {$t->tp_title}, " . $this->money((float) $rows->sum('amount_due')) . " dus" : "{$rows->count()} document(s) de {$t->tp_title}") . " (les plus récents d'abord) :\n\n"
            . $rows->take(ListLimit::get())->map(fn ($r) => '• ' . (self::TYPES[$r->document_type] ?? $r->document_type) . " {$r->reference} — " . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . (self::STATUSES[$r->status] ?? $r->status) . ' — ' . $this->money((float) $r->total_ttc) . ((float) $r->amount_due > 0 ? ', reste ' . $this->money((float) $r->amount_due) : ''))->implode("\n")
            . ($rows->count() > ListLimit::get() ? "\n… et " . ($rows->count() - ListLimit::get()) . ' autre(s).' : ''));
    }

    /** « produits achetés par le client Atlas ». @param string $n phrase normalisée */
    public function productsBoughtBy(string $n): array
    {
        $t = $this->findThirdParty($n);
        if (is_array($t)) {
            return $t;
        }
        $rows = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')->whereNull('d.deleted_at')->where('d.thirdPartner_id', $t->id)->whereIn('d.document_type', self::SALES_TYPES)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->where('l.status', 'active')->where('l.line_type', 'product')->where('d.issued_at', '>=', $this->today()->copy()->subYear()->toDateString())
            ->groupBy('l.product_id', 'l.designation')->selectRaw('l.designation AS produit, SUM(l.quantity) AS qte, SUM(l.total_ligne_ht) AS ht, MAX(d.issued_at) AS derniere')->orderByDesc('ht')->limit(ListLimit::get())->get();
        if ($rows->isEmpty()) {
            return $this->reply("{$t->tp_title} n'a rien acheté sur les 12 derniers mois.");
        }

        return $this->reply("Produits achetés par {$t->tp_title} sur 12 mois (chiffre HT) :\n\n" . $rows->map(fn ($r) => "• {$r->produit} — " . $this->qty((float) $r->qte) . ' pièce(s), ' . $this->money((float) $r->ht) . ', dernier achat le ' . Carbon::parse($r->derniere)->format('d/m/Y'))->implode("\n"));
    }

    // ── Stock d'un produit ───────────────────────────────────────────

    /** « mouvements du produit PRC1 ». @param string $n phrase normalisée */
    public function productMovements(string $n): array
    {
        $term = $this->term($n, '/mouvements?\s+(?:du produit|de l.article|de la reference|du)\s+(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quel produit ? Par exemple : « mouvements du produit PRC1 ».', [], true);
        }
        $like = $this->like($term);
        $p = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_sku', $term)->orWhere('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->orderByRaw('p_sku = ? DESC', [$term])->orderBy('p_title')->first(['id', 'p_title', 'p_sku']);
        if (!$p) {
            return $this->reply("Je ne trouve aucun produit correspondant à « {$term} ».", [], true);
        }
        $rows = DB::table('stock_mouvements as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->leftJoin('warehouses as w', 'w.id', '=', 'm.warehouse_id')->where('m.product_id', $p->id)->where('m.status', '!=', 'cancelled')
            ->orderByDesc('m.id')->limit(ListLimit::get())->get(['m.created_at', 'm.direction', 'm.reason', 'm.quantity', 'm.stock_after', 'm.document_reference', 'u.name', 'w.wh_title']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun mouvement de stock pour {$p->p_title} ({$p->p_sku}).");
        }

        return $this->reply("Derniers mouvements de {$p->p_title} ({$p->p_sku}) :\n\n" . $rows->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m/Y H:i') . ' — ' . ($r->direction === 'in' ? '+' : '-') . $this->qty((float) $r->quantity) . " ({$r->reason})" . ($r->wh_title ? " — {$r->wh_title}" : '') . ($r->document_reference ? " — {$r->document_reference}" : '') . ' — stock après : ' . $this->qty((float) $r->stock_after) . ($r->name ? " — {$r->name}" : ''))->implode("\n"));
    }

    /** « valeur du stock par catégorie ». */
    public function stockValueByCategory(): array
    {
        $rows = DB::table('warehouse_has_stock as s')->join('products as p', 'p.id', '=', 's.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->whereNull('p.deleted_at')->where('s.stockLevel', '>', 0)
            ->groupBy('c.id', 'c.ctg_title')->selectRaw('COALESCE(c.ctg_title, \'Sans catégorie\') AS categorie, COUNT(DISTINCT p.id) AS produits, SUM(s.stockLevel) AS pieces, SUM(s.stockLevel * s.wh_average) AS valeur')->orderByDesc('valeur')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun stock en entrepôt : rien à valoriser.');
        }
        $total = max((float) $rows->sum('valeur'), 0.01);

        return $this->reply('Valeur du stock par catégorie (au coût moyen, ' . $this->money($total) . " au total) :\n\n"
            . $rows->take(ListLimit::get())->map(fn ($r) => "• {$r->categorie} — " . $this->money((float) $r->valeur) . ' (' . round((float) $r->valeur / $total * 100) . " %), {$r->produits} produit(s)")->implode("\n") . ($rows->count() > ListLimit::get() ? "\n… et " . ($rows->count() - ListLimit::get()) . ' autre(s) catégorie(s).' : ''));
    }

    // ── Documents ────────────────────────────────────────────────────

    /** « brouillons anciens », « brouillons de plus de 15 jours ». @param string $n phrase normalisée */
    public function staleDrafts(string $n): array
    {
        $days = preg_match('/(\d{1,3})\s*j/', $n, $m) ? max(1, min(365, (int) $m[1])) : 7;
        $cutoff = $this->today()->copy()->subDays($days)->toDateTimeString();
        $rows = DB::table('document_headers as d')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')->where('d.status', 'draft')->where('d.created_at', '<', $cutoff)
            ->whereNotIn('d.document_type', ['StockEntry', 'StockExit', 'StockTransfer', 'StockAdjustmentNote'])->orderBy('d.created_at')->get(['d.reference', 'd.document_type', 'd.created_at', 'u.name AS par', 't.tp_title']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun brouillon de plus de {$days} jour(s) : rien n'est oublié.");
        }

        return $this->reply("{$rows->count()} brouillon(s) de plus de {$days} jour(s), les plus anciens d'abord (à confirmer ou supprimer) :\n\n"
            . $rows->take(ListLimit::get())->map(fn ($r) => '• ' . (self::TYPES[$r->document_type] ?? $r->document_type) . " {$r->reference} — créé le " . Carbon::parse($r->created_at)->format('d/m/Y') . ($r->tp_title ? " — {$r->tp_title}" : '') . ($r->par ? " — {$r->par}" : ''))->implode("\n")
            . ($rows->count() > ListLimit::get() ? "\n… et " . ($rows->count() - ListLimit::get()) . ' autre(s).' : ''));
    }

    public function latestDocuments(): array
    {
        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->orderByDesc('d.id')->limit(ListLimit::get())->get(['d.reference', 'd.document_type', 'd.status', 'd.created_at', 'u.name AS par', 't.tp_title', 'f.total_ttc']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun document pour le moment.');
        }

        return $this->reply("Les {$rows->count()} derniers documents créés :\n\n" . $rows->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . ' — ' . (self::TYPES[$r->document_type] ?? $r->document_type) . " {$r->reference} — " . (self::STATUSES[$r->status] ?? $r->status) . ' — ' . ($r->tp_title ?? 'sans tiers') . ' — ' . $this->money((float) $r->total_ttc) . ($r->par ? " — {$r->par}" : ''))->implode("\n"));
    }

    // ── Nouveautés et comparaisons ───────────────────────────────────

    /** « nouveaux clients du mois », « nouveaux produits de la semaine ». @param string $n phrase normalisée */
    public function newcomers(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $products = (bool) preg_match('/produits?|articles?/', $n);
        if ($products) {
            $rows = DB::table('products')->whereNull('deleted_at')->where('created_at', '>=', $from->toDateTimeString())->where('created_at', '<=', $to->copy()->endOfDay()->toDateTimeString())->orderByDesc('id')->get(['p_title as nom', 'p_sku as ref']);
        } else {
            $rows = DB::table('third_partners')->whereNull('deleted_at')->where('created_at', '>=', $from->toDateTimeString())->where('created_at', '<=', $to->copy()->endOfDay()->toDateTimeString())->orderByDesc('id')->get(['tp_title as nom', 'tp_Role as ref']);
        }
        if ($rows->isEmpty()) {
            return $this->reply('Aucun nouveau ' . ($products ? 'produit' : 'tiers') . " {$label}.");
        }
        $roles = ['customer' => 'client', 'supplier' => 'fournisseur', 'both' => 'client et fournisseur'];

        return $this->reply("{$rows->count()} nouveau(x) " . ($products ? 'produit(s)' : 'tiers') . " {$label} :\n\n" . $rows->take(ListLimit::get())->map(fn ($r) => "• {$r->nom} (" . ($products ? $r->ref : $roles[$r->ref]) . ')')->implode("\n")
            . ($rows->count() > ListLimit::get() ? "\n… et " . ($rows->count() - ListLimit::get()) . ' autre(s).' : ''));
    }

    /** « compare ce mois au mois dernier », « compare cette semaine à la semaine dernière ». @param string $n phrase normalisée */
    public function comparePeriods(string $n): array
    {
        $today = $this->today();
        if (preg_match('/semaine/', $n)) {
            $a = [$today->copy()->startOfWeek(), $today, 'cette semaine'];
            $b = [$today->copy()->startOfWeek()->subWeek(), $today->copy()->startOfWeek()->subWeek()->addDays($today->diffInDays($today->copy()->startOfWeek())), 'la semaine dernière (même nombre de jours)'];
        } elseif (preg_match('/annee|an dernier|l.an passe/', $n)) {
            $a = [$today->copy()->startOfMonth(), $today, 'ce mois'];
            $b = [$today->copy()->subYear()->startOfMonth(), $today->copy()->subYear(), 'le même mois il y a un an (même nombre de jours)'];
        } else {
            $a = [$today->copy()->startOfMonth(), $today, 'ce mois'];
            $b = [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow(), 'le mois dernier (même nombre de jours)'];
        }
        $stat = function (array $p) {
            $r = $this->salesDocs($p[0], $p[1])->selectRaw('COUNT(*) AS n, COALESCE(SUM(f.total_ttc), 0) AS ttc')->first();
            $pay = DB::table('payments as pa')->join('document_headers as d', 'd.id', '=', 'pa.document_header_id')->whereNull('d.deleted_at')->whereIn('d.document_type', ['InvoiceSale', 'TicketSale', 'DeliveryNote', 'CustomerOrder'])
                ->whereBetween('pa.paid_at', [$p[0]->toDateString(), $p[1]->toDateString()])->sum('pa.amount');

            return ['n' => (int) $r->n, 'ttc' => (float) $r->ttc, 'avg' => (int) $r->n > 0 ? (float) $r->ttc / (int) $r->n : 0.0, 'paid' => (float) $pay];
        };
        $x = $stat($a);
        $y = $stat($b);
        $delta = fn (float $new, float $old) => $old > 0 ? (($d = round(($new - $old) / $old * 100)) >= 0 ? '+' : '') . $d . ' %' : ($new > 0 ? 'nouveau' : '—');

        return $this->reply("Comparaison : {$a[2]} ({$a[0]->format('d/m')} → {$a[1]->format('d/m')}) et {$b[2]} ({$b[0]->format('d/m/Y')} → {$b[1]->format('d/m/Y')}).\n\n"
            . '• Chiffre d\'affaires TTC : ' . $this->money($x['ttc']) . ' contre ' . $this->money($y['ttc']) . ' (' . $delta($x['ttc'], $y['ttc']) . ")\n"
            . "• Nombre de ventes : {$x['n']} contre {$y['n']} (" . $delta((float) $x['n'], (float) $y['n']) . ")\n"
            . '• Panier moyen : ' . $this->money($x['avg']) . ' contre ' . $this->money($y['avg']) . ' (' . $delta($x['avg'], $y['avg']) . ")\n"
            . '• Encaissements : ' . $this->money($x['paid']) . ' contre ' . $this->money($y['paid']) . ' (' . $delta($x['paid'], $y['paid']) . ')');
    }

    /** « ventes par jour de la semaine ». */
    public function byWeekday(): array
    {
        $from = $this->today()->copy()->subDays(89);
        $rows = $this->salesDocs($from, $this->today())->groupByRaw('DAYOFWEEK(d.issued_at)')->selectRaw('DAYOFWEEK(d.issued_at) AS jour, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->get()->keyBy('jour');
        if ($rows->isEmpty()) {
            return $this->reply('Aucune vente sur les 90 derniers jours.');
        }
        $names = [2 => 'Lundi', 3 => 'Mardi', 4 => 'Mercredi', 5 => 'Jeudi', 6 => 'Vendredi', 7 => 'Samedi', 1 => 'Dimanche'];
        $max = max(1.0, (float) $rows->max('ttc'));
        $lines = [];
        foreach ($names as $d => $label) {
            $v = (float) ($rows[$d]->ttc ?? 0);
            $lines[] = str_pad($label, 9) . ' ' . str_repeat('█', (int) round($v / $max * 14)) . ' ' . $this->money($v) . ' (' . (int) ($rows[$d]->n ?? 0) . ')';
        }

        return $this->reply("Ventes TTC par jour de la semaine, sur les 90 derniers jours :\n\n" . implode("\n", $lines));
    }

    /** « heures de pointe » : les tickets de caisse des 30 derniers jours, par heure. */
    public function peakHours(): array
    {
        $tz = Setting::get('locale', 'timezone') ?: config('app.timezone');
        $offset = (int) (Carbon::now($tz)->utcOffset() - Carbon::now(config('app.timezone'))->utcOffset());   // minutes entre l'heure de l'entreprise et celle de la base
        $rows = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.document_type', 'TicketSale')->whereNotIn('d.status', ['draft', 'cancelled'])
            ->where('d.created_at', '>=', $this->today()->copy()->subDays(29)->toDateTimeString())->groupByRaw("HOUR(DATE_ADD(d.created_at, INTERVAL {$offset} MINUTE))")
            ->selectRaw("HOUR(DATE_ADD(d.created_at, INTERVAL {$offset} MINUTE)) AS heure, COUNT(*) AS n, SUM(f.total_ttc) AS ttc")->orderBy('heure')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun ticket de caisse sur les 30 derniers jours.');
        }
        $max = max(1, (int) $rows->max('n'));

        return $this->reply("Tickets de caisse par heure sur les 30 derniers jours (heure de l'entreprise) :\n\n" . $rows->map(fn ($r) => str_pad((string) $r->heure, 2, '0', STR_PAD_LEFT) . ' h ' . str_repeat('█', (int) round($r->n / $max * 14)) . " {$r->n} ticket(s), " . $this->money((float) $r->ttc))->implode("\n"));
    }

    // ── Tiers et accès ───────────────────────────────────────────────

    public function thirdPartiesByCity(): array
    {
        $rows = DB::table('third_partners')->whereNull('deleted_at')->where('tp_status', true)->groupBy('tp_city')->selectRaw('COALESCE(NULLIF(tp_city, \'\'), \'Ville non renseignée\') AS ville, COUNT(*) AS n, SUM(tp_Role IN (\'customer\', \'both\')) AS clients, SUM(tp_Role IN (\'supplier\', \'both\')) AS fournisseurs')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun tiers actif.');
        }

        return $this->reply('Tiers actifs par ville (' . $rows->sum('n') . " au total) :\n\n" . $rows->take(ListLimit::get())->map(fn ($r) => "• {$r->ville} — {$r->n} ({$r->clients} client(s), {$r->fournisseurs} fournisseur(s))")->implode("\n") . ($rows->count() > ListLimit::get() ? "\n… et " . ($rows->count() - ListLimit::get()) . ' autre(s) ville(s).' : ''));
    }

    /** « dernières connexions » : la dernière utilisation d'un accès par utilisateur (jamais le jeton). */
    public function lastLogins(): array
    {
        $rows = DB::table('personal_access_tokens as k')->join('users as u', fn ($j) => $j->on('u.id', '=', 'k.tokenable_id')->where('k.tokenable_type', 'like', '%User'))->whereNull('u.deleted_at')
            ->groupBy('u.id', 'u.name', 'u.is_active')->selectRaw('u.name, u.is_active, MAX(k.last_used_at) AS derniere')->orderByRaw('MAX(k.last_used_at) IS NULL')->orderByDesc('derniere')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun accès enregistré.');
        }

        return $this->reply("Dernière utilisation de l'application par utilisateur :\n\n" . $rows->take(ListLimit::get())->map(fn ($r) => "• {$r->name} — " . ($r->derniere ? Carbon::parse($r->derniere)->format('d/m/Y H:i') : 'jamais utilisé') . ($r->is_active ? '' : ' (compte inactif)'))->implode("\n"));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** Le tiers désigné dans la phrase, ou une réponse (erreur, choix) à renvoyer telle quelle. @return object|array<string, mixed> */
    private function findThirdParty(string $n): object|array
    {
        $term = $this->term($n, '/\b(?:du|de la|de l.|des|d.|par le|par la|par)\s+(?:client|fournisseur|tiers)\s+(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quel client ou fournisseur ? Par exemple : « factures du client Atlas ».', [], true);
        }
        $matches = DB::table('third_partners')->whereNull('deleted_at')->where(fn ($w) => $w->where('tp_title', 'like', $this->like($term))->orWhere('tp_code', $term))->orderBy('tp_title')->limit(6)->get(['id', 'tp_title']);
        if ($matches->isEmpty()) {
            return $this->reply("Je ne trouve aucun tiers correspondant à « {$term} ».", [], true);
        }
        if ($matches->count() > 1) {
            return $this->reply("Plusieurs tiers correspondent à « {$term} » :\n\n" . $matches->take(5)->map(fn ($t) => "• {$t->tp_title}")->implode("\n") . "\n\nPrécisez le nom complet.");
        }

        return $matches->first();
    }

    private function term(string $n, string $regex): ?string
    {
        if (!preg_match($regex, trim($n), $m)) {
            return null;
        }
        $t = trim($m[1], " ?.!");

        return mb_strlen($t) >= 2 ? $t : null;
    }

    private function like(string $term): string
    {
        return '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
    }

    private function salesDocs(Carbon $from, Carbon $to): Builder
    {
        return DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)
            ->whereNotIn('d.status', ['draft', 'cancelled'])->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()]);
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'explorer', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
