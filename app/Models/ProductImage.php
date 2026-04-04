<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['title', 'altContent', 'url', 'isPrimary', 'product_id'];

    protected function casts(): array
    {
        return ['isPrimary' => 'boolean'];
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
