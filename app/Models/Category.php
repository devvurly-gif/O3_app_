<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, BelongsToStructure;

    public string $codeField = 'ctg_code';

    protected $fillable = ['ctg_title', 'ctg_code', 'ctg_status', 'is_ecom', 'structure_id'];

    protected function casts(): array
    {
        return [
            'ctg_status' => 'boolean',
            'is_ecom'    => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
