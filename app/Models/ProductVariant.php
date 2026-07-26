<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'label', 'attributes', 'sku', 'price', 'stock', 'is_active', 'position'];

    protected $casts = [
        'attributes' => 'array',
        'price'      => 'decimal:2',
        'stock'      => 'decimal:2',
        'is_active'  => 'boolean',
        'position'   => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseHasStock::class, 'variant_id');
    }

    public function getTotalStockAttribute(): float
    {
        if ($this->relationLoaded('warehouseStocks')) {
            return (float) $this->warehouseStocks->sum('stockLevel');
        }
        return (float) $this->warehouseStocks()->sum('stockLevel');
    }
}
