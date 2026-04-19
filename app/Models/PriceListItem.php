<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    protected $fillable = [
        'price_list_id',
        'product_id',
        'price_ht',
        'price_ttc',
        'min_qty',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'price_ht'   => 'decimal:2',
            'price_ttc'  => 'decimal:2',
            'min_qty'    => 'integer',
            'valid_from' => 'date',
            'valid_to'   => 'date',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if this price is currently valid (within valid_from/valid_to window).
     */
    public function isCurrentlyValid(): bool
    {
        $today = now()->toDateString();
        if ($this->valid_from && $this->valid_from->toDateString() > $today) return false;
        if ($this->valid_to && $this->valid_to->toDateString() < $today) return false;
        return true;
    }
}
