<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle d'écriture répétitive : loyer mensuel, salaires, abonnement…
 *
 * La récurrence ne crée rien elle-même ; la commande `treasury:generate`
 * matérialise les échéances arrivées à terme en `CashTransaction`.
 */
class CashRecurrence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cr_label',
        'cr_direction',
        'cr_amount',
        'cash_account_id',
        'cash_category_id',
        'thirdPartner_id',
        'cr_method',
        'cr_frequency',
        'cr_anchor_day',
        'cr_start_date',
        'cr_end_date',
        'cr_next_run_at',
        'cr_status',
        'cr_notes',
    ];

    protected $casts = [
        'cr_amount'      => 'decimal:2',
        'cr_start_date'  => 'date',
        'cr_end_date'    => 'date',
        'cr_next_run_at' => 'date',
        'cr_status'      => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class, 'cash_category_id');
    }

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'thirdPartner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'cash_recurrence_id');
    }

    public function scopeDue(Builder $query, ?string $on = null): Builder
    {
        $date = $on ?: now()->toDateString();

        // La date de fin se compare à la prochaine échéance, pas à l'horizon
        // demandé : un abonnement terminé en juillet doit encore livrer ses
        // échéances de juin et juillet quand on génère en décembre.
        return $query->where('cr_status', true)
            ->whereDate('cr_next_run_at', '<=', $date)
            ->where(function ($q) {
                $q->whereNull('cr_end_date')->orWhereColumn('cr_end_date', '>=', 'cr_next_run_at');
            });
    }

    /**
     * Échéance suivante après $from.
     *
     * Le jour d'ancrage est réappliqué à chaque saut plutôt que reporté depuis
     * la date précédente : sans ça, un loyer au 31 tombe au 28 en février puis
     * reste bloqué au 28 pour tous les mois suivants.
     */
    public function nextOccurrenceAfter(CarbonImmutable $from): CarbonImmutable
    {
        $anchor = (int) $this->cr_anchor_day;

        return match ($this->cr_frequency) {
            'weekly'    => $from->addWeek()->startOfWeek()->addDays(max(1, min(7, $anchor)) - 1),
            'quarterly' => $this->applyMonthlyAnchor($from->addMonths(3), $anchor),
            'yearly'    => $this->applyMonthlyAnchor($from->addYear(), $anchor),
            default     => $this->applyMonthlyAnchor($from->addMonth(), $anchor),
        };
    }

    private function applyMonthlyAnchor(CarbonImmutable $date, int $anchor): CarbonImmutable
    {
        $day = max(1, min($anchor, $date->daysInMonth));

        return $date->setDay($day);
    }
}
