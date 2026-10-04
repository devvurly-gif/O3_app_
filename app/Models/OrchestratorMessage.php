<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un message de la conversation administrateur ↔ orchestrateur. role : admin
 * (l'administrateur écrit) ou orchestrator (le « chef » répond).
 */
class OrchestratorMessage extends Model
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ORCHESTRATOR = 'orchestrator';

    protected $fillable = ['user_id', 'role', 'body', 'meta'];

    protected $casts = ['meta' => 'array'];
}
