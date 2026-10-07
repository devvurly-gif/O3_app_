<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * « Propose un échéancier pour Atlas en 3 mensualités » : cinquième commande de PROPOSITION de l'orchestrateur.
 *
 * Il répartit en N versements mensuels égaux (le dernier absorbe l'arrondi) le reste à payer de TOUTES les factures de vente
 * impayées d'un client, et prépare le message qui lui propose ce calendrier (lien WhatsApp ou e-mail déjà rempli : O3
 * n'envoie rien). Le clic « Enregistrer l'échéancier » ne fait que MÉMORISER l'accord (événement + journal) : aucune facture,
 * aucune date d'échéance et aucun règlement n'est modifié ; chaque versement reçu s'enregistre ensuite normalement
 * (« rapproche un virement… »). Un nouvel échéancier pour le même client remplace le précédent.
 */
class PaymentPlanAssistant
{
    private const TYPE = 'echeancier_paiement';
    private const WORDS = ['deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10, 'douze' => 12];

    public function __construct(private MentionResolver $mentions)
    {
    }

    /** @param string $n phrase normalisée */
    public function propose(User $admin, string $n): array
    {
        $count = $this->count($n);
        if ($count === null) {
            return $this->reply("En combien de versements mensuels ? Exemple : « propose un échéancier pour Atlas en 3 mensualités » (de 2 à 12).");
        }
        $client = $this->mentions->thirdParty($n) ?? $this->clientByWord($n);
        if ($client === null) {
            return $this->reply("Pour quel client ? Exemple : « propose un échéancier pour Atlas en {$count} mensualités ». Le nom doit être celui d'un client, ou un mot unique de son nom.");
        }
        $start = $this->start($n);
        if ($start === null) {
            return $this->reply('Je n\'ai pas compris la date « à partir du … ». Format : « à partir du 15/11 » ou « à partir du 15/11/2026 ».');
        }

        $types = ['InvoiceSale'];
        Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true' && $types[] = 'DeliveryNote';
        $docs = DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')
            ->whereNull('d.deleted_at')->where('d.thirdPartner_id', $client->id)->whereIn('d.document_type', $types)->whereNotIn('d.status', ['draft', 'cancelled'])->where('f.amount_due', '>', 0)
            ->orderBy('d.issued_at')->orderBy('d.id')->get(['d.reference', 'd.issued_at', 'f.amount_due']);
        if ($docs->isEmpty()) {
            return $this->reply("{$client->title} n'a aucune facture de vente impayée : pas d'échéancier à proposer.");
        }

        $total = round((float) $docs->sum('amount_due'), 2);
        $installments = $this->split($total, $count, $start);
        $previous = AgentEvent::where('type', self::TYPE)->where('status', AgentEvent::STATUS_DONE)->get()->first(fn (AgentEvent $e) => ($e->payload['client_id'] ?? null) === $client->id);

        $company = (string) (Setting::get('company', 'name') ?: Setting::get('general', 'company_name') ?: 'notre équipe');
        $message = $this->message($client->title, $total, $installments, $company);
        $contact = DB::table('third_partners')->where('id', $client->id)->first(['tp_phone', 'tp_email']);
        $wa = QuoteFollowUpAssistant::whatsapp((string) ($contact->tp_phone ?? ''), $message);
        $mail = filter_var($contact->tp_email ?? '', FILTER_VALIDATE_EMAIL) ? 'mailto:' . $contact->tp_email . '?subject=' . rawurlencode('Échéancier de paiement') . '&body=' . rawurlencode($message) : null;
        $links = array_values(array_filter([
            $wa ? ['label' => 'Envoyer le calendrier (WhatsApp)', 'to' => $wa] : null,
            $mail ? ['label' => 'Envoyer le calendrier (e-mail)', 'to' => $mail] : null,
        ]));

        $event = AgentEvent::create([
            'type' => self::TYPE, 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'recouvrement')->value('id'),
            'payload' => ['text' => "Échéancier {$client->title} : {$count} versements, {$this->money($total)}", 'client_id' => $client->id, 'client' => $client->title, 'total' => $total, 'invoices' => $docs->pluck('reference')->all(), 'installments' => $installments, 'message' => $message, 'requested_by' => $admin->name],
        ]);

        return $this->reply(
            "Échéancier proposé pour {$client->title} (lot #{$event->id}) : {$docs->count()} facture(s) impayée(s), {$this->money($total)} au total, en {$count} versements :\n\n"
            . implode("\n", array_map(fn ($i) => "• {$i['n']}/{$count} — " . Carbon::parse($i['date'])->format('d/m/Y') . ' — ' . $this->money($i['amount']), $installments))
            . ($previous ? "\n\nUn échéancier est déjà enregistré pour ce client : s'il est enregistré, celui-ci le remplace." : '')
            . ($links === [] ? "\n\nCe client n'a ni téléphone ni e-mail valide : le calendrier est à lui communiquer autrement." : "\n\nMessage prêt pour le client : les boutons ouvrent WhatsApp ou votre messagerie, vous envoyez vous-même.")
            . "\n\n« Enregistrer l'échéancier » mémorise seulement cet accord : aucune facture, date d'échéance ni règlement n'est modifié. Chaque versement reçu s'enregistre ensuite avec « rapproche un virement de … ».",
            [['label' => "Enregistrer l'échéancier", 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            $links,
            $event->id,
        );
    }

    /** Mémorise l'accord ; le précédent échéancier du même client est remplacé. */
    public function apply(User $admin, AgentEvent $event): array
    {
        $p = $event->payload ?? [];
        foreach (AgentEvent::where('type', self::TYPE)->where('status', AgentEvent::STATUS_DONE)->where('id', '!=', $event->id)->get() as $old) {
            if (($old->payload['client_id'] ?? null) === ($p['client_id'] ?? 0)) {
                $old->update(['status' => AgentEvent::STATUS_REJECTED, 'payload' => array_merge($old->payload ?? [], ['superseded_by' => $event->id])]);
            }
        }
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($p, ['recorded_by' => $admin->name, 'recorded_at' => now()->toDateTimeString()])]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'payment_plan_recorded', 'level' => 'approval', 'input' => ['requested_by' => $p['requested_by'] ?? null], 'result' => ['client' => $p['client'] ?? null, 'installments' => count($p['installments'] ?? []), 'total' => $p['total'] ?? null]]);

        return $this->reply("Échéancier enregistré pour " . ($p['client'] ?? '?') . ' : ' . count($p['installments'] ?? []) . ' versements, ' . $this->money((float) ($p['total'] ?? 0)) . ". Aucune facture n'a été modifiée. Dites « échéanciers en cours » pour le suivre.", eventId: $event->id);
    }

    /** « échéanciers en cours » : les échéanciers enregistrés, le prochain versement et ce que le client doit encore. */
    public function list(): array
    {
        $plans = AgentEvent::where('type', self::TYPE)->where('status', AgentEvent::STATUS_DONE)->latest('id')->offset(ListLimit::offset())->limit(ListLimit::get())->get();
        if ($plans->isEmpty()) {
            return $this->reply("Aucun échéancier enregistré. Pour en proposer un : « propose un échéancier pour Atlas en 3 mensualités ».");
        }
        $today = Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->toDateString();
        $lines = $plans->map(function (AgentEvent $e) use ($today) {
            $p = $e->payload;
            $upcoming = collect($p['installments'] ?? [])->first(fn ($i) => $i['date'] >= $today);
            $due = (float) DB::table('document_headers as d')->join('document_footers as f', 'f.document_header_id', '=', 'd.id')->whereNull('d.deleted_at')->where('d.thirdPartner_id', $p['client_id'] ?? 0)
                ->whereIn('d.document_type', ['InvoiceSale', 'DeliveryNote'])->whereNotIn('d.status', ['draft', 'cancelled'])->sum('f.amount_due');

            return "• {$p['client']} — " . count($p['installments'] ?? []) . ' versements, ' . $this->money((float) $p['total']) . ' à l\'origine, reste dû aujourd\'hui ' . $this->money($due)
                . ($upcoming ? ' — prochain : ' . Carbon::parse($upcoming['date'])->format('d/m/Y') . ' (' . $this->money((float) $upcoming['amount']) . ')' : ' — tous les versements sont échus');
        });

        return $this->reply("Échéanciers enregistrés :\n\n" . $lines->implode("\n") . "\n\nLe « reste dû » est lu sur les factures du client (tous documents confondus) : il baisse quand vous enregistrez les versements reçus.");
    }

    /** @return array<int, array{n: int, date: string, amount: float}> */
    private function split(float $total, int $count, Carbon $start): array
    {
        $each = floor($total / $count * 100) / 100;
        $out = [];
        $paid = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $amount = $i === $count - 1 ? round($total - $paid, 2) : round($each, 2);
            $paid = round($paid + $amount, 2);
            $out[] = ['n' => $i + 1, 'date' => $start->copy()->addMonthsNoOverflow($i)->toDateString(), 'amount' => $amount];
        }

        return $out;
    }

    private function count(string $n): ?int
    {
        if (preg_match('/\b(\d{1,2})\s*(?:mensualites?|mois|fois|echeances?|versements?|paiements?|tranches?)\b/', $n, $m)) {
            $c = (int) $m[1];
        } elseif (preg_match('/\b(' . implode('|', array_keys(self::WORDS)) . ')\s*(?:mensualites?|mois|fois|echeances?|versements?|paiements?|tranches?)\b/', $n, $m)) {
            $c = self::WORDS[$m[1]];
        } else {
            return null;
        }

        return $c >= 2 && $c <= 12 ? $c : null;
    }

    /** « à partir du 15/11[/2026] » ; sinon le même jour le mois prochain. Null si la date est illisible. */
    private function start(string $n): ?Carbon
    {
        $today = Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
        if (!preg_match('/a partir du\s+(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.](\d{2,4}))?/', $n, $m)) {
            return $today->copy()->addMonthNoOverflow();
        }
        $year = isset($m[3]) ? (int) $m[3] + (strlen($m[3]) === 2 ? 2000 : 0) : $today->year;
        if (!checkdate((int) $m[2], (int) $m[1], $year)) {
            return null;
        }
        $date = Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();

        return !isset($m[3]) && $date->lt($today) ? $date->addYear() : $date;
    }

    /** « … pour Atlas », « … avec Atlas », « … de Atlas » : le seul client dont le nom contient ce mot. */
    private function clientByWord(string $n): ?object
    {
        if (!preg_match('/\b(?:pour|avec|de|du|chez)\s+(?:le client\s+)?([a-z0-9][a-z0-9\-]{2,30})\b/', (string) preg_replace('/\b(un|une|le|la|les)\s+echeancier\w*/', '', $n), $m)) {
            return null;
        }

        return $this->mentions->thirdPartyLike($m[1]);
    }

    /** @param array<int, array{n: int, date: string, amount: float}> $installments */
    private function message(string $client, float $total, array $installments, string $company): string
    {
        $lines = implode("\n", array_map(fn ($i) => "- {$i['n']} : " . Carbon::parse($i['date'])->format('d/m/Y') . ' — ' . number_format($i['amount'], 2, ',', ' ') . ' MAD', $installments));

        return "Bonjour {$client},\n\nPour régler le solde de " . number_format($total, 2, ',', ' ') . " MAD TTC, nous vous proposons le calendrier suivant :\n{$lines}\n\nPouvez-vous nous confirmer votre accord ?\n\nCordialement,\n{$company}";
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @param array<int, array{label: string, to: string}> $links
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $suggestions = [], array $links = [], ?int $eventId = null): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'catalog', 'suggestions' => $suggestions ?: null, 'links' => $links ?: null, 'event_id' => $eventId], fn ($v) => $v !== null)];
    }
}
