<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockMouvement extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['product_id', 'warehouse_id', 'direction', 'quantity', 'reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Mouvement stock {$eventName}");
    }
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'document_header_id',
        'document_reference',
        'document_type',
        'direction',
        'reason',
        'quantity',
        'unit_cost',
        'stock_before',
        'stock_after',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:2',
            'unit_cost'    => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after'  => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function documentHeader(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIn(): bool  { return $this->direction === 'in'; }
    public function isOut(): bool { return $this->direction === 'out'; }
}
