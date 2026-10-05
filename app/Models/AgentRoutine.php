<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une routine planifiée de l'orchestrateur : des étapes connues (brouillons et lectures) à heure fixe.
 * Elle ne fait jamais que préparer des propositions : aucune étape n'écrit sans la validation d'un humain.
 */
class AgentRoutine extends Model
{
    protected $fillable = ['name', 'steps', 'schedule', 'agent_id', 'is_active', 'created_by', 'last_run_at', 'next_run_at', 'last_status', 'last_summary'];

    protected $casts = [
        'steps'       => 'array',
        'schedule'    => 'array',
        'is_active'   => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];
}
