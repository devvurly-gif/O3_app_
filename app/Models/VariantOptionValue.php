<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantOptionValue extends Model
{
    protected $fillable = ['variant_option_type_id', 'key', 'value', 'position'];

    protected $casts = ['position' => 'integer'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(VariantOptionType::class, 'variant_option_type_id');
    }
}
