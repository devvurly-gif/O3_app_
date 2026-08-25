<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Un compte de trésorerie : caisse espèces, banque, chéquier…
 *
 * `ca_payment_method` fait le pont avec les règlements documents : tout
 * `Payment` dont la méthode correspond est imputé à ce compte au moment du
 * calcul du solde, sans qu'aucune ligne ne soit dupliquée.
 */
class CashAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ca_title', 'ca_type', 'ca_initial_balance', 'ca_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Compte de trésorerie {$eventName}");
    }

    protected $fillable = [
        'ca_title',
        'ca_code',
        'ca_type',
        'ca_payment_method',
        'ca_initial_balance',
        'ca_status',
        'ca_notes',
        'structure_id',
    ];

    protected $casts = [
        'ca_initial_balance' => 'decimal:2',
        'ca_status'          => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'cash_account_id');
    }

    public function recurrences(): HasMany
    {
        return $this->hasMany(CashRecurrence::class, 'cash_account_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }
}
