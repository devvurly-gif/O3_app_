<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentIncrementor;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentHeaderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * « Réapprovisionne le stock faible » : deuxième commande de PROPOSITION de l'orchestrateur.
 *
 * Il regroupe par fournisseur les produits au seuil d'alerte (ou en dessous) et propose la quantité qui ramène le stock à
 * DEUX FOIS le seuil. Le clic « Créer les brouillons » crée un bon de commande fournisseur BROUILLON par fournisseur, au nom
 * de l'administrateur qui clique : rien n'est envoyé au fournisseur, aucun stock ne bouge (un bon de commande n'a aucun
 * effet sur le stock), et le brouillon se relit et se corrige dans O3 avant toute confirmation.
 *
 * Fournisseur : le lien explicite du produit (priorité la plus basse), sinon le fournisseur le plus fréquent de l'historique
 * d'achat. Un produit sans fournisseur connu n'est jamais deviné : il est listé à part. Un produit déjà présent sur un bon
 * de commande ouvert n'est pas reproposé. Prix : le prix d'achat O3 est TTC (règle du 2026-10-02), converti en HT avec la
 * TVA du produit.
 */
class ReorderAssistant
{
    private const TYPE = 'PurchaseOrder';
    private const CLOSED = ['cancelled', 'converted'];
    private const HISTORY = ['ReceiptNotePurchase', 'InvoicePurchase'];
    private const MAX_PRODUCTS = 60;

    public function __construct(private DocumentHeaderService $documents)
    {
    }

    public function propose(User $admin): array
    {
        $threshold = max(0, min(100, (int) Setting::get('stock', 'seuil_alerte_stock', '5')));
        $target = max(1, $threshold * 2);

        $qty = DB::table('warehouse_has_stock')->selectRaw('product_id, COALESCE(SUM(stockLevel), 0) AS qty')->groupBy('product_id');
        $low = DB::table('products as p')->leftJoinSub($qty, 's', 's.product_id', '=', 'p.id')->whereNull('p.deleted_at')->where('p.p_status', true)
            ->whereRaw('COALESCE(s.qty, 0) <= ?', [$threshold])
            ->orderByRaw('COALESCE(s.qty, 0)')->orderBy('p.id')
            ->get(['p.id', 'p.p_sku', 'p.p_title', 'p.p_unit', 'p.p_taxRate', 'p.p_purchasePrice', DB::raw('COALESCE(s.qty, 0) AS stock')]);
        if ($low->isEmpty()) {
            return $this->reply("Rien à réapprovisionner : aucun produit actif n'est au seuil d'alerte ({$threshold} pièce(s)) ou en dessous.");
        }

        $ordered = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')
            ->whereNull('d.deleted_at')->where('d.document_type', self::TYPE)->whereNotIn('d.status', self::CLOSED)->whereIn('l.product_id', $low->pluck('id'))->pluck('l.product_id')->all();
        $todo = $low->reject(fn ($p) => in_array($p->id, $ordered, true))->values();
        if ($todo->isEmpty()) {
            return $this->reply("Les {$low->count()} produit(s) au seuil d'alerte figurent déjà sur un bon de commande fournisseur ouvert : rien de plus à commander.");
        }
        $todo = $todo->take(self::MAX_PRODUCTS);

        $ids = $todo->pluck('id')->all();
        $links = DB::table('product_suppliers')->whereIn('product_id', $ids)->orderBy('product_id')->orderBy('priority')->orderBy('id')->get()->groupBy('product_id')->map(fn ($r) => $r->first());
        $history = DB::table('document_lignes as l')->join('document_headers as d', 'd.id', '=', 'l.document_header_id')
            ->whereIn('d.document_type', self::HISTORY)->whereNotIn('d.status', ['cancelled', 'draft', 'converted'])->whereNull('d.deleted_at')->whereIn('l.product_id', $ids)
            ->groupBy('l.product_id', 'd.thirdPartner_id')->selectRaw('l.product_id, d.thirdPartner_id AS supplier, COUNT(*) AS freq, MAX(d.issued_at) AS last')
            ->orderByDesc('freq')->orderByDesc('last')->get()->groupBy('product_id')->map(fn ($r) => $r->first()->supplier);

        $groups = [];
        $unassigned = [];
        foreach ($todo as $p) {
            $link = $links->get($p->id);
            $supplier = $link->third_partner_id ?? $history->get($p->id);
            if (!$supplier) {
                $unassigned[] = "{$p->p_title} ({$p->p_sku})";
                continue;
            }
            $quantity = max(1, (int) ceil($target - (float) $p->stock));
            $ttc = (float) ($link->purchase_price ?? 0) > 0 ? (float) $link->purchase_price : (float) $p->p_purchasePrice;
            $rate = (float) $p->p_taxRate;
            $groups[$supplier][] = [
                'product_id' => $p->id, 'reference' => $link->supplier_sku ?? $p->p_sku, 'designation' => $p->p_title, 'unit' => $p->p_unit ?: 'pièce',
                'stock' => (float) $p->stock, 'quantity' => $quantity, 'tax_percent' => $rate, 'unit_price' => round($ttc / (1 + $rate / 100), 2), 'price_missing' => $ttc <= 0,
            ];
        }
        if ($groups === []) {
            return $this->reply('Aucun produit au seuil d\'alerte n\'a de fournisseur connu (ni lien produit-fournisseur ni achat passé) : je ne devine jamais un fournisseur. Produits concernés : ' . implode(', ', array_slice($unassigned, 0, 10)) . '.');
        }

        $names = DB::table('third_partners')->whereIn('id', array_keys($groups))->pluck('tp_title', 'id');
        $items = [];
        $lines = [];
        foreach ($groups as $supplierId => $rows) {
            $name = (string) ($names[$supplierId] ?? "Fournisseur #{$supplierId}");
            $items[] = ['supplier_id' => $supplierId, 'supplier' => $name, 'lines' => $rows];
            $noPrice = count(array_filter($rows, fn ($r) => $r['price_missing']));
            $lines[] = "• {$name} — " . count($rows) . ' produit(s), ' . $this->money(array_sum(array_map(fn ($r) => $r['quantity'] * $r['unit_price'] * (1 + $r['tax_percent'] / 100), $rows))) . ' TTC'
                . ($noPrice > 0 ? " ({$noPrice} sans prix d'achat, à compléter)" : '');
            foreach (array_slice($rows, 0, 3) as $r) {
                $lines[] = "    – {$r['designation']} : stock " . $this->num($r['stock']) . ", à commander {$r['quantity']}";
            }
            count($rows) > 3 && $lines[] = '    – … et ' . (count($rows) - 3) . ' autre(s)';
        }

        $event = AgentEvent::create([
            'type' => 'reappro_commande', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'achats')->value('id'),
            'payload' => ['text' => 'Réapprovisionnement : ' . count($items) . ' bon(s) de commande fournisseur à préparer', 'items' => $items, 'threshold' => $threshold, 'target' => $target, 'requested_by' => $admin->name],
        ]);

