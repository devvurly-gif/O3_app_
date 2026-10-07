<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * « Rapproche un virement de 4 500 dirhams de Atlas » : quatrième commande de PROPOSITION de l'orchestrateur.
 *
 * L'administrateur annonce un paiement reçu (montant, mode, éventuellement le client et une référence) ; l'orchestrateur
 * cherche à quelle(s) facture(s) de vente il correspond et PROPOSE l'affectation, sans rien enregistrer :
 *   - un client nommé : une facture dont le reste à payer égale exactement le montant, sinon les factures impayées du
 *     client, de la plus ancienne à la plus récente (comme le règlement groupé de la fiche tiers) ; le trop-perçu éventuel
 *     n'est PAS affecté, il est signalé ;
 *   - aucun client : seulement les factures dont le reste à payer égale exactement le montant ; une seule → proposée,
 *     plusieurs → je demande le client, aucune → rien.
 *
 * Le clic « Enregistrer le paiement » crée les règlements au nom de l'administrateur, avec la date du jour. C'est la seule
 * commande de proposition qui écrit un enregistrement financier : il est annulable depuis la facture (suppression du
 * règlement), et le client n'est PAS notifié par e-mail ni WhatsApp (aucun message ne part). Un document déjà porté par une
 * écriture de trésorerie manuelle est écarté, comme dans le règlement groupé.
 */
class PaymentMatchAssistant
{
    private const METHODS = ['bank_transfer' => 'virement', 'cheque' => 'chèque', 'cash' => 'espèces', 'effet' => 'effet'];
    private const MAX_DOCS = 15;

    public function __construct(private MentionResolver $mentions)
    {
    }

    /**
     * @param string $n phrase normalisée
     * @param bool $outgoing vrai pour un paiement fait à un fournisseur (factures d'achat) ; faux pour un encaissement (factures de vente)
     */
    public function propose(User $admin, string $n, bool $outgoing = false): array
    {
        $who = $outgoing ? 'fournisseur' : 'client';
        $docs = $outgoing ? "facture d'achat" : 'facture de vente';
        $amount = $this->amount($n);
        if ($amount === null || $amount <= 0) {
            return $this->reply("Je n'ai pas trouvé le montant. Exemple : " . ($outgoing ? '« j\'ai payé un virement de 4 500 dirhams à Leader Star »' : '« rapproche un virement de 4 500 dirhams de Atlas »') . '.');
        }
        $method = $this->method($n);
        if ($method === null) {
            return $this->reply("Quel mode de paiement ? Dites par exemple « rapproche un virement de {$this->num($amount)} dirhams », « un chèque », « des espèces » ou « un effet ».");
        }
        $reference = preg_match('/\bref(?:erence)?\.?\s*:?\s*([a-z0-9][a-z0-9\-\/]{2,40})/', $n, $m) ? strtoupper($m[1]) : null;
        $client = $this->mentions->thirdParty($n) ?? $this->clientByWord($n);
        if ($outgoing && $client !== null && !in_array($client->role ?? '', ['supplier', 'both'], true)) {
            $client = null;                                                                    // « Atlas » est un client : pas un fournisseur à payer
        }

        $types = $outgoing ? ['InvoicePurchase'] : ['InvoiceSale'];
        !$outgoing && Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true' && $types[] = 'DeliveryNote';
        $open = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->whereIn('d.document_type', $types)->whereNotIn('d.status', ['draft', 'cancelled'])->where('f.amount_due', '>', 0)
            ->when($client !== null, fn ($q) => $q->where('d.thirdPartner_id', $client->id))
            ->orderBy('d.issued_at')->orderBy('d.id')
            ->get(['d.id', 'd.reference', 'd.issued_at', 'd.thirdPartner_id', 't.tp_title', 'f.amount_due']);
        $blocked = $open->filter(fn ($r) => DocumentHeader::find($r->id)?->hasManualTreasuryEntry())->pluck('id')->all();
        $open = $open->reject(fn ($r) => in_array($r->id, $blocked, true))->values();

        $exact = $open->filter(fn ($r) => abs((float) $r->amount_due - $amount) < 0.01)->values();
        if ($client === null) {
            if ($exact->isEmpty()) {
                return $this->reply("Aucune {$docs} n'a un reste à payer de {$this->money($amount)}. Précisez le {$who} : " . $this->example($outgoing, $method, $amount) . '.');
            }
            if ($exact->count() > 1) {
                return $this->reply("{$exact->count()} factures ont un reste à payer de {$this->money($amount)} : " . $exact->take(6)->map(fn ($r) => "{$r->reference} ({$r->tp_title})")->implode(', ')
                    . ". Précisez le {$who} : " . $this->example($outgoing, $method, $amount) . '.');
            }
            $plan = [['doc' => $exact->first(), 'applied' => $amount]];
            $surplus = 0.0;
        } else {
            if ($open->isEmpty()) {
                return $this->reply("{$client->title} n'a aucune {$docs} impayée" . ($blocked !== [] ? ' (hors celles déjà portées par une écriture de trésorerie manuelle)' : '') . '.');
            }
            $docs = $exact->isNotEmpty() ? $exact->take(1) : $open;
            $remaining = $amount;
            $plan = [];
            foreach ($docs as $r) {
                if ($remaining <= 0.004 || count($plan) >= self::MAX_DOCS) {
                    break;
                }
                $applied = round(min($remaining, (float) $r->amount_due), 2);
                $plan[] = ['doc' => $r, 'applied' => $applied];
                $remaining = round($remaining - $applied, 2);
            }
            $surplus = max(0.0, $remaining);
        }

        $items = array_map(fn ($p) => [
            'document_id' => $p['doc']->id, 'reference' => $p['doc']->reference, 'client' => $p['doc']->tp_title, 'due' => round((float) $p['doc']->amount_due, 2), 'applied' => $p['applied'],
        ], $plan);
        $label = $client?->title ?? $items[0]['client'];
        $event = AgentEvent::create([
            'type' => 'rapprochement_paiement', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'recouvrement')->value('id'),
            'payload' => ['text' => ($outgoing ? 'Paiement fournisseur' : 'Rapprochement') . " : {$this->methodLabel($method)} de {$this->money($amount)} ({$label})", 'outgoing' => $outgoing, 'items' => $items, 'amount' => $amount, 'method' => $method, 'reference' => $reference, 'surplus' => $surplus, 'requested_by' => $admin->name],
        ]);

