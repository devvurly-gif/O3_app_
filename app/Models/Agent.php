<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registre des agents IA : un enregistrement par domaine, lié au compte
 * technique Sanctum de l'agent (voir les commandes *:agent-token).
 */
class Agent extends Model
{
    protected $fillable = ['domain', 'name', 'user_id', 'ability', 'default_level', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
