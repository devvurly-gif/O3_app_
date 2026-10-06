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
 * « Relance les devis sans réponse » : première commande de PROPOSITION de l'orchestrateur.
 *
 * Il prépare, pour chaque devis ouvert depuis trop longtemps, le message de relance et le lien qui l'ouvre dans
 * WhatsApp ou dans le client de messagerie, déjà rempli. RIEN n'est envoyé par O3 : l'administrateur lit, clique sur le
 * lien et envoie lui-même. Le clic « Marquer comme relancés » ne fait qu'inscrire la relance au journal et éviter de
 * reproposer les mêmes devis pendant sept jours ; il ne modifie aucun devis.
 *
 * Le message est un modèle fixe (aucun modèle de langage, aucune donnée envoyée à Anthropic).
 */
class QuoteFollowUpAssistant
{
    private const BATCH = 10;
    private const DEDUPE_DAYS = 7;
    private const OPEN = ['confirmed', 'sent', 'pending'];

    /** @param string $n phrase normalisée */
    public function propose(User $admin, string $n): array
    {
        $days = preg_match('/(\d{1,3})\s*j/', $n, $m) ? max(1, min(365, (int) $m[1])) : 10;
        $cutoff = $this->today()->copy()->subDays($days)->addDay()->toDateString();

        $already = AgentEvent::where('type', 'relance_devis')->whereIn('status', [AgentEvent::STATUS_ROUTED, AgentEvent::STATUS_DONE])
            ->where('created_at', '>=', now()->subDays(self::DEDUPE_DAYS))->get()
            ->flatMap(fn (AgentEvent $e) => array_column($e->payload['items'] ?? [], 'document_id'))->all();

        $rows = DB::table('document_headers as d')->leftJoin('document_footers as f', 'f.document_header_id', '=', 'd.id')->leftJoin('third_partners as t', 't.id', '=', 'd.thirdPartner_id')
            ->whereNull('d.deleted_at')->where('d.document_type', 'QuoteSale')->whereIn('d.status', self::OPEN)->where('d.issued_at', '<', $cutoff)
            ->orderBy('d.issued_at')->get(['d.id', 'd.reference', 'd.issued_at', 't.tp_title', 't.tp_phone', 't.tp_email', 'f.total_ttc'])
            ->reject(fn ($r) => in_array($r->id, $already, true))->values();
        if ($rows->isEmpty()) {
            return $this->reply("Aucun devis à relancer : aucun devis ouvert depuis plus de {$days} jour(s) qui n'ait pas déjà été relancé ces " . self::DEDUPE_DAYS . ' derniers jours.');
        }

        $company = (string) (Setting::get('company', 'name') ?: Setting::get('general', 'company_name') ?: 'notre équipe');
        $items = [];
        $links = [];
        $lines = [];
        foreach ($rows->take(self::BATCH) as $r) {
            $client = $r->tp_title ?: 'Madame, Monsieur';
            $message = $this->message($client, (string) $r->reference, Carbon::parse($r->issued_at), (float) $r->total_ttc, $company);
            $wa = $this->whatsapp((string) $r->tp_phone, $message);
            $mail = filter_var($r->tp_email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $r->tp_email . '?subject=' . rawurlencode("Votre devis {$r->reference}") . '&body=' . rawurlencode($message) : null;
            $items[] = ['document_id' => $r->id, 'reference' => $r->reference, 'client' => $client, 'amount' => round((float) $r->total_ttc, 2), 'issued_at' => (string) $r->issued_at, 'channel' => $wa ? 'whatsapp' : ($mail ? 'email' : null), 'message' => $message];
            $age = Carbon::parse($r->issued_at)->diffInDays($this->today());
            $lines[] = "• {$r->reference} — {$client} — " . $this->money((float) $r->total_ttc) . " — {$age} jour(s)" . ($wa ? '' : ($mail ? ' (pas de téléphone : e-mail)' : ' (ni téléphone ni e-mail : à relancer autrement)'));
            $link = $wa ?? $mail;
            $link && $links[] = ['label' => "Relancer {$r->reference} (" . ($wa ? 'WhatsApp' : 'e-mail') . ')', 'to' => $link];
        }

        $event = AgentEvent::create([
            'type' => 'relance_devis', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED, 'agent_id' => Agent::where('domain', 'ventes')->value('id'),
            'payload' => ['text' => 'Relance de ' . count($items) . " devis sans réponse (plus de {$days} jours)", 'items' => $items, 'days' => $days, 'requested_by' => $admin->name],
        ]);

        $rest = $rows->count() - count($items);

        return $this->reply(
            "{$rows->count()} devis ouvert(s) depuis plus de {$days} jour(s), les plus anciens d'abord (lot #{$event->id}) :\n\n" . implode("\n", $lines) . ($rest > 0 ? "\n… et {$rest} autre(s), pour le prochain lot." : '')
            . "\n\nMessage préparé pour le premier devis :\n« " . str_replace("\n", ' / ', $items[0]['message']) . ' »'
            . "\n\nO3 n'envoie rien : cliquez sur un bouton « Relancer … » pour ouvrir WhatsApp ou votre messagerie avec le message déjà rempli, puis envoyez-le vous-même. Quand c'est fait, « Marquer ces devis comme relancés » évite de les reproposer pendant " . self::DEDUPE_DAYS . ' jours.',
            suggestions: [['label' => 'Marquer ces devis comme relancés', 'text' => "applique le lot #{$event->id}"], ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"]],
            links: $links,
            eventId: $event->id,
        );
    }

    /** Inscrit la relance au journal ; aucun devis n'est modifié. */
    public function apply(User $admin, AgentEvent $event): array
    {
        $items = $event->payload['items'] ?? [];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($event->payload ?? [], ['marked_by' => $admin->name, 'marked_at' => now()->toDateTimeString()])]);
        AgentAction::create(['agent_id' => $event->agent_id, 'event_id' => $event->id, 'action' => 'quote_followup_recorded', 'level' => 'approval', 'input' => ['requested_by' => $event->payload['requested_by'] ?? null], 'result' => ['quotes' => array_column($items, 'reference')]]);

        return $this->reply('Relance de ' . count($items) . ' devis inscrite au journal : ' . implode(', ', array_column($items, 'reference')) . '. Ils ne seront pas reproposés avant ' . self::DEDUPE_DAYS . ' jours. Aucun devis n\'a été modifié.', eventId: $event->id);
    }

