<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\AgentThreshold;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\Setting;

/**
 * Agent Recouvrement : contrôle les encaissements et prépare les relances de
 * paiement. Il ne contacte jamais un client : chaque relance est un brouillon
 * (PaymentReminder) qu'un humain valide, modifie ou rejette.
 *
 * Contrôle (lecture seule) : écarts entre le pied de facture et les paiements
 * enregistrés, statuts incohérents, paiements sans document.
 *
 * Relances, à partir des jours de retard (seuils modifiables dans `agent_thresholds`,
 * paramètres days_level1/2/3, 1 / 15 / 30 jours par défaut) :
 *   niveau 1 : rappel courtois ; niveau 2 : rappel ferme ; niveau 3 : escalade
 *   humaine (pas de message au client, une note pour l'équipe).
 * Un niveau n'est préparé qu'une fois, et seulement si le niveau précédent a
 * été envoyé : un brouillon en attente ou rejeté ne s'escalade pas tout seul.
 */
class CollectionsAgent
{
    public const DEFAULT_DAYS = [1 => 1, 2 => 15, 3 => 30];

    /**
     * @return array{anomalies: array<int, array<string, string>>, overdue: int, created: array<int, int>, by_level: array<int, int>}
     */
    public function handle(AgentEvent $event): array
    {
        $anomalies = $this->verify();
        $prepared = $this->prepare($event->id);

        $result = [
            'anomalies' => $anomalies,
            'overdue'   => $prepared['overdue'],
            'created'   => $prepared['created'],
            'by_level'  => $prepared['by_level'],
        ];

        AgentAction::create([
            'agent_id' => Agent::where('domain', 'recouvrement')->value('id'),
            'event_id' => $event->id,
            'case_id'  => $event->case_id,
            'action'   => 'verify_collections_and_prepare_reminders',
            'level'    => 'approval',
            'input'    => [],
            'result'   => $result,
        ]);

        return $result;
    }

    /** @return array<int, array<string, string>> */
    public function verify(): array
    {
        $anomalies = [];

        $invoices = DocumentHeader::where('document_type', 'InvoiceSale')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with(['footer', 'payments'])
            ->get();

        foreach ($invoices as $d) {
            if (!$d->footer) {
                $anomalies[] = $this->anomaly('NO_FOOTER', $d, 'Facture sans pied de document (totaux introuvables).');
                continue;
            }
            $ttc = (float) $d->footer->total_ttc;
            $paid = (float) $d->footer->amount_paid;
            $due = (float) $d->footer->amount_due;
            $recorded = (float) $d->payments->sum('amount');

            if (abs($paid - $recorded) > 0.01) {
                $anomalies[] = $this->anomaly('PAID_MISMATCH', $d, sprintf('Encaissé au pied de facture : %.2f MAD, paiements enregistrés : %.2f MAD.', $paid, $recorded));
            }
            if (abs($ttc - $paid - $due) > 0.01) {
                $anomalies[] = $this->anomaly('BALANCE_MISMATCH', $d, sprintf('Total %.2f MAD, encaissé %.2f MAD, reste dû %.2f MAD : le solde ne tombe pas juste.', $ttc, $paid, $due));
            }
            if ($d->status === 'paid' && $due > 0.01) {
                $anomalies[] = $this->anomaly('PAID_WITH_BALANCE', $d, sprintf('Facture marquée payée mais il reste %.2f MAD dû.', $due));
            }
            if ($d->status !== 'paid' && $due <= 0.01 && $ttc > 0) {
                $anomalies[] = $this->anomaly('SETTLED_NOT_PAID', $d, "Plus rien à payer mais le statut est « {$d->status} ».");
            }
        }

        foreach (Payment::whereDoesntHave('document')->get() as $p) {
            $anomalies[] = [
                'code'      => 'ORPHAN_PAYMENT',
                'reference' => $p->payment_code,
                'message'   => sprintf('Paiement de %.2f MAD (%s) rattaché à aucun document.', (float) $p->amount, $p->method),
            ];
        }

        return $anomalies;
    }