        $lines = array_map(fn ($i) => "• {$i['reference']} — {$i['client']} — reste " . $this->money($i['due']) . ' → affecté ' . $this->money($i['applied']) . ($i['applied'] >= $i['due'] - 0.004 ? ' (soldée)' : ' (partiel)'), $items);

        return $this->reply(
            ucfirst($this->methodLabel($method)) . ($outgoing ? ' payé' : '') . " de {$this->money($amount)}" . ($reference ? " (réf. {$reference})" : '') . " — affectation proposée (lot #{$event->id}) :\n\n" . implode("\n", $lines)
            . ($surplus > 0 ? "\n\n" . ($outgoing ? 'Montant payé en trop' : 'Trop-perçu') . " non affecté : {$this->money($surplus)} (je ne l'enregistre pas ; à traiter à part, par exemple en avoir ou avance)." : '')
            . ($blocked !== [] ? "\n\n" . count($blocked) . ' facture(s) écartée(s) : déjà portée(s) par une écriture de trésorerie saisie à la main (le règlement compterait la somme deux fois).' : '')
            . "\n\nLe bouton enregistre ce(s) règlement(s) à votre nom, daté d'aujourd'hui, et met à jour le reste à payer. Le {$who} ne reçoit aucun message. Vous pouvez l'annuler depuis la facture (supprimer le règlement).",
            [['label' => $outgoing ? 'Enregistrer le paiement fournisseur' : 'Enregistrer le paiement', 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            $event->id,
        );
    }

    /** Enregistre les règlements proposés, si les factures n'ont pas changé depuis la proposition. */
    public function apply(User $admin, AgentEvent $event): array
    {
        $p = $event->payload ?? [];
        $items = $p['items'] ?? [];

        foreach ($items as $i) {
            $footer = DB::table('document_footers')->where('document_header_id', $i['document_id'])->first();
            $doc = DocumentHeader::find($i['document_id']);
            if (!$doc || !$footer || (float) $footer->amount_due + 0.004 < (float) $i['applied'] || $doc->hasManualTreasuryEntry()) {
                return $this->reply("La facture {$i['reference']} a changé depuis la proposition (déjà réglée, supprimée ou portée par une écriture de trésorerie) : rien n'a été enregistré. Redemandez le rapprochement.", eventId: $event->id);
            }
        }

        $created = [];
        $previous = Payment::$skipNotification;
        Payment::$skipNotification = true;                                                     // aucun e-mail ni WhatsApp au client ni au fournisseur
        try {
            DB::transaction(function () use ($items, $p, $admin, &$created) {
                foreach ($items as $i) {
                    $payment = Payment::create([
                        'document_header_id' => $i['document_id'], 'amount' => $i['applied'], 'method' => $p['method'], 'paid_at' => now(),
                        'reference' => $p['reference'] ?? null, 'user_id' => $admin->id,
                        'notes' => "Rapprochement validé par {$admin->name} depuis l'orchestrateur.",
                    ]);
                    $created[] = ['payment_id' => $payment->id, 'reference' => $i['reference'], 'amount' => $i['applied']];
                }
            });
        } finally {
            Payment::$skipNotification = $previous;
        }

        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($p, ['created' => $created, 'applied_by' => $admin->name])]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'payment_matched', 'level' => 'approval', 'input' => ['requested_by' => $p['requested_by'] ?? null, 'method' => $p['method'] ?? null], 'result' => ['payments' => array_column($created, 'payment_id')]]);

        return $this->reply('Paiement enregistré : ' . implode(', ', array_map(fn ($c) => "{$c['reference']} ({$this->money((float) $c['amount'])})", $created)) . ". Le reste à payer des factures est à jour ; aucun message n'a été envoyé au " . (!empty($p['outgoing']) ? 'fournisseur' : 'client') . '.', eventId: $event->id);
    }

    /** Le premier montant de la phrase : « 4 500 », « 4500,50 », « 1.200,5 », suivi ou non de dh / mad / dirhams. */
    private function amount(string $n): ?float
    {
        if (!preg_match('/(\d{1,3}(?:[ .\x{a0}\x{202f}]\d{3})+|\d+)(?:,(\d{1,2})|\.(\d{1,2})(?!\d))?/u', $n, $m)) {
            return null;
        }
        $whole = (string) preg_replace('/\D/', '', $m[1]);
        $decimals = ($m[2] ?? '') . ($m[3] ?? '');

        return (float) ($decimals === '' ? $whole : "{$whole}.{$decimals}");
    }

    /** « … de Atlas » : le seul client dont le nom contient le mot qui suit « de », « du » ou « chez » après le montant. */
    private function clientByWord(string $n): ?object
    {
        if (!preg_match('/\d\s*(?:dh|dhs|mad|dirhams?)?\s*(?:de|du|d|a|au|chez|par|pour)\s+([a-z0-9][a-z0-9\- ]{2,40}?)(?:\s+ref\b.*)?$/', $n, $m)) {
            return null;
        }

        return $this->mentions->thirdPartyLike(trim($m[1]));
    }

    private function example(bool $outgoing, string $method, float $amount): string
    {
        return $outgoing
            ? "« j'ai payé un {$this->methodLabel($method)} de {$this->num($amount)} dirhams à <fournisseur> »"
            : "« rapproche un {$this->methodLabel($method)} de {$this->num($amount)} dirhams de <client> »";
    }

    private function method(string $n): ?string
    {
        return match (true) {
            (bool) preg_match('/virement|\bvir\b|transfert bancaire/', $n) => 'bank_transfer',
            (bool) preg_match('/cheque|\bchq\b/', $n)                      => 'cheque',
            (bool) preg_match('/especes?|\bcash\b|liquide/', $n)           => 'cash',
            (bool) preg_match('/\beffets?\b|traite/', $n)                  => 'effet',
            default                                                        => null,
        };
    }

    private function methodLabel(string $method): string
    {
        return self::METHODS[$method] ?? $method;
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
