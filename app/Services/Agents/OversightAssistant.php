<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Huitième lot de lectures de l'orchestrateur, choisi d'après ce que les lots précédents ne lisaient pas encore :
 * les promotions d'un produit (et inversement), les règles de routage et les seuils des agents, l'activité des agents,
 * les notifications, les factures non envoyées, la répartition par mode de paiement, les livraisons par ville, les
 * entrepôts, les catégories de trésorerie et les listes de prix.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. Les clés d'abonnement aux notifications ne sont jamais lues (on ne
 * compte que les appareils), ni les codes PIN, mots de passe et jetons.
 */
class OversightAssistant
{
    private const LIST = 10;
    private const SALES_TYPES = ['InvoiceSale', 'TicketSale'];

    // ── Promotions ───────────────────────────────────────────────────

    /** « produits de la promotion rentrée ». @param string $n phrase normalisée */
    public function promotionProducts(string $n): array
    {
        $term = $this->term($n, '/promotion\s+(?:du |de la |de l.|des |de |d.)?(?:nommee |intitulee )?(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quelle promotion ? Par exemple : « produits de la promotion rentrée ».', [], true);
        }
        $promo = DB::table('promotions')->where('name', 'like', $this->like($term))->orderByDesc('is_active')->orderByDesc('id')->first(['id', 'name', 'type', 'value', 'is_active', 'starts_at', 'ends_at']);
        if (!$promo) {
            return $this->reply("Je ne trouve aucune promotion correspondant à « {$term} ».", [], true);
        }
        $rows = DB::table('promotion_product as pp')->join('products as p', 'p.id', '=', 'pp.product_id')->whereNull('p.deleted_at')->where('pp.promotion_id', $promo->id)->orderBy('p.p_title')->get(['p.p_title', 'p.p_sku', 'p.p_salePrice', 'pp.promo_price']);