    /** @return array{overdue: int, created: array<int, int>, by_level: array<int, int>} */
    public function prepare(?int $eventId = null): array
    {
        $days = $this->thresholds();
        $overdue = DocumentHeader::where('document_type', 'InvoiceSale')
            ->whereNotIn('status', ['paid', 'cancelled', 'draft'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->startOfDay())
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
            ->with(['footer', 'thirdPartner'])
            ->orderBy('due_at')
            ->get();

        $created = [];
        $byLevel = [1 => 0, 2 => 0, 3 => 0];

        foreach ($overdue as $d) {
            $late = (int) $d->due_at->startOfDay()->diffInDays(now()->startOfDay());
            $target = $this->levelFor($late, $days);
            if ($target === 0) {
                continue;   // pas encore assez en retard pour le premier niveau
            }
            $existing = PaymentReminder::where('document_header_id', $d->id)->pluck('status', 'level');

            // Premier niveau applicable qui n'a pas encore de relance, et un seul par passage.
            // Un niveau déjà préparé (même rejeté) n'est jamais recréé : la décision humaine est
            // respectée ; on n'escalade pas un brouillon en attente ou rejeté.
            $level = 0;
            foreach (range(1, $target) as $candidate) {
                if ($existing->has($candidate)) {
                    continue;
                }
                if ($candidate === 1 || ($existing[$candidate - 1] ?? null) === PaymentReminder::STATUS_SENT) {
                    $level = $candidate;
                }
                break;
            }
            if ($level === 0) {
                continue;
            }

            $client = $d->thirdPartner;
            $reminder = PaymentReminder::create([
                'document_header_id' => $d->id,
                'third_partner_id'   => $client?->id,
                'level'              => $level,
                'channel'            => $level >= 3 || !$client?->tp_phone ? 'manual' : 'whatsapp',
                'message'            => $this->message($level, $d, $late),
                'amount_due'         => $d->footer->amount_due,
                'days_overdue'       => $late,
                'status'             => PaymentReminder::STATUS_DRAFT,
                'event_id'           => $eventId,
            ]);

            $created[] = $reminder->id;
            $byLevel[$level]++;
        }

        return ['overdue' => $overdue->count(), 'created' => $created, 'by_level' => $byLevel];
    }

    /** @return array<int, int> jours de retard à partir desquels chaque niveau s'applique */
    public function thresholds(): array
    {
        $days = self::DEFAULT_DAYS;
        $rows = AgentThreshold::where('agent_domain', 'recouvrement')->where('action_type', 'relance')
            ->whereIn('parameter', ['days_level1', 'days_level2', 'days_level3'])
            ->get();

        foreach ($rows as $row) {
            if ($row->value !== null) {
                $days[(int) substr($row->parameter, -1)] = (int) $row->value;
            }
        }

        return $days;
    }

    /** @param array<int, int> $days */
    private function levelFor(int $late, array $days): int
    {
        $level = 0;
        foreach ([1, 2, 3] as $l) {
            if ($late >= $days[$l]) {
                $level = $l;
            }
        }

        return $level;
    }

    private function message(int $level, DocumentHeader $d, int $late): string
    {
        $client = $d->thirdPartner?->tp_title ?? 'Madame, Monsieur';
        $company = Setting::get('company', 'name') ?: Setting::get('general', 'company_name') ?: '';
        $amount = number_format((float) $d->footer->amount_due, 2, ',', ' ') . ' MAD';
        $due = $d->due_at->format('d/m/Y');
        $signature = $company !== '' ? "\n{$company}" : '';

        return match ($level) {
            1 => "Bonjour {$client},\nSauf erreur de notre part, la facture {$d->reference} de {$amount}, échue le {$due}, reste à régler. "
                . "Pourriez-vous nous confirmer la date de votre règlement ? Merci d'avance.{$signature}",
            2 => "Bonjour {$client},\nMalgré notre précédent rappel, la facture {$d->reference} de {$amount}, échue depuis {$late} jours (le {$due}), "
                . "n'est toujours pas réglée. Merci de procéder au paiement dans les meilleurs délais, ou de nous contacter si un point bloque.{$signature}",
            default => "Escalade : la facture {$d->reference} de {$client} ({$amount}) est impayée depuis {$late} jours (échéance {$due}). "
                . "Deux rappels sont restés sans règlement. À traiter par téléphone ou visite : aucun message automatique n'est envoyé au client.",
        };
    }

    /** @return array<string, string> */
    private function anomaly(string $code, DocumentHeader $d, string $message): array
    {
        return ['code' => $code, 'reference' => $d->reference, 'message' => $message];
    }
}
