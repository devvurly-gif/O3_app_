<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PosSession extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pos_terminal_id', 'user_id', 'opened_at', 'closed_at', 'opening_cash', 'closing_cash'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Session POS {$eventName}");
    }

    protected $fillable = [
        'pos_terminal_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
            'opening_cash'    => 'decimal:2',
            'closing_cash'    => 'decimal:2',
            'expected_cash'   => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'pos_session_id')
                    ->where('document_type', 'TicketSale');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
