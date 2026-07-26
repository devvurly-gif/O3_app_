<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VariantOptionType extends Model
{
    protected $fillable = ['name', 'slug', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'position' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function values(): HasMany
    {
        return $this->hasMany(VariantOptionValue::class)->orderBy('position');
    }
}
