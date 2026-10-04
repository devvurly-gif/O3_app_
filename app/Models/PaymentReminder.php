<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relance de paiement préparée par l'agent Recouvrement (brouillon tant qu'un
 * humain ne l'a pas validée). Niveaux : 1 rappel courtois, 2 rappel ferme,
 * 3 escalade vers un humain (pas de message automatique au client).
 */
class PaymentReminder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'document_header_id', 'third_partner_id', 'level', 'channel', 'message', 'amount_due',
        'days_overdue', 'status', 'error', 'reason', 'event_id', 'decided_by', 'sent_at',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'sent_at'    => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'third_partner_id');
    }
}
