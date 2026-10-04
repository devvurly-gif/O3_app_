<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\PaymentReminder;
use App\Models\User;
use App\Services\WhatsAppService;

/**
 * Validation humaine d'une relance. C'est le seul endroit qui peut contacter un
 * client, et il ne s'exécute que sur le geste d'un utilisateur.
 *
 * - whatsapp : envoi par le fournisseur configuré. Si l'envoi échoue (canal non
 *   configuré, fournisseur qui refuse), la relance reste réessayable (« failed »).
 * - manual   : l'utilisateur a relancé lui-même (téléphone, visite) ; elle est
 *   simplement marquée traitée.
 */
class ReminderSender
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /** @throws \DomainException si la relance n'est plus à valider */
    public function validate(PaymentReminder $reminder, User $by, ?string $channel = null, ?string $message = null): PaymentReminder
    {
        if (!in_array($reminder->status, [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED], true)) {
            throw new \DomainException('Cette relance a déjà été traitée.');
        }

        $channel = $channel ?: $reminder->channel;
        if ($message !== null && trim($message) !== '') {
            $reminder->message = $message;
        }
        $reminder->channel = $channel;
        $reminder->decided_by = $by->id;

        if ($channel === 'whatsapp') {
            $phone = $reminder->thirdPartner?->tp_phone;
            if (!$phone) {
                return $this->fail($reminder, "Ce client n'a pas de numéro de téléphone.");
            }
            if (!$this->whatsapp->send($phone, $reminder->message)) {
                return $this->fail($reminder, "Envoi impossible : WhatsApp n'est pas configuré ou le fournisseur a refusé le message.");
            }
        }

        $reminder->forceFill(['status' => PaymentReminder::STATUS_SENT, 'sent_at' => now(), 'error' => null])->save();
        $this->log($reminder, 'reminder_validated', $by);

        return $reminder;
    }

    public function reject(PaymentReminder $reminder, User $by, ?string $reason = null): PaymentReminder
    {
        if (!in_array($reminder->status, [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED], true)) {
            throw new \DomainException('Cette relance a déjà été traitée.');
        }

        $reminder->forceFill(['status' => PaymentReminder::STATUS_REJECTED, 'reason' => $reason, 'decided_by' => $by->id])->save();
        $this->log($reminder, 'reminder_rejected', $by);

        return $reminder;
    }

    private function fail(PaymentReminder $reminder, string $error): PaymentReminder
    {
        $reminder->forceFill(['status' => PaymentReminder::STATUS_FAILED, 'error' => $error])->save();

        return $reminder;
    }

    private function log(PaymentReminder $reminder, string $action, User $by): void
    {
        AgentAction::create([
            'agent_id'    => Agent::where('domain', 'recouvrement')->value('id'),
            'event_id'    => $reminder->event_id,
            'action'      => $action,
            'level'       => 'approval',
            'input'       => ['reminder_id' => $reminder->id, 'level' => $reminder->level, 'channel' => $reminder->channel],
            'result'      => ['status' => $reminder->status, 'by' => $by->name],
            'document_id' => $reminder->document_header_id,
        ]);
    }
}
