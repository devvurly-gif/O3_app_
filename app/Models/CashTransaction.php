<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Une écriture de trésorerie saisie à la main : dépense ('out') ou
 * recette ('in').
 *
 * Une écriture annulée (`ct_status = cancelled`) reste visible au journal mais
 * sort de tous les totaux — on ne supprime pas une pièce de caisse, on la
 * contre-passe. Le scope `active()` est le point de passage obligé de tout
 * calcul de solde.
 */
class CashTransaction extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['ct_direction', 'ct_amount', 'ct_date', 'ct_label', 'ct_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Écriture de trésorerie {$eventName}");
    }

    /** Alimente le compteur TRZ-{00000} via BelongsToStructure. */
    public string $codeField = 'ct_code';

    protected $fillable = [
        'ct_code',
        'cash_account_id',
        'cash_category_id',
        'cash_recurrence_id',
        'ct_direction',
        'ct_amount',
        'ct_date',
        'ct_label',
        'ct_method',
        'ct_reference',
        'thirdPartner_id',
        'document_header_id',
        'ct_transfer_group',
        'ct_attachment_path',
        'ct_attachment_name',
        'ct_notes',
        'ct_status',
        'user_id',
    ];

    protected $casts = [
        'ct_amount' => 'decimal:2',
        'ct_date'   => 'date',
    ];

    protected static function booted(): void
    {
        // Le code TRZ vient du StructureIncrementor du tenant. Le trait
        // BelongsToStructure ferait aussi le travail, mais il renseignerait
        // structure_id à partir de l'utilisateur connecté — or une écriture
        // générée par une récurrence n'a pas d'utilisateur connecté.
        static::creating(function (CashTransaction $transaction) {
            if (empty($transaction->ct_code)) {
                $incrementor = StructureIncrementor::where('si_model', 'CashTransaction')->first();
                if ($incrementor) {
                    $transaction->ct_code = $incrementor->generateCode();
                }
            }
        });
    }

    // ── Relations ─────────────────────────────────────────────────
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashCategory::class, 'cash_category_id');
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(CashRecurrence::class, 'cash_recurrence_id');
    }

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'thirdPartner_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ct_status', 'active');
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate('ct_date', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('ct_date', '<=', $to));
    }

    // ── Helpers ───────────────────────────────────────────────────
    public function isIncome(): bool   { return $this->ct_direction === 'in'; }
    public function isExpense(): bool  { return $this->ct_direction === 'out'; }
    public function isTransfer(): bool { return $this->ct_transfer_group !== null; }

    /** Montant signé : positif en recette, négatif en dépense. */
    public function signedAmount(): float
    {
        return $this->isIncome() ? (float) $this->ct_amount : -(float) $this->ct_amount;
    }
}
