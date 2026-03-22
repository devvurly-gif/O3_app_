<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StructureIncrementor extends Model
{
    use HasFactory;

    protected $fillable = [
        'si_title',
        'si_model',
        'si_template',
        'si_nextTrick',
        'si_status',
    ];

    protected function casts(): array
    {
        return [
            'si_status'    => 'boolean',
            'si_nextTrick' => 'integer',
        ];
    }

    /**
     * Generate the next code from the template and atomically increment the counter.
     * Template example: "CAT-{000}" → "CAT-001", "CAT-002", …
     */
    public function generateCode(): string
    {
        return DB::transaction(function () {
            // Lock the row so concurrent requests don't get the same number
            $incrementor = static::lockForUpdate()->findOrFail($this->id);

            $counter  = $incrementor->si_nextTrick;
            $template = $incrementor->si_template;

            // Replace {000…} placeholder — width = number of zeros inside braces
            $code = preg_replace_callback('/\{(0+)\}/', function ($matches) use ($counter) {
                $width = strlen($matches[1]);
                return str_pad($counter, $width, '0', STR_PAD_LEFT);
            }, $template);

            $incrementor->increment('si_nextTrick');

            return $code;
        });
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'structure_id');
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'structure_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'structure_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'structure_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'structure_id');
    }

    public function thirdPartners(): HasMany
    {
        return $this->hasMany(ThirdPartner::class, 'structure_id');
    }
}
