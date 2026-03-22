<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentIncrementor extends Model
{
    use HasFactory;

    protected $fillable = [
        'di_title',
        'di_model',
        'di_domain',
        'template',
        'nextTrick',
        'status',
        'operatorSens',
    ];

    protected function casts(): array
    {
        return [
            'status'    => 'boolean',
            'nextTrick' => 'integer',
        ];
    }

    public function documentHeaders(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'document_incrementor_id');
    }

    // Generate next reference from template
    
}