        return $this->reply(
            count($items) . " bon(s) de commande à préparer (lot #{$event->id}) pour les produits à {$threshold} pièce(s) ou moins ; quantité = ce qu'il faut pour revenir à {$target} pièce(s) :\n\n" . implode("\n", $lines)
            . ($unassigned !== [] ? "\n\nSans fournisseur connu (à lier dans la fiche produit, je ne devine pas) : " . implode(', ', array_slice($unassigned, 0, 8)) . (count($unassigned) > 8 ? '…' : '') . '.' : '')
            . "\n\nLe bouton crée des BROUILLONS de bons de commande à votre nom : rien n'est envoyé aux fournisseurs et le stock ne bouge pas. Vous relisez et corrigez chaque bon dans O3 avant de le confirmer. Prix : prix d'achat TTC de la fiche, converti en HT.",
            [['label' => 'Créer les brouillons de commande', 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            $event->id,
        );
    }

    /** Crée un bon de commande brouillon par fournisseur, au nom de l'administrateur. */
    public function apply(User $admin, AgentEvent $event): array
    {
        $incrementor = DocumentIncrementor::where('di_model', self::TYPE)->first();
        $warehouse = Warehouse::where('wh_status', true)->orderBy('id')->first();
        if (!$incrementor || !$warehouse) {
            return $this->reply("Impossible de créer les brouillons : " . (!$incrementor ? "l'incrémenteur des bons de commande fournisseur n'existe pas" : "aucun entrepôt actif n'est configuré") . '. Le lot reste en attente.', eventId: $event->id);
        }

        $created = [];
        foreach ($event->payload['items'] ?? [] as $item) {
            $lines = array_map(fn ($l) => [
                'product_id' => $l['product_id'], 'designation' => $l['designation'], 'reference' => $l['reference'], 'quantity' => $l['quantity'], 'unit' => $l['unit'],
                'unit_price' => $l['unit_price'], 'discount_percent' => 0, 'tax_percent' => $l['tax_percent'],
            ], $item['lines']);
            $ht = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price'], $lines));
            $tax = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price'] * $l['tax_percent'] / 100, $lines));

            $document = $this->documents->createWithLinesAndFooter([
                'document_incrementor_id' => $incrementor->id, 'document_type' => self::TYPE, 'document_title' => 'Bon de Commande (Fournisseur)',
                'thirdPartner_id' => $item['supplier_id'], 'company_role' => 'supplier', 'warehouse_id' => $warehouse->id, 'issued_at' => Carbon::today(), 'due_at' => null,
                'notes' => 'Brouillon préparé par l\'orchestrateur (réapprovisionnement du stock faible), validé par ' . $admin->name . '. À relire et confirmer avant tout envoi au fournisseur.',
                'user_id' => $admin->id,
            ], $lines, [
                'total_ht' => round($ht, 2), 'total_discount' => 0, 'total_tax' => round($tax, 2), 'total_ttc' => round($ht + $tax, 2), 'amount_paid' => 0, 'amount_due' => round($ht + $tax, 2),
            ]);
            $created[] = ['reference' => $document->reference, 'supplier' => $item['supplier'], 'document_id' => $document->id];
        }

        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['created' => $created, 'applied_by' => $admin->name])]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'reorder_drafts_created', 'level' => 'approval', 'input' => ['requested_by' => $event->payload['requested_by'] ?? null], 'result' => ['documents' => array_column($created, 'reference')]]);

        return $this->reply(count($created) . ' bon(s) de commande brouillon créé(s) : ' . implode(', ', array_map(fn ($c) => "{$c['reference']} ({$c['supplier']})", $created))
            . ". Rien n'a été envoyé ; relisez-les dans les achats avant de les confirmer.", [['label' => 'Voir les bons de commande', 'text' => 'bons de commande fournisseur']], $event->id);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', ' '), '0'), ',');
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = [], ?int $eventId = null): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'catalog', 'suggestions' => $suggestions ?: null, 'event_id' => $eventId], fn ($v) => $v !== null)];
    }
}