        return $this->reply("Promotion « {$promo->name} » — " . ($promo->type === 'percentage' ? round((float) $promo->value, 1) . ' %' : $this->money((float) $promo->value)) . ($promo->is_active ? '' : ' (inactive)') . ($promo->ends_at ? " jusqu'au " . Carbon::parse($promo->ends_at)->format('d/m/Y') : '') . ".\n\n"
            . ($rows->isEmpty() ? 'Aucun produit n\'y est rattaché.' : "{$rows->count()} produit(s) :\n" . $rows->take(self::LIST)->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — " . $this->money((float) $r->p_salePrice) . ($r->promo_price !== null ? ' → ' . $this->money((float) $r->promo_price) : ''))->implode("\n")
                . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : '')));
    }

    /** « promotions du produit PRC1 ». @param string $n phrase normalisée */
    public function productPromotions(string $n): array
    {
        $term = $this->term($n, '/promotions?\s+(?:du |de l.|d.)\s*(?:produit |article )?(.{2,60})$/');
        if ($term === null) {
            return $this->reply('Quel produit ? Par exemple : « promotions du produit PRC1 ».', [], true);
        }
        $p = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_sku', $term)->orWhere('p_title', 'like', $this->like($term)))->orderByRaw('p_sku = ? DESC', [$term])->orderBy('p_title')->first(['id', 'p_title', 'p_sku']);
        if (!$p) {
            return $this->reply("Je ne trouve aucun produit correspondant à « {$term} ».", [], true);
        }
        $rows = DB::table('promotion_product as pp')->join('promotions as m', 'm.id', '=', 'pp.promotion_id')->where('pp.product_id', $p->id)->orderByDesc('m.is_active')->orderByDesc('m.id')->get(['m.name', 'm.is_active', 'm.starts_at', 'm.ends_at', 'pp.promo_price']);

        return $this->reply("Promotions de {$p->p_title} ({$p->p_sku}) :\n\n" . ($rows->isEmpty() ? 'Aucune promotion ne le concerne.' : $rows->map(fn ($r) => "• {$r->name} — " . ($r->is_active ? 'active' : 'inactive') . ($r->promo_price !== null ? ', prix promo ' . $this->money((float) $r->promo_price) : '') . ($r->ends_at ? " jusqu'au " . Carbon::parse($r->ends_at)->format('d/m/Y') : ''))->implode("\n")));
    }

    // ── Agents ───────────────────────────────────────────────────────

    public function routingRules(): array
    {
        $rows = DB::table('agent_routing_rules')->orderBy('priority')->orderBy('id')->get(['event_type', 'conditions', 'agent_domain', 'priority', 'is_active', 'phase']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune règle de routage définie.');
        }

        return $this->reply("{$rows->count()} règle(s) de routage (par priorité) :\n\n" . $rows->take(15)->map(function ($r) {
            $c = json_decode((string) $r->conditions, true);

            return "• {$r->event_type}" . (is_array($c) && $c !== [] ? ' (' . collect($c)->map(fn ($v, $k) => $k . ' = ' . (is_scalar($v) ? $v : json_encode($v)))->implode(', ') . ')' : '') . " → agent {$r->agent_domain}" . ($r->is_active ? '' : ' — désactivée');
        })->implode("\n") . "\n\nLe routeur confie un événement à l'agent de la première règle qui correspond.");
    }

    public function thresholds(): array
    {
        $rows = DB::table('agent_thresholds')->orderBy('agent_domain')->orderBy('action_type')->orderBy('parameter')->get(['agent_domain', 'action_type', 'parameter', 'value', 'unit']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun seuil défini pour les agents : toute action passe par une validation.');
        }

        return $this->reply("{$rows->count()} seuil(s) des agents :\n\n" . $rows->take(20)->map(fn ($r) => "• {$r->agent_domain} / {$r->action_type} — {$r->parameter} : " . rtrim(rtrim(number_format((float) $r->value, 2, ',', ' '), '0'), ',') . ($r->unit ? " {$r->unit}" : ''))->implode("\n"));
    }

    /** « événements des agents du mois ». @param string $n phrase normalisée */
    public function agentEvents(string $n): array
    {
        [$from, , $label] = $this->period($n, 'month');
        $rows = DB::table('agent_events')->where('created_at', '>=', $from->toDateTimeString())->groupBy('type', 'status')->selectRaw('type, status, COUNT(*) AS n')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucun événement d'agent {$label}.");
        }
        $byStatus = $rows->groupBy('status')->map(fn ($g) => (int) $g->sum('n'));

        return $this->reply("Événements des agents {$label} : " . $rows->sum('n') . ' (' . $byStatus->map(fn ($c, $s) => "{$s} : {$c}")->implode(', ') . ").\n\nPar type :\n"
            . $rows->groupBy('type')->map(fn ($g, $t) => "• {$t} — " . (int) $g->sum('n') . ' (' . $g->map(fn ($r) => "{$r->status} {$r->n}")->implode(', ') . ')')->take(self::LIST)->implode("\n"));
    }

    // ── Notifications ────────────────────────────────────────────────

    /** « mes notifications non lues ». */
    public function notifications(int $userId): array
    {
        $base = fn () => DB::table('notifications')->where('notifiable_id', $userId)->where('notifiable_type', 'like', '%User');
        $unread = $base()->whereNull('read_at')->count();
        $rows = $base()->whereNull('read_at')->orderByDesc('created_at')->limit(self::LIST)->get(['created_at', 'data', 'type']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune notification non lue.');
        }

        return $this->reply("{$unread} notification(s) non lue(s), les plus récentes :\n\n" . $rows->map(function ($r) {
            $d = json_decode((string) $r->data, true);
            $text = is_array($d) ? (string) ($d['title'] ?? $d['message'] ?? $d['body'] ?? class_basename($r->type)) : class_basename($r->type);

            return '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . ' — ' . mb_strimwidth($text, 0, 120, '…');
        })->implode("\n"));
    }

    /** Appareils abonnés aux notifications : un décompte par utilisateur, jamais les clés d'abonnement. */
    public function pushDevices(): array
    {
        $rows = DB::table('push_subscriptions as s')->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 's.subscribable_id')->where('s.subscribable_type', 'like', '%User'))
            ->groupBy('u.id', 'u.name')->selectRaw('COALESCE(u.name, \'?\') AS nom, COUNT(*) AS n')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun appareil n\'est abonné aux notifications.');
        }

        return $this->reply("{$rows->sum('n')} appareil(s) abonné(s) aux notifications :\n\n" . $rows->take(self::LIST)->map(fn ($r) => "• {$r->nom} — {$r->n} appareil(s)")->implode("\n"));
    }

    // ── Documents et livraisons ──────────────────────────────────────

    /** « factures non envoyées au client ». */
    public function unsentInvoices(): array
    {
        $rows = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')->where('d.document_type', 'InvoiceSale')
            ->whereNotIn('d.status', ['draft', 'cancelled'])->where('f.is_sent', false)->where('d.issued_at', '>=', $this->today()->copy()->subDays(60)->toDateString())->orderBy('d.issued_at')->get(['d.reference', 'd.issued_at', 't.tp_title', 'f.total_ttc']);
        if ($rows->isEmpty()) {
            return $this->reply('Toutes les factures des 60 derniers jours ont été envoyées au client.');
        }

        return $this->reply("{$rows->count()} facture(s) des 60 derniers jours non envoyée(s) au client, pour " . $this->money((float) $rows->sum('total_ttc')) . " TTC. Les plus anciennes :\n\n"
            . $rows->take(self::LIST)->map(fn ($r) => "• {$r->reference} — " . ($r->tp_title ?? 'sans client') . ' — du ' . Carbon::parse($r->issued_at)->format('d/m/Y') . ' — ' . $this->money((float) $r->total_ttc))->implode("\n")
            . ($rows->count() > self::LIST ? "\n… et " . ($rows->count() - self::LIST) . ' autre(s).' : '') . "\n\n« Envoyée » est l'indicateur d'envoi enregistré sur la facture ; une facture remise autrement peut y figurer.");
    }

    /** « répartition des ventes par mode de paiement ». @param string $n phrase normalisée */
    public function paymentMix(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->whereIn('d.document_type', self::SALES_TYPES)->whereNotIn('d.status', ['draft', 'cancelled'])
            ->whereBetween('d.issued_at', [$from->toDateString(), $to->toDateString()])->groupBy('f.payment_method')->selectRaw('f.payment_method AS mode, COUNT(*) AS n, SUM(f.total_ttc) AS ttc')->orderByDesc('ttc')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune vente {$label}.");
        }
        $total = max((float) $rows->sum('ttc'), 0.01);
        $names = ['cash' => 'espèces', 'bank_transfer' => 'virement', 'cheque' => 'chèque', 'effet' => 'effet', 'credit' => 'crédit (à payer plus tard)'];

        return $this->reply("Ventes {$label} par mode de règlement prévu sur la facture (" . $this->money($total) . " TTC) :\n\n" . $rows->map(fn ($r) => '• ' . ($names[$r->mode] ?? ($r->mode ?: 'non renseigné')) . " — {$r->n} vente(s), " . $this->money((float) $r->ttc) . ' (' . round((float) $r->ttc / $total * 100) . ' %)')->implode("\n")
            . "\n\nC'est le mode inscrit sur le document ; les paiements réellement reçus se lisent avec « encaissements du mois ».");
    }

    public function deliveriesByCity(): array
    {
        $rows = DB::table('document_headers as d')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')->whereIn('d.document_type', ['DeliveryNote', 'CustomerOrder'])->whereIn('d.status', ['confirmed', 'sent', 'pending'])
            ->get(['d.reference', 'd.document_type', 'd.ship_name', 'd.ship_city', 't.tp_title', 't.tp_city']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune livraison ni commande en attente.');
        }
        $byCity = $rows->groupBy(fn ($r) => $r->ship_city ?: ($r->tp_city ?: 'Ville non renseignée'))->map->count()->sortDesc();

        return $this->reply("{$rows->count()} livraison(s) ou commande(s) en attente, par ville de livraison :\n\n" . $byCity->take(self::LIST)->map(fn ($c, $v) => "• {$v} — {$c}")->implode("\n")
            . "\n\nLa ville est celle de l'adresse de livraison du document, à défaut celle du client.");
    }

    // ── Référentiels ─────────────────────────────────────────────────

    public function warehouses(): array
    {
        $rows = DB::table('warehouses as w')->leftJoin('warehouse_has_stock as s', 's.warehouse_id', '=', 'w.id')->groupBy('w.id', 'w.wh_title', 'w.wh_code', 'w.wh_status')
            ->selectRaw('w.wh_title, w.wh_code, w.wh_status, COUNT(DISTINCT CASE WHEN s.stockLevel > 0 THEN s.product_id END) AS produits, COALESCE(SUM(CASE WHEN s.stockLevel > 0 THEN s.stockLevel END), 0) AS pieces, COALESCE(SUM(CASE WHEN s.stockLevel > 0 THEN s.stockLevel * s.wh_average END), 0) AS valeur')->orderBy('w.wh_title')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun entrepôt défini.');
        }

        return $this->reply("{$rows->count()} entrepôt(s) :\n\n" . $rows->map(fn ($r) => "• {$r->wh_title}" . ($r->wh_code ? " ({$r->wh_code})" : '') . ($r->wh_status ? '' : ' — inactif') . " — {$r->produits} produit(s) en stock, " . rtrim(rtrim(number_format((float) $r->pieces, 2, ',', ' '), '0'), ',') . ' pièce(s), ' . $this->money((float) $r->valeur))->implode("\n"));
    }

    public function cashCategories(): array
    {
        $since = $this->today()->copy()->startOfMonth()->toDateString();
        $rows = DB::table('cash_categories as c')->leftJoin('cash_transactions as x', fn ($j) => $j->on('x.cash_category_id', '=', 'c.id')->whereNull('x.deleted_at')->where('x.ct_status', 'active')->where('x.ct_date', '>=', $since))
            ->whereNull('c.deleted_at')->groupBy('c.id', 'c.cc_title', 'c.cc_direction', 'c.cc_status')->selectRaw('c.cc_title, c.cc_direction, c.cc_status, COUNT(x.id) AS n, COALESCE(SUM(x.ct_amount), 0) AS total')->orderBy('c.cc_title')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucune catégorie de trésorerie définie.');
        }
        $dir = ['in' => 'entrées', 'out' => 'sorties', 'both' => 'entrées et sorties'];

        return $this->reply("{$rows->count()} catégorie(s) de trésorerie (usage depuis le début du mois) :\n\n" . $rows->map(fn ($r) => "• {$r->cc_title} ({$dir[$r->cc_direction]})" . ($r->cc_status ? '' : ' — inactive') . " — {$r->n} opération(s), " . $this->money((float) $r->total))->implode("\n"));
    }

    public function priceLists(): array
    {
        $rows = DB::table('price_lists as l')->leftJoin('price_list_items as i', 'i.price_list_id', '=', 'l.id')->groupBy('l.id', 'l.name', 'l.channel', 'l.is_active', 'l.is_default', 'l.priority')
            ->selectRaw('l.id, l.name, l.channel, l.is_active, l.is_default, COUNT(i.id) AS produits')->orderBy('l.priority')->orderBy('l.name')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune liste de prix n'est définie : tous les clients paient le prix de la fiche.");
        }
        $clients = DB::table('third_partners')->whereNull('deleted_at')->whereNotNull('price_list_id')->groupBy('price_list_id')->selectRaw('price_list_id, COUNT(*) AS n')->pluck('n', 'price_list_id');

        return $this->reply("{$rows->count()} liste(s) de prix :\n\n" . $rows->map(fn ($r) => "• {$r->name} ({$r->channel})" . ($r->is_default ? ' — par défaut' : '') . ($r->is_active ? '' : ' — inactive') . " — {$r->produits} produit(s), " . (int) ($clients[$r->id] ?? 0) . ' client(s) rattaché(s)')->implode("\n"));
    }

    // ── Outils ───────────────────────────────────────────────────────

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

    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} début, fin (incluse), libellé */
    private function period(string $n, string $default): array
    {
        $today = $this->today();

        return match (true) {
            (bool) preg_match('/\bhier\b/', $n)  => [$today->copy()->subDay(), $today->copy()->subDay(), "d'hier"],
            (bool) preg_match('/semaine/', $n)   => [$today->copy()->startOfWeek(), $today, 'de la semaine (depuis lundi)'],
            (bool) preg_match('/\bannee\b/', $n) => [$today->copy()->startOfYear(), $today, "de l'année"],
            (bool) preg_match('/aujourd|du jour|journee/', $n) => [$today, $today, "d'aujourd'hui"],
            default                              => [$today->copy()->startOfMonth(), $today, 'du mois (depuis le ' . $today->copy()->startOfMonth()->format('d/m') . ')'],
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'oversight', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
