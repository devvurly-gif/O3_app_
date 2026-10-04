<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Journal d'audit des agents IA : en ajout seul. Aucune ligne n'est modifiée
 * ni supprimée (voir la spécification du socle, section 2).
 */
class AgentAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['agent_id', 'event_id', 'case_id', 'action', 'level', 'input', 'result', 'document_id'];

    protected $casts = ['input' => 'array', 'result' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('agent_actions est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('agent_actions est en ajout seul.'));
    }
}
