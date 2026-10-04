<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Règle de routage configurable : première règle active qui correspond, par
 * priorité décroissante. conditions = {source, keywords[], mime}.
 */
class AgentRoutingRule extends Model
{
    protected $fillable = ['event_type', 'conditions', 'agent_domain', 'priority', 'is_active', 'phase'];

    protected $casts = ['conditions' => 'array', 'is_active' => 'boolean'];
}
