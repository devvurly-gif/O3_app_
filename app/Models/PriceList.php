<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PriceList extends Model
{
    protected $fillable = [
        'name',
        'description',
        'channel',
        'is_default',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'priority'   => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'price_list_items')
            ->withPivot(['price_ht', 'price_ttc', 'min_qty', 'valid_from', 'valid_to'])
            ->withTimestamps();
    }

    public function customers(): HasMany
    {
        return $this->hasMany(ThirdPartner::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->whereIn('channel', [$channel, 'all']);
    }

    /**
     * Get the default price list (used when customer has no price_list_id).
     */
    public static function default(?string $channel = null): ?self
    {
        $q = static::active()->where('is_default', true);
        if ($channel) {
            $q->forChannel($channel);
        }
        return $q->orderByDesc('priority')->first();
    }
}
