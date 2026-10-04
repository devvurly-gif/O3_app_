<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tout ce qui entre ou circule entre les agents. Statuts : new, routed,
 * in_progress, done, to_sort, rejected, error.
 */
class AgentEvent extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_ROUTED = 'routed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_TO_SORT = 'to_sort';
    public const STATUS_ERROR = 'error';
    /** Refusé en amont (PIN incorrect, limite de débit) : jamais routé, aucun dossier. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type', 'source', 'payload', 'entities', 'case_id', 'parent_event_id',
        'order_message_id', 'agent_id', 'priority', 'status',
    ];

    protected $casts = ['payload' => 'array', 'entities' => 'array'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(AgentCase::class, 'case_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function orderMessage(): BelongsTo
    {
        return $this->belongsTo(OrderMessage::class);
    }
}
