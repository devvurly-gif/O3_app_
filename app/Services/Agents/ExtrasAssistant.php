<?php

namespace App\Services\Agents;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Septième lot de lectures de l'orchestrateur : les canaux et le suivi (commandes reçues par messagerie, importations
 * d'achat, relances de paiement, dossiers et validations des agents), les droits (rôles et permissions), les chèques
 * et effets reçus, les bannières du site, les terminaux de caisse, les variantes et les prix par liste.
 *
 * Mêmes garde-fous que les lots précédents : lecture seule, aucun modèle de langage (rien n'est envoyé à Anthropic),
 * listes de 10 lignes au plus, montants au centime. Les numéros de téléphone ne sont jamais affichés en entier, le
 * contenu des messages des clients n'est pas lu, les codes PIN, mots de passe et jetons ne sont jamais lus.
 */
class ExtrasAssistant
{

    // ── Canaux et suivi ──────────────────────────────────────────────

    /** « commandes WhatsApp du jour ». @param string $n phrase normalisée */
    public function messagingOrders(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'day');
        $rows = DB::table('order_messages as m')->leftJoin('third_partners as t', 't.id', '=', 'm.third_partner_id')->leftJoin('document_headers as d', 'd.id', '=', 'm.document_id')
            ->where('m.direction', 'in')->where('m.created_at', '>=', $from->toDateTimeString())->where('m.created_at', '<=', $to->copy()->endOfDay()->toDateTimeString())->orderByDesc('m.id')->get(['m.created_at', 'm.channel', 'm.status', 'm.phone', 't.tp_title', 'd.reference']);
        $imports = DB::table('whatsapp_order_imports')->where('created_at', '>=', $from->toDateTimeString())->groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status');
        if ($rows->isEmpty() && $imports->isEmpty()) {
            return $this->reply("Aucune commande reçue par messagerie {$label}.");
        }

        $byStatus = $rows->groupBy('status')->map->count();
        $lines = ["Commandes reçues par messagerie {$label} : {$rows->count()} message(s)" . ($byStatus->isNotEmpty() ? ' (' . $byStatus->map(fn ($c, $s) => "{$s} : {$c}")->implode(', ') . ')' : '') . '.'];
        if ($imports->isNotEmpty()) {
            $lines[] = 'Importations de commandes : ' . $imports->map(fn ($c, $s) => "{$s} : {$c}")->implode(', ') . '.';
        }
        if ($rows->isNotEmpty()) {
            $lines[] = "\nLes plus récents :";
            foreach ($rows->slice(ListLimit::offset())->take(ListLimit::get()) as $r) {
                $lines[] = '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . " — {$r->channel} — " . ($r->tp_title ?? 'client inconnu (' . $this->maskPhone((string) $r->phone) . ')') . " — {$r->status}" . ($r->reference ? " → {$r->reference}" : '');
            }
        }

        return $this->reply(implode("\n", $lines));
    }

    /** « importations d'achat récentes ». */
    public function purchaseImports(): array
    {
        $since = $this->today()->copy()->subDays(29)->toDateTimeString();
        $byStatus = DB::table('purchase_imports')->where('created_at', '>=', $since)->groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status');
        if ($byStatus->isEmpty()) {
            return $this->reply("Aucune importation de facture d'achat sur les 30 derniers jours.");
        }
        $rows = DB::table('purchase_imports')->where('created_at', '>=', $since)->orderByDesc('id')->offset(ListLimit::offset())->limit(ListLimit::get())->get(['created_at', 'status', 'document_reference']);

        return $this->reply("Importations de factures d'achat sur 30 jours : " . $byStatus->map(fn ($c, $s) => "{$s} : {$c}")->implode(', ') . ".\n\nLes plus récentes :\n"
            . $rows->map(fn ($r) => '• ' . Carbon::parse($r->created_at)->format('d/m H:i') . " — {$r->status}" . ($r->document_reference ? " → {$r->document_reference}" : ''))->implode("\n"));
    }

    /** « relances de paiement du mois », « relances en échec ». @param string $n phrase normalisée */
    public function reminders(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('payment_reminders')->where('created_at', '>=', $from->toDateTimeString())->where('created_at', '<=', $to->copy()->endOfDay()->toDateTimeString())->groupBy('status')->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(amount_due), 0) AS du')->get();
        if ($rows->isEmpty()) {
            return $this->reply("Aucune relance de paiement {$label}.");
        }
        $failed = DB::table('payment_reminders as r')->leftJoin('third_partners as t', 't.id', '=', 'r.third_partner_id')->where('r.created_at', '>=', $from->toDateTimeString())->where('r.status', 'failed')->orderByDesc('r.id')->limit(5)->get(['t.tp_title', 'r.error', 'r.channel']);

        return $this->reply("Relances de paiement {$label} :\n\n" . $rows->map(fn ($r) => "• {$r->status} — {$r->n} relance(s), " . $this->money((float) $r->du) . ' dus')->implode("\n")
            . ($failed->isNotEmpty() ? "\n\nEn échec :\n" . $failed->map(fn ($r) => '• ' . ($r->tp_title ?? '?') . " ({$r->channel}) — " . ($r->error ?: 'raison non précisée'))->implode("\n") : '')
            . "\n\nStatuts : draft = brouillon à valider, sent = envoyée, rejected = refusée, failed = échec d'envoi.");
    }

    public function agentCases(): array
    {
        $cases = DB::table('agent_cases as c')->leftJoin('third_partners as t', 't.id', '=', 'c.third_partner_id')->where('c.status', '!=', 'closed')->whereNull('c.closed_at')->orderBy('c.opened_at')->offset(ListLimit::offset())->limit(ListLimit::get())->get(['c.id', 'c.status', 'c.opened_at', 't.tp_title']);
        $pending = DB::table('agent_approvals')->whereNull('decision')->count();
        $routed = DB::table('agent_events')->where('status', 'routed')->count();

        return $this->reply('Suivi des agents : ' . $cases->count() . " dossier(s) ouvert(s), {$pending} approbation(s) en attente, {$routed} proposition(s) à valider."
            . ($cases->isNotEmpty() ? "\n\nDossiers ouverts, les plus anciens d'abord :\n" . $cases->map(fn ($c) => "• #{$c->id} — " . ($c->tp_title ?? 'sans client') . " — ouvert le " . Carbon::parse($c->opened_at)->format('d/m/Y') . " ({$c->status})")->implode("\n") : ''),
            [['label' => 'Que dois-je valider ?', 'text' => 'que dois-je valider ?']]);
    }

    // ── Droits ───────────────────────────────────────────────────────

    /** « permissions du rôle manager », « rôles et permissions ». @param string $n phrase normalisée */
    public function permissions(string $n): array
    {
        $roles = DB::table('roles')->orderBy('id')->get(['id', 'name', 'display_name']);
        if ($roles->isEmpty()) {
            return $this->reply('Aucun rôle défini.');
        }
        $role = $roles->first(fn ($r) => str_contains($n, mb_strtolower(Str::ascii((string) $r->name))) || ($r->display_name && str_contains($n, mb_strtolower(Str::ascii((string) $r->display_name)))));
        if ($role === null) {
            $counts = DB::table('role_permission')->groupBy('role_id')->selectRaw('role_id, COUNT(*) AS n')->pluck('n', 'role_id');
            $users = DB::table('users')->whereNull('deleted_at')->groupBy('role_id')->selectRaw('role_id, COUNT(*) AS n')->pluck('n', 'role_id');

            return $this->reply("Rôles :\n\n" . $roles->map(fn ($r) => '• ' . ($r->display_name ?: $r->name) . " — " . (int) ($counts[$r->id] ?? 0) . ' permission(s), ' . (int) ($users[$r->id] ?? 0) . ' utilisateur(s)')->implode("\n") . "\n\nPour le détail : « permissions du rôle manager ».");
        }

        $perms = DB::table('role_permission as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('rp.role_id', $role->id)->orderBy('p.module')->orderBy('p.action')->get(['p.module', 'p.action']);
        if ($perms->isEmpty()) {
            return $this->reply('Le rôle « ' . ($role->display_name ?: $role->name) . ' » n\'a aucune permission enregistrée' . ($role->name === 'admin' ? ' (un administrateur a tous les droits).' : '.'));
        }

        return $this->reply('Le rôle « ' . ($role->display_name ?: $role->name) . " » a {$perms->count()} permission(s) :\n\n"
            . $perms->groupBy('module')->map(fn ($g, $m) => "• {$m} : " . $g->pluck('action')->implode(', '))->take(25)->implode("\n"));
    }

    // ── Paiements, site, caisse, catalogue ───────────────────────────

    /** « chèques et effets reçus ce mois ». @param string $n phrase normalisée */
    public function cheques(string $n): array
    {
        [$from, $to, $label] = $this->period($n, 'month');
        $rows = DB::table('payments as p')->join('document_headers as d', 'd.id', '=', 'p.document_header_id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')->whereNull('d.deleted_at')
            ->whereIn('p.method', ['cheque', 'effet'])->whereBetween('p.paid_at', [$from->toDateString(), $to->toDateString()])->orderByDesc('p.paid_at')->orderByDesc('p.id')->get(['p.paid_at', 'p.method', 'p.amount', 'p.reference', 'd.reference as facture', 't.tp_title', 'd.document_type']);
        if ($rows->isEmpty()) {
            return $this->reply("Aucun chèque ni effet enregistré {$label}.");
        }
        $sales = $rows->filter(fn ($r) => in_array($r->document_type, ['InvoiceSale', 'TicketSale', 'DeliveryNote', 'CustomerOrder'], true));

        return $this->reply("Chèques et effets {$label} : {$rows->count()} paiement(s), " . $this->money((float) $rows->sum('amount')) . ' (dont ' . $this->money((float) $sales->sum('amount')) . " reçus de clients).\n\n"
            . $rows->slice(ListLimit::offset())->take(ListLimit::get())->map(fn ($r) => '• ' . Carbon::parse($r->paid_at)->format('d/m/Y') . ' — ' . ($r->method === 'cheque' ? 'chèque' : 'effet') . ($r->reference ? " {$r->reference}" : '') . ' — ' . $this->money((float) $r->amount) . " — {$r->facture}" . ($r->tp_title ? " ({$r->tp_title})" : ''))->implode("\n")
            . "\n\nO3 n'enregistre pas la date d'échéance ni l'encaissement en banque d'un chèque ou d'un effet : ils ne peuvent pas être suivis ici.");
    }

    public function banners(): array
    {
        $now = $this->today();
        $rows = DB::table('slides')->where('is_active', true)->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now->copy()->endOfDay()))->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('position')->orderBy('sort_order')->get(['title', 'position', 'ends_at', 'link_type']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucune bannière active sur le site.');
        }

        return $this->reply("{$rows->count()} bannière(s) active(s) :\n\n" . $rows->slice(ListLimit::offset())->take(ListLimit::get())->map(fn ($r) => "• {$r->title} — {$r->position}" . ($r->link_type !== 'none' ? " — lien : {$r->link_type}" : '') . ($r->ends_at ? " — jusqu'au " . Carbon::parse($r->ends_at)->format('d/m/Y') : ''))->implode("\n"));
    }

    public function terminals(): array
    {
        $rows = DB::table('pos_terminals as t')->leftJoin('warehouses as w', 'w.id', '=', 't.warehouse_id')->orderBy('t.id')->get(['t.id', 't.name', 't.code', 't.is_active', 'w.wh_title']);
        if ($rows->isEmpty()) {
            return $this->reply('Aucun terminal de caisse défini.');
        }
        $open = DB::table('pos_sessions')->whereNull('closed_at')->groupBy('pos_terminal_id')->selectRaw('pos_terminal_id, COUNT(*) AS n')->pluck('n', 'pos_terminal_id');

        return $this->reply("{$rows->count()} terminal(aux) de caisse :\n\n" . $rows->map(fn ($r) => "• {$r->name} ({$r->code}) — " . ($r->is_active ? 'actif' : 'inactif') . ($r->wh_title ? " — entrepôt {$r->wh_title}" : '') . ((int) ($open[$r->id] ?? 0) > 0 ? ' — session ouverte' : ''))->implode("\n"));
    }

    public function variants(): array
    {
        $rows = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->whereNull('p.deleted_at')->groupBy('p.id', 'p.p_title', 'p.p_sku')
            ->selectRaw('p.p_title, p.p_sku, COUNT(*) AS n, SUM(v.is_active = 0) AS inactives, SUM(v.stock <= 0) AS sans_stock')->orderByDesc('n')->get();
        if ($rows->isEmpty()) {
            return $this->reply('Aucun produit n\'a de variantes.');
        }

        return $this->reply("{$rows->count()} produit(s) avec variantes ({$rows->sum('n')} variante(s)) :\n\n" . $rows->slice(ListLimit::offset())->take(ListLimit::get())->map(fn ($r) => "• {$r->p_title} ({$r->p_sku}) — {$r->n} variante(s), {$r->sans_stock} sans stock, {$r->inactives} inactive(s)")->implode("\n")
            . (ListLimit::more($rows->count())));
    }

    /** « prix du produit PRC1 par liste de prix ». @param string $n phrase normalisée */
    public function pricesByList(string $n): array
    {
        $term = preg_match('/prix\s+(?:du |de la |de l.|des |de |d.)?(?:produit |article )?(.{2,60}?)\s+(?:par liste|dans (?:les|chaque|toutes les) listes?|selon (?:les|la) listes?)/', $n, $m) ? trim($m[1]) : '';
        if ($term === '') {
            return $this->reply('Quel produit ? Par exemple : « prix du produit PRC1 par liste de prix ».', [], true);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $p = DB::table('products')->whereNull('deleted_at')->where(fn ($w) => $w->where('p_sku', $term)->orWhere('p_title', 'like', $like)->orWhere('p_sku', 'like', $like))->orderByRaw('p_sku = ? DESC', [$term])->orderBy('p_title')->first(['id', 'p_title', 'p_sku', 'p_salePrice']);
        if (!$p) {
            return $this->reply("Je ne trouve aucun produit correspondant à « {$term} ».", [], true);
        }
        $rows = DB::table('price_list_items as i')->join('price_lists as l', 'l.id', '=', 'i.price_list_id')->where('i.product_id', $p->id)->orderBy('l.priority')->orderBy('l.name')->get(['l.name', 'l.channel', 'l.is_active', 'i.price_ht', 'i.price_ttc', 'i.min_qty']);

        return $this->reply("Prix de {$p->p_title} ({$p->p_sku}) : prix de vente de la fiche " . $this->money((float) $p->p_salePrice) . ' HT.' . ($rows->isEmpty() ? "\n\nIl ne figure dans aucune liste de prix." : "\n\nListes de prix :\n"
            . $rows->map(fn ($r) => "• {$r->name} ({$r->channel})" . ($r->is_active ? '' : ' — inactive') . ' — ' . $this->money((float) $r->price_ht) . ' HT, ' . $this->money((float) $r->price_ttc) . ' TTC' . ((int) $r->min_qty > 1 ? ", dès {$r->min_qty} pièces" : ''))->implode("\n")));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** Un numéro masqué : seuls les quatre derniers chiffres restent visibles. */
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $digits === '' ? 'numéro inconnu' : '…' . substr($digits, -4);
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
        return ['body' => $body, 'meta' => array_filter(['intent' => 'extras', 'suggestions' => $suggestions ?: null, 'error' => $error ?: null], fn ($v) => $v !== null)];
    }
}