    private function message(string $client, string $reference, Carbon $date, float $amount, string $company): string
    {
        return "Bonjour {$client},\n\nNous revenons vers vous au sujet de notre devis {$reference} du " . $date->format('d/m/Y') . ', d\'un montant de ' . number_format($amount, 2, ',', ' ') . " MAD TTC.\n\nSouhaitez-vous le valider, ou avez-vous des questions ou une modification à demander ?\n\nCordialement,\n{$company}";
    }

    /** Le lien WhatsApp d'un numéro marocain (0612345678, +212612345678, 212612345678…), message déjà rempli. Null si le numéro est inutilisable. */
    private function whatsapp(string $phone, string $message): ?string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';
        $d = preg_replace('/^00/', '', $d) ?? $d;
        $d = match (true) {
            str_starts_with($d, '212') && strlen($d) === 12 => $d,
            str_starts_with($d, '0') && strlen($d) === 10    => '212' . substr($d, 1),
            strlen($d) === 9                                 => '212' . $d,
            default                                          => '',
        };

        return $d === '' ? null : "https://wa.me/{$d}?text=" . rawurlencode($message);
    }

    private function today(): Carbon
    {
        return Carbon::now(Setting::get('locale', 'timezone') ?: config('app.timezone'))->startOfDay();
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD TTC';
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
