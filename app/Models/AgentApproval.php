<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * File de validation humaine : enregistre la décision (approved, modified,
 * rejected) ; l'exécution reste du ressort de l'agent. decision null = en attente.
 */
class AgentApproval extends Model
{
    protected $fillable = ['action_id', 'proposal', 'decision', 'modifications', 'decided_by', 'decided_at', 'reason'];

    protected $casts = [
        'proposal'      => 'array',
        'modifications' => 'array',
        'decided_at'    => 'datetime',
    ];
}
