<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slide extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'button_text',
        'link_type',
        'link_id',
        'link_url',
        'position',
        'sort_order',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'is_active'  => 'boolean',
        ];
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

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    // ── Relations (polymorphic-like via link_type + link_id) ───
    public function linkedPromotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'link_id');
    }

    public function linkedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'link_id');
    }

    public function linkedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'link_id');
    }

    /**
     * Get the resolved link URL for the eCom frontend.
     */
    public function getResolvedLinkAttribute(): ?string
    {
        return match ($this->link_type) {
            'promotion' => $this->link_id ? "/promotions/{$this->linkedPromotion?->slug}" : null,
            'category'  => $this->link_id ? "/categories/{$this->link_id}" : null,
            'product'   => $this->link_id ? "/products/{$this->linkedProduct?->p_slug}" : null,
            'url'       => $this->link_url,
            default     => null,
        };
    }
}
