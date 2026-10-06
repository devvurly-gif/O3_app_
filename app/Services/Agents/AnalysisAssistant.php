<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quatrième lot de lectures de l'orchestrateur : les classements (produits les plus vendus, ventes par vendeur ou par
 * caisse, meilleurs clients), les tiers (fournisseurs inactifs, clients en compte à facturer), la qualité du catalogue
 * (TVA inhabituelle, codes-barres invalides, produits par catégorie, sans marque, absents d'une liste de prix), le stock
 * (mouvements en attente, ajustements d'inventaire), l'historique d'une fiche (qui l'a modifiée) et les actions des agents.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. L'historique n'affiche que le libellé du journal, jamais ses
 * propriétés ; les codes PIN, mots de passe et jetons ne sont jamais lus.
 */
class AnalysisAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];

    // ── Classements de ventes ────────────────────────────────────────

    /** « top 10 des produits vendus ce mois ». @param string $n phrase normalisée */
    public function topProducts(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $limit = preg_match('/top\s*(\d{1,2})|(\d{1,2})\s*(?:premiers|meilleurs|plus)/', $n, $m) ? max(1, min(25, (int) ($m[1] !== '' ? $m[1] : $m[2]))) : self::LIST;
        $rows = $this->saleLines($from, $to)->groupBy('l.product_id', 'l.designation')->selectRaw('l.designation AS produit, SUM(l.quantity) AS qte, SUM(l.total_ligne_ht) AS ht')->orderByDesc('ht')->limit($limit)->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }

        return $this->reply("Les {$rows->count()} produits les plus vendus {$label} (en chiffre d'affaires HT) :\n\n"
            . $rows->map(fn ($r, $i) => ($i + 1) . ". {$r->produit} — " . $this->qty((float) $r->qte) . ' vendu(s), ' . $this->money((float) $r->ht))->implode("\n"));
    }

    /** « ventes du mois par vendeur ». @param string $n phrase normalisée */
    public function salesBySeller(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = $this->salesDocs($from, $to)->leftJoin('users as u', 'u.id', '=', 'd.user_id')->groupBy('u.id', 'u.name')
            ->selectRaw('COALESCE(u.name, \'Inconnu\') AS vendeur, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }

        return $this->reply("Ventes {$label} par utilisateur (celui qui a établi le document) : " . $this->money((float) $rows->sum('ttc')) . " TTC.\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->vendeur} — {$r->n} vente(s), " . $this->money((float) $r->ttc))->implode("\n"));
    }

    /** « ventes du mois par caisse ». @param string $n phrase normalisée */
    public function salesByRegister(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = $this->salesDocs($from, $to)->join('pos_sessions as s', 's.id', '=', 'd.pos_session_id')->leftJoin('users as u', 'u.id', '=', 's.user_id')->groupBy('s.id', 'u.name', 's.opened_at')
            ->selectRaw('s.id AS session, COALESCE(u.name, \'?\') AS caissier, s.opened_at, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('s.id')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente en caisse {$label}.");
        }

        return $this->reply("Ventes en caisse {$label} : {$rows->sum('n')} ticket(s) sur {$rows->count()} session(s), " . $this->money((float) $rows->sum('ttc')) . " TTC.\n\nDernières sessions :\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• Session #{$r->session} — {$r->caissier} — ouverte le " . Carbon::parse($r->opened_at)->format('d/m H:i') . " — {$r->n} ticket(s), " . $this->money((float) $r->ttc))->implode("\n"));
    }

    /** « meilleurs clients du trimestre ». @param string $n phrase normalisée */
    public function bestCustomers(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'quarter');
        $rows = $this->salesDocs($from, $to)->join('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->groupBy('t.id', 't.tp_title')
            ->selectRaw('t.tp_title AS client, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }
        $total = (float) $rows->sum('ttc');

        return $this->reply("Meilleurs clients {$label} (sur " . $this->money($total) . " TTC) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r, $i) => ($i + 1) . ". {$r->client} — {$r->n} vente(s), " . $this->money((float) $r->ttc) . ' (' . round((float) $r->ttc / max($total, 0.01) * 100) . ' %)')->implode("\n"));
    }

    // ── Tiers ────────────────────────────────────────────────────────

    /** « fournisseurs inactifs depuis un an ». @param string $n phrase normalisée */
    public function inactiveSuppliers(string $n): array
    {
        $days = match (true) {
            (bool) preg_match('/(\d{1,3})\s*j/', $n, $m)  => max(1, min(1095, (int) $m[1])),
            (bool) preg_match('/(\d{1,2})\s*mois/', $n, $k) => max(1, min(36, (int) $k[1])) * 30,
            default                                        => 365,
        };
        $cutoff = $this->today()->copy()->subDays($days)->toDateString();
        $last = DB::table('document_headers')->whereNull('deleted_at')->where('document_type', 'InvoicePurchase')->whereNotIn('status', ['draft', 'cancelled'])->groupBy('thirdPartner_id')->selectRaw('thirdPartner_id, MAX(issued_at) AS derniere');
        $rows = DB::table('third_partners as t')->leftJoinSub($last, 'v', 'v.thirdPartner_id', '=', 't.id')->whereNull('t.deleted_at')->where('t.tp_status', true)->whereIn('t.tp_Role', ['supplier', 'both'])
            ->where(fn ($w) => $w->whereNull('v.derniere')->orWhere('v.derniere', '<', $cutoff))->orderByRaw('v.derniere IS NULL')->orderBy('v.derniere')->get(['t.tp_title', 'v.derniere']);
        if ($rows->isEmpty()) {
            return $this->reply("Tous les fournisseurs actifs ont une facture d'achat depuis {$days} jour(s).");
        }

        return $this->reply("{$rows->count()} fournisseur(s) actif(s) sans facture d'achat depuis {$days} jour(s) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->tp_title} — " . ($r->derniere ? 'dernière facture le ' . Carbon::parse($r->derniere)->format('d/m/Y') : 'jamais facturé'))->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : '') . "\n\nIls peuvent être désactivés dans l'écran concerné : je ne change rien.");
    }

    public function accountCustomersToInvoice(): array
    {
        $rows = DB::table('third_partners as t')->join('document_headers as d', 'd.thirdPartner_id', '=', 't.id')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('t.deleted_at')->whereNull('d.deleted_at')->where('t.tp_status', true)->where('t.type_compte', 'en_compte')->where('d.document_type', 'DeliveryNote')->whereIn('d.status', ['confirmed', 'sent', 'delivered', 'pending'])
            ->groupBy('t.id', 't.tp_title', 't.frequence_facturation')->selectRaw('t.tp_title AS client, t.frequence_facturation AS frequence, COUNT(*) AS n, SUM(f.total_ttc) AS ttc, MIN(d.issued_at) AS premier')->orderByDesc('ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun client en compte n\'a de bon de livraison en attente de facturation.');
        }

        return $this->reply("{$rows->count()} client(s) en compte avec des bons de livraison à facturer, pour " . $this->money((float) $rows->sum('ttc')) . " TTC :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->client} — {$r->n} bon(s), " . $this->money((float) $r->ttc) . ' depuis le ' . Carbon::parse($r->premier)->format('d/m/Y') . ($r->frequence ? " (facturation {$r->frequence})" : ''))->implode("\n"));
    }

    // ── Catalogue ────────────────────────────────────────────────────

    public function vatRates(): array
    {
        $rows = DB::table('products')->whereNull('deleted_at')->where('p_status', true)->groupBy('p_taxRate')->selectRaw('p_taxRate AS taux, COUNT(*) AS n')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun produit actif.');
        }
        $unusual = $rows->filter(fn ($r) => (float) $r->taux !== 20.0);

        $body = "Taux de TVA des produits actifs :\n\n" . $rows->map(fn ($r) => '• ' . rtrim(rtrim(number_format((float) $r->taux, 2, ',', ''), '0'), ',') . " % — {$r->n} produit(s)")->implode("\n");
        if ($unusual->isNotEmpty()) {
            $examples = DB::table('products')->whereNull('deleted_at')->where('p_status', true)->where('p_taxRate', '!=', 20)->orderBy('p_title')->limit(5)->get(['p_title', 'p_sku', 'p_taxRate']);
            $body .= "\n\nTaux différents de 20 % (à vérifier) :\n" . $examples->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — " . round((float) $r->p_taxRate, 2) . ' %')->implode("\n");
        } else {
            $body .= "\n\nTous les produits actifs sont à 20 %.";
        }

        return $this->reply($body);
    }

    public function invalidBarcodes(): array
    {
        $bad = [];
        $total = 0;
        foreach (DB::table('products')->whereNull('deleted_at')->whereNotNull('p_ean13')->where('p_ean13', '!=', '')->orderBy('id')->get(['p_title', 'p_sku', 'p_ean13']) as $p) {
            $total++;
            self::validEan13((string) $p->p_ean13) || $bad[] = $p;
        }
        if ($total === 0) {
            return $this->reply('Aucun produit n\'a de code-barres EAN13 renseigné.');
        }
        if ($bad === []) {
            return $this->reply("Les {$total} code(s)-barres renseignés sont tous valides (13 chiffres et clé de contrôle correcte).");
        }

        return $this->reply(count($bad) . " code(s)-barres invalide(s) sur {$total} (il faut 13 chiffres et une clé de contrôle correcte) :\n\n"
            . implode("\n", array_map(fn ($p) => "• {$p->p_title} ({$p->p_sku}) — « {$p->p_ean13} »", array_slice($bad, 0, self::LIST)))
            . (count($bad) > self::LIST ? "\n… et " . (count($bad) - self::LIST) . ' autre(s).' : '') . "\n\nÀ corriger dans l'écran Produits : je ne modifie aucun code.");
    }

    public function productsByCategory(): array
    {
        $rows = DB::table('categories as c')->leftJoin('products as p', fn ($j) => $j->on('p.category_id', '=', 'c.id')->whereNull('p.deleted_at'))->groupBy('c.id', 'c.ctg_title')
            ->selectRaw('c.ctg_title AS categorie, COALESCE(SUM(p.p_status = 1), 0) AS actifs, COALESCE(SUM(p.p_status = 0), 0) AS inactifs')->orderByRaw('COALESCE(SUM(p.p_status = 1), 0) DESC')->get();
        $rows = $rows->filter(fn ($r) => (int) $r->actifs + (int) $r->inactifs > 0)->values();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun produit.');
        }

        return $this->reply('Produits par catégorie (' . $rows->sum('actifs') . ' actif(s), ' . $rows->sum('inactifs') . " inactif(s)) :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->categorie} — {$r->actifs} actif(s), {$r->inactifs} inactif(s)")->implode("\n") . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s) catégorie(s).' : ''));
    }

    public function productsWithoutBrand(): array
    {
        $rows = DB::table('products')->whereNull('deleted_at')->where('p_status', true)->whereNull('brand_id')->orderBy('p_title')->get(['p_title', 'p_sku']);
        if ($rows->isEmpty()) {
            return $this->reply('Tous les produits actifs ont une marque.');
        }

        return $this->reply("{$rows->count()} produit(s) actif(s) sans marque :\n\n" . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku})")->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''), [['label' => 'Compléter marques et catégories (IA)', 'text' => 'complète les descriptions, catégories et marques des fiches produits']]);
    }

    /** « produits absents de la liste de prix revendeur ». @param string $n phrase normalisée */
    public function priceListGaps(string $n): array
    {
        $names = DB::table('price_lists')->orderBy('name')->pluck('name', 'id');
        if ($names->isEmpty()) {
            return $this->reply('Aucune liste de prix n\'est définie.');
        }
        $term = preg_match('/liste (?:de prix|tarifaire)\s+(?:du |de la |des |de l.|d.)?(.{2,40})$/', trim($n), $m) ? trim($m[1]) : '';
        $id = $term === '' ? null : $names->search(fn ($name) => str_contains(mb_strtolower(\Illuminate\Support\Str::ascii((string) $name)), $term));
        if ($id === null || $id === false) {
            return $this->reply('De quelle liste de prix parlez-vous ? Listes existantes : ' . $names->implode(', ') . '. Par exemple : « produits absents de la liste de prix ' . mb_strtolower((string) $names->first()) . ' ».', [], true);
        }

        $rows = DB::table('products as p')->whereNull('p.deleted_at')->where('p.p_status', true)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('price_list_items as i')->whereColumn('i.product_id', 'p.id')->where('i.price_list_id', $id))
            ->orderBy('p.p_title')->get(['p.p_title', 'p.p_sku']);
        if ($rows->isEmpty()) {
            return $this->reply("Tous les produits actifs figurent dans la liste « {$names[$id]} ».");
        }

        return $this->reply("{$rows->count()} produit(s) actif(s) absent(s) de la liste « {$names[$id]} » :\n\n" . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku})")->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : ''));
    }

    // ── Stock ────────────────────────────────────────────────────────

    public function pendingMovements(): array
    {
        $rows = DB::table('stock_mouvements as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.status', 'pending')->orderBy('m.created_at')->get(['p.p_title', 'p.p_sku', 'm.direction', 'm.reason', 'm.quantity', 'm.created_at']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun mouvement de stock en attente : tout est appliqué.');
        }

        return $this->reply("{$rows->count()} mouvement(s) de stock en attente d'application, les plus anciens d'abord :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — " . ($r->direction === 'in' ? 'entrée' : 'sortie') . ' de ' . $this->qty((float) $r->quantity) . " ({$r->reason}) — du " . Carbon::parse($r->created_at)->format('d/m/Y'))->implode("\n"));
    }

    /** « ajustements d'inventaire récents ». @param string $n phrase normalisée */
    public function inventoryAdjustments(string $n): array
    {
        [$from, , $label] = $this->period($n, 'month');
        $rows = DB::table('stock_mouvements as m')->join('products as p', 'p.id', '=', 'm.product_id')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->where('m.status', '!=', 'cancelled')
            ->whereIn('m.reason', ['inventory_adjustment', 'adjustment_in', 'adjustment_out', 'stock_adjustment'])->where('m.created_at', '>=', $from->toDateTimeString())->orderByDesc('m.id')
            ->get(['p.p_title', 'p.p_sku', 'm.direction', 'm.quantity', 'm.created_at', 'u.name']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun ajustement de stock {$label}.");
        }
        $byUser = $rows->groupBy(fn ($r) => $r->name ?? 'inconnu')->map->count()->sortDesc();

        return $this->reply("{$rows->count()} ajustement(s) de stock {$label}, par " . $byUser->map(fn ($c, $u) => "{$u} ({$c})")->implode(', ') . ".\n\nLes plus récents :\n"
            . $rows->take(self::LIST)->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . " — {$r->p_title} ({$r->p_sku}) — " . ($r->direction === 'in' ? '+' : '-') . $this->qty((float) $r->quantity) . ' — ' . ($r->name ?? 'inconnu'))->implode("\n"));
    }

    // ── Historique et agents ─────────────────────────────────────────

    /** « qui a modifié la facture FV-001 », « historique du produit PRC1 ». @param string $n phrase normalisée */
    public function history(string $n): array
    {
        $term = preg_match('/(?:modifie|change|supprime|cree|touche|historique (?:de|du|des))\s+(?:la |le |les |l.|un |une )?(?:facture|devis|bon de \w+|bon|produit|client|fournisseur|document|article|fiche)?\s*(.{2,60})$/', trim($n), $m) ? trim($m[1], " ?.") : '';
        if (mb_strlen($term) < 2) {
            return $this->reply('Quelle fiche ? Par exemple : « qui a modifié la facture FV-001 » ou « historique du produit PRC1 ».', [], true);
        }

        $subject = null;
        $doc = DB::table('document_headers as d')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->where('d.reference', $term)->first(['d.id', 'd.reference', 'd.document_type', 'd.created_at', 'u.name AS par']);
        if ($doc) {
            $subject = ['DocumentHeader', $doc->id, "{$doc->document_type} {$doc->reference}", 'créé le ' . Carbon::parse($doc->created_at)->format('d/m/Y H:i') . ($doc->par ? " par {$doc->par}" : '')];
        } elseif ($p = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_sku', $term)->orWhere('p_title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%'))->orderBy('p_title')->first(['id', 'p_title', 'p_sku', 'created_at'])) {
            $subject = ['Product', $p->id, "produit {$p->p_title} ({$p->p_sku})", 'créé le ' . Carbon::parse($p->created_at)->format('d/m/Y H:i')];
        } elseif ($t = DB::table('third_partners')->whereNull('deleted_at')->where('tp_title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%')->orderBy('tp_title')->first(['id', 'tp_title', 'created_at'])) {
            $subject = ['ThirdPartner', $t->id, "tiers {$t->tp_title}", 'créé le ' . Carbon::parse($t->created_at)->format('d/m/Y H:i')];
        }
        if ($subject === null) {
            return $this->reply("Je ne trouve ni document, ni produit, ni tiers correspondant à « {$term} ».", [], true);
        }

        $rows = DB::table('activity_log as a')->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'a.causer_id')->where('a.causer_type', 'like', '%User'))
            ->where('a.subject_type', 'like', '%\\' . $subject[0])->where('a.subject_id', $subject[1])->orderByDesc('a.id')->limit(self::LIST)->get(['a.created_at', 'a.description', 'a.event', 'u.name']);

        return $this->reply("Historique de {$subject[2]} ({$subject[3]}) :\n\n" . ($rows->isEmpty() ? 'Aucune modification enregistrée au journal.'
            : $rows->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m/Y H:i') . ' — ' . ($r->name ?? 'système') . ' — ' . ($r->description ?: ($r->event ?? '?')))->implode("\n")));
    }

    /** « actions des agents aujourd'hui ». @param string $n phrase normalisée */
    public function agentActions(string $n): array
    {
        [$from, , $label] = $this->period($n, 'day');
        $rows = DB::table('agent_actions as a')->leftJoin('agents as g', 'g.id', '=', 'a.agent_id')->where('a.created_at', '>=', $from->toDateTimeString())->orderByDesc('a.id')->get(['a.created_at', 'a.action', 'a.level', 'g.name']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucune action d'agent {$label}.");
        }
        $by = $rows->groupBy(fn ($r) => $r->name ?? 'orchestrateur')->map->count()->sortDesc();

        return $this->reply("{$rows->count()} action(s) d'agents {$label} : " . $by->map(fn ($c, $a) => "{$a} ({$c})")->implode(', ') . ".\n\nLes plus récentes :\n"
            . $rows->take(self::LIST)->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . ' — ' . ($r->name ?? 'orchestrateur') . " — {$r->action}")->implode("\n"));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** EAN-13 : 13 chiffres et clé de contrôle correcte. */
    public static function validEan13(string $code): bool
    {
        if (!preg_match('/^\d{13}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $code[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $code[12];
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
        $today = $this->today();

        return match (true) {
            (bool) preg_match('/\bhier\b/', $n)                 => [$today->copy()->subDay(), $today->copy()->subDay(), "d'hier"],
            (bool) preg_match('/semaine/', $n)                  => [$today->copy()->startOfWeek(), $today, 'de la semaine (depuis lundi)'],
            (bool) preg_match('/trimestre/', $n)                => [$today->copy()->startOfQuarter(), $today, 'du trimestre (depuis le ' . $today->copy()->startOfQuarter()->format('d/m') . ')'],
            (bool) preg_match('/\bannee\b|\ban\b/', $n)         => [$today->copy()->startOfYear(), $today, "de l'année"],
            (bool) preg_match('/\bmois\b/', $n)                 => [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'],
            (bool) preg_match('/aujourd|du jour|journee/', $n)  => [$today, $today, "d'aujourd'hui"],
            $default === 'quarter'                              => [$today->copy()->startOfQuarter(), $today, 'du trimestre (depuis le ' . $today->copy()->startOfQuarter()->format('d/m') . ')'],
            $default === 'day'                                  => [$today, $today, "d'aujourd'hui"],
            default                                             => [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'],
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'analysis', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
