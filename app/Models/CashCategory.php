<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Poste de dépense ou de recette (Loyer, Salaires, Apport…).
 *
 * `cc_direction` vaut 'in', 'out' ou 'both' : il filtre les catégories
 * proposées à la saisie selon le sens de l'écriture.
 */
class CashCategory extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['cc_title', 'cc_direction', 'cc_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Catégorie de trésorerie {$eventName}");
    }

    protected $fillable = [
        'cc_title',
        'cc_code',
        'cc_direction',
        'cc_color',
        'cc_status',
    ];

    protected $casts = [
        'cc_status' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'cash_category_id');
    }

    public function acceptsDirection(string $direction): bool
    {
        return $this->cc_direction === 'both' || $this->cc_direction === $direction;
    }
}
