<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseHasStock extends Model
{
    use HasFactory;

    protected $table = 'warehouse_has_stock';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'stockLevel',
        'stockAtTime',
        'wh_average',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'stockLevel'  => 'decimal:2',
            'wh_average'  => 'decimal:2',
            'stockAtTime' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isLowStock(float $threshold = 5): bool
    {
        return $this->stockLevel <= $threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stockLevel <= 0;
    }
}
