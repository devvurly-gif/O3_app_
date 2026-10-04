<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Seuil HITL configurable sans déploiement. value null = « à définir » : tant
 * qu'il n'est pas fixé, l'action reste soumise à validation.
 */
class AgentThreshold extends Model
{
    protected $fillable = ['agent_domain', 'action_type', 'parameter', 'value', 'unit'];

    protected $casts = ['value' => 'decimal:2'];
}
