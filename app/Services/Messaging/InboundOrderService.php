<?php

namespace App\Services\Messaging;

use App\Http\Requests\Api\WhatsAppOrderImportRequest;
use App\Models\DocumentHeader;
use App\Models\OrderMessage;
use App\Models\Role;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Notifications\MessageOrderDrafted;
use App\Services\SmsService;
use App\Services\Ventes\CustomerLookup;
use App\Services\Ventes\WhatsAppOrderImportService;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Messagerie commandes : transforme un message (WhatsApp, SMS, chat équipe,
 * chat boutique) en BL brouillon et répond à l'expéditeur par un récapitulatif.
 *
 * Mêmes garde-fous que l'agent de facturation client, puisque la création
 * passe par le même moteur (WhatsAppOrderImportService) :
 *   - BL toujours en brouillon, stock seulement « pending » ; la confirmation
 *     reste un geste manuel dans O3 ;
 *   - jamais de création de client ni de produit, jamais de choix entre
 *     plusieurs produits possibles ;
 *   - tout ou rien : si un passage du message n'est pas compris, rien n'est
 *     créé et la réponse dit quoi corriger (pas de BL partiel silencieux).
 *
 * Expéditeur :
 *   - numéro d'un utilisateur O3 (users.phone) → mode équipe : la 1re ligne
 *     « Client : … » désigne le client ;
 *   - numéro d'un client (tp_phone) → la commande est pour ce client, et lui
 *     seul (une ligne « Client : » venant d'un client est ignorée) ;
 *   - numéro inconnu → rien n'est créé, l'IA n'est pas appelée.
 */
class InboundOrderService
{
    private const EXTERNAL = ['whatsapp', 'sms'];
    private const MAX_INBOUND_PER_HOUR = 30;

    public function __construct(
        private OrderTextParser $parser,
        private AiOrderExtractor $ai,
        private OrderReplyFormatter $replies,
        private CustomerLookup $customers,
        private WhatsAppOrderImportService $import,
        private WhatsAppService $whatsapp,
        private SmsService $sms,
    ) {
    }

    /**
     * @param string $channel whatsapp | sms | web_staff | web_client
     * @param User|null $staff employé déjà authentifié (chat équipe)
     * @param ThirdPartner|null $customer client déjà identifié de façon sûre
     *        (choisi par l'employé, ou chat boutique vérifié par code)
     * @return array{status: string, reply: ?string, message_id: ?int, document: ?array}
     */
    public function process(
        string $channel,
        ?string $phone,
        string $body,
        ?string $providerMessageId = null,
        ?User $staff = null,
        ?ThirdPartner $customer = null,
        int $numMedia = 0,
    ): array {
        $phone = PhoneNumber::normalize($phone);
        $external = in_array($channel, self::EXTERNAL, true);

        if ($providerMessageId && OrderMessage::where('provider_message_id', $providerMessageId)->exists()) {
            return ['status' => 'duplicate', 'reply' => null, 'message_id' => null, 'document' => null];
        }

        $inbound = OrderMessage::create([
            'channel'             => $channel,
            'direction'           => 'in',
            'phone'               => $phone,
            'third_partner_id'    => $customer?->id,
            'user_id'             => $staff?->id,
            'body'                => mb_substr($body, 0, 10000),
            'provider_message_id' => $providerMessageId,
            'meta'                => $numMedia > 0 ? ['num_media' => $numMedia] : null,
        ]);

        if ($channel !== 'web_staff' && Setting::get('messaging', 'inbound_enabled', 'false') !== 'true') {
            return $this->finish($inbound, 'ignored', null, meta: ['reason' => 'disabled']);
        }

        if ($external && $phone && $this->tooManyMessages($phone)) {
            return $this->finish($inbound, 'ignored', null, meta: ['reason' => 'rate_limited']);
        }

        // 1. Qui écrit ?
        if ($external) {
            $staff = $this->findStaff($phone);
            if (!$staff) {
                $found = $phone ? $this->customers->byPhone($phone) : collect();
                if ($found->count() !== 1) {
                    return $this->unknownSender($inbound, $found->count());
                }
                $customer = $found->first();
            }
        }
        $staffMode = $staff !== null;

        if ($numMedia > 0 && trim($body) === '') {
            return $this->finish($inbound, 'rejected', $this->replies->mediaNotSupported(), $staff, $customer);
        }

        // 2. Lecture : règles, puis IA en secours si elles ne comprennent pas tout.
        $parsed = $this->parser->parse($body);
        if (!$parsed->isComplete() && $this->ai->enabled()) {
            $viaAi = $this->ai->extract($body);
            if ($viaAi && $viaAi->lines !== []) {
                $parsed = $viaAi;
            }
        }

        // 3. Pour quel client ?
        if ($staffMode && !$customer) {
            $hint = $parsed->customerHint;
            if (!$hint) {
                return $this->finish($inbound, 'rejected', $this->replies->staffNeedsCustomer(), $staff, null, $parsed->method);
            }
            $found = $this->customers->byHint($hint);
            if ($found->count() !== 1) {
                $reply = $this->replies->staffCustomerProblem($hint, $found->count(),
                    $found->map(fn ($c) => "{$c->tp_code} ({$c->tp_title})")->all());
                return $this->finish($inbound, 'rejected', $reply, $staff, null, $parsed->method);
            }
            $customer = $found->first();
        }

        if (!$customer) {
            return $this->unknownSender($inbound, 0);
        }

        if ($parsed->lines === []) {
            return $this->finish($inbound, 'rejected', $this->replies->help(), $staff, $customer, $parsed->method);
        }

        // 4. Même contrat et même moteur que l'import API de l'agent de facturation client.
        $label = OrderMessage::CHANNEL_LABELS[$channel] ?? $channel;
        $payload = [
            'external_id' => 'MSG-' . $inbound->id,
            'source_text' => mb_substr($body, 0, 4000),
            'customer'    => array_filter([
                'phone' => $customer->tp_phone ?: null,
                'code'  => $customer->tp_code ?: null,
                'name'  => $customer->tp_title ?: null,
            ]),
            'lines'       => $parsed->lines,
            'notes'       => mb_substr("Reçu par {$label} de " . ($staff?->name ?? $phone ?? 'la boutique') . " :\n" . $body, 0, 2000),
        ];

        $validator = Validator::make($payload, WhatsAppOrderImportRequest::payloadRules());
        if ($validator->fails()) {
            $errors = [['code' => 'VALIDATION', 'message' => $validator->errors()->first()]];
            return $this->finish($inbound, 'rejected', $this->replies->rejected($errors, [], $staffMode), $staff, $customer, $parsed->method);
        }

        // Un passage non compris : contrôle seul (dry run) pour signaler aussi
        // les produits à préciser, mais rien n'est créé.
        $dryRun = $parsed->unparsed !== [];
        $result = $this->import->handle(
            $payload,
            $dryRun,
            $staff?->id ?? $this->channelUser()->id,
            $customer,
            "Commande reçue par {$label}",
        );

        if ($result['status'] === 'created') {
            $reply = $this->replies->created($result, $customer->tp_title);
            $this->notifyTeam($result['document']['id'], $channel, !empty($result['document']['appended']));
            return $this->finish($inbound, 'created', $reply, $staff, $customer, $parsed->method, $result['document']);
        }

        $reply = $this->replies->rejected($result['errors'] ?? [], $parsed->unparsed, $staffMode);
        return $this->finish($inbound, 'rejected', $reply, $staff, $customer, $parsed->method, null, ['errors' => $result['errors'] ?? []]);
    }

    // ───────────────────────────── Expéditeur ─────────────────────────────

    private function findStaff(?string $phone): ?User
    {
        $tail = PhoneNumber::tail($phone);
        if ($tail === null) {
            return null;
        }
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '') LIKE ?",
                ['%' . $tail]
            )
            ->get()
            ->first(fn (User $u) => PhoneNumber::equals($u->phone, $phone));
    }

    private function unknownSender(OrderMessage $inbound, int $matches): array
    {
        // Au plus une réponse par jour à un même numéro inconnu : pas de ping-pong
        // coûteux avec un robot ou un numéro qui insiste.
        $alreadyAnswered = OrderMessage::where('phone', $inbound->phone)
            ->where('direction', 'out')
            ->where('meta->kind', 'unknown_sender')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        $reply = $alreadyAnswered ? null
            : ($matches > 1 ? $this->replies->ambiguousSender() : $this->replies->unknownSender());

        return $this->finish($inbound, 'ignored', $reply, meta: ['reason' => $matches > 1 ? 'ambiguous_sender' : 'unknown_sender'], replyMeta: ['kind' => 'unknown_sender']);
    }

    private function tooManyMessages(string $phone): bool
    {
        return OrderMessage::where('phone', $phone)
            ->where('direction', 'in')
            ->where('created_at', '>=', now()->subHour())
            ->count() > self::MAX_INBOUND_PER_HOUR;
    }

    /**
     * Compte technique auteur des BL venus d'un client (pas d'utilisateur
     * connecté derrière un webhook). Sans jeton ni accès à l'interface,
     * distinct des comptes des agents IA.
     */
    private function channelUser(): User
    {
        $email = 'canal-messagerie@' . (tenant('id') ?? 'o3') . '.o3app.local';
        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        if (!$user->exists || $user->trashed()) {
            $user->name = 'Canal messagerie (automatique)';
            $user->is_active = false; // ne se connecte jamais
            if (!$user->exists) {
                $user->password = Str::password(48);
                $role = Role::where('name', 'cashier')->first();
                if ($role) {
                    $user->role_id = $role->id;
                }
            }
            $user->save();
            if ($user->trashed()) {
                $user->restore();
            }
        }

        return $user;
    }

    // ───────────────────────────── Fin de traitement ─────────────────────────────

    private function finish(
        OrderMessage $inbound,
        string $status,
        ?string $reply,
        ?User $staff = null,
        ?ThirdPartner $customer = null,
        ?string $parseMethod = null,
        ?array $document = null,
        array $meta = [],
        array $replyMeta = [],
    ): array {
        $inbound->update([
            'status'           => $status,
            'parse_method'     => $parseMethod ?? 'none',
            'user_id'          => $staff?->id ?? $inbound->user_id,
            'third_partner_id' => $customer?->id ?? $inbound->third_partner_id,
            'document_id'      => $document['id'] ?? null,
            'meta'             => array_merge($inbound->meta ?? [], $meta) ?: null,
        ]);

        if ($reply !== null) {
            $outbound = OrderMessage::create([
                'channel'          => $inbound->channel,
                'direction'        => 'out',
                'phone'            => $inbound->phone,
                'third_partner_id' => $inbound->third_partner_id,
                'user_id'          => $inbound->user_id,
                'body'             => $reply,
                'status'           => 'pending',
                'reply_to_id'      => $inbound->id,
                'document_id'      => $document['id'] ?? null,
                'meta'             => $replyMeta ?: null,
            ]);
            $outbound->update(['status' => $this->deliver($inbound->channel, $inbound->phone, $reply) ? 'sent' : 'failed']);
        }

        return [
            'status'     => $status,
            'reply'      => $reply,
            'message_id' => $inbound->id,
            'document'   => $document,
        ];
    }

    /** Les chats web affichent la réponse directement ; WhatsApp/SMS l'envoient via Twilio. */
    private function deliver(string $channel, ?string $phone, string $text): bool
    {
        if (!in_array($channel, self::EXTERNAL, true)) {
            return true;
        }
        if (!$phone) {
            return false;
        }
        return $channel === 'whatsapp'
            ? $this->whatsapp->send($phone, $text)
            : $this->sms->send($phone, $text);
    }

    private function notifyTeam(int $documentId, string $channel, bool $appended = false): void
    {
        try {
            $document = DocumentHeader::with('thirdPartner')->find($documentId);
            if (!$document) {
                return;
            }
            User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                ->where('is_active', true)
                ->get()
                ->each(fn (User $u) => $u->notify(new MessageOrderDrafted($document, $channel, $appended)));
        } catch (\Throwable $e) {
            Log::warning("Messagerie : notification équipe échouée pour le document {$documentId} : {$e->getMessage()}");
        }
    }
}
