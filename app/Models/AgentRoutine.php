<?php

namespace App\Models;

use App\Services\Agents\RoutineSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Une routine de l'orchestrateur : des étapes connues (brouillons et lectures) lancées soit à heure fixe, soit
 * quand un événement interne d'O3 se produit (`trigger`). Elle ne fait jamais que préparer des propositions :
 * aucune étape n'écrit sans la validation d'un humain.
 */
class AgentRoutine extends Model
{
    protected $fillable = ['name', 'steps', 'schedule', 'trigger', 'last_event_id', 'agent_id', 'is_active', 'created_by', 'last_run_at', 'next_run_at', 'last_status', 'last_summary'];

    protected $casts = [
        'steps'       => 'array',
        'schedule'    => 'array',
        'trigger'     => 'array',
        'is_active'   => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /** Déclenchée par un événement (et non par une heure) ? */
    public function isEventDriven(): bool
    {
        return !empty($this->trigger['event_type']);
    }

    /** La prochaine échéance horaire ; null pour une routine déclenchée par un événement. */
    public function nextScheduledRun(?Carbon $after = null): ?Carbon
    {
        return $this->isEventDriven() ? null : RoutineSchedule::next($this->schedule, $after);
    }
}
