<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Promotion extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'value', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Promotion {$eventName}");
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'banner_image',
        'banner_text',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'value'        => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    // ── Boot ────────────────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (self $promo) {
            if (empty($promo->slug)) {
                $promo->slug = Str::slug($promo->name);
            }
        });
    }

    // ── Relations ───────────────────────────────────────────────
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot('promo_price')
            ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    // ── Helpers ─────────────────────────────────────────────────
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->ends_at && $this->ends_at->isPast()) return false;
        return true;
    }

    /**
     * Calculate the discounted price for a given original price.
     */
    public function calculateDiscount(float $originalPrice): float
    {
        if ($this->type === 'percentage') {
            $discount = $originalPrice * ($this->value / 100);
            if ($this->max_discount) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return round($originalPrice - $discount, 2);
        }

        // fixed_amount
        return round(max(0, $originalPrice - (float) $this->value), 2);
    }
}
