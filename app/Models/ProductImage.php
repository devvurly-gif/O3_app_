<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['title', 'altContent', 'url', 'isPrimary', 'product_id'];

    protected function casts(): array
    {
        return ['isPrimary' => 'boolean'];
    }

    /**
     * Normalize image URL: ensure /storage/ prefix for tenant file serving.
     * Both /storage/ and /tenancy/assets/ are handled by Laravel tenant routes.
     * External URLs (http/https) are left untouched.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function (string $value) {
                // External URLs — leave as-is
                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//')) {
                    return $value;
                }

                // Normalize /tenancy/assets/ back to /storage/
                if (str_starts_with($value, '/tenancy/assets/')) {
                    return '/storage/' . substr($value, 16);
                }

                // Already /storage/ prefix — good
                if (str_starts_with($value, '/storage/')) {
                    return $value;
                }

                // No prefix — add /storage/
                return '/storage/' . ltrim($value, '/');
            },
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // When setting as primary, unset others
    public function setAsPrimary(): void
    {
        static::where('product_id', $this->product_id)->update(['isPrimary' => false]);
        $this->update(['isPrimary' => true]);
    }
}
