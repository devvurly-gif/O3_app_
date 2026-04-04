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
     * Ensure the URL always uses the tenant asset route.
     * Converts /storage/xxx to /tenancy/assets/xxx automatically.
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

                // Fix old /storage/ prefix → /tenancy/assets/
                if (str_starts_with($value, '/storage/')) {
                    return '/tenancy/assets/' . substr($value, 9);
                }

                // Already correct
                if (str_starts_with($value, '/tenancy/assets/')) {
                    return $value;
                }

                // Fallback: prepend tenant asset route
                return '/tenancy/assets/' . ltrim($value, '/');
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
