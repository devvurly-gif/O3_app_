<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un message de la messagerie commandes (reçu ou envoyé). Voir
 * App\Services\Messaging\InboundOrderService.
 */
class OrderMessage extends Model
{
    public const CHANNEL_LABELS = [
        'whatsapp'   => 'WhatsApp',
        'sms'        => 'SMS',
        'web_staff'  => 'chat équipe',
        'web_client' => 'chat boutique',
    ];

    protected $fillable = [
        'channel', 'direction', 'phone', 'third_partner_id', 'user_id', 'body',
        'provider_message_id', 'parse_method', 'status', 'document_id', 'reply_to_id', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'third_partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_id');
    }
}
