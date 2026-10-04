<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier d'affaire : mémoire partagée entre agents pour un client donné
 * (commande, devis, facture, achat liés).
 */
class AgentCase extends Model
{
    protected $fillable = ['third_partner_id', 'status', 'refs', 'opened_at', 'closed_at'];

    protected $casts = [
        'refs'      => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'third_partner_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentEvent::class, 'case_id');
    }
}
