<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory, BelongsToStructure;

    public string $codeField = 'br_code';

    protected $fillable = ['br_title', 'br_code', 'br_status', 'structure_id'];

    protected function casts(): array
    {
        return ['br_status' => 'boolean'];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
