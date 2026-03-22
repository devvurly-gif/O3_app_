<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLigne extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_header_id',
        'product_id',
        'sort_order',
        'line_type',
        'designation',
        'reference',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'tax_percent',
        'total_ligne_ht',
        'total_tax',
        'total_ttc',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:2',
            'unit_price'       => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'tax_percent'      => 'decimal:2',
            'total_ligne_ht'   => 'decimal:2',
            'total_tax'        => 'decimal:2',
            'total_ttc'        => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Auto-compute totals before saving
    protected static function booted(): void
    {
        static::saving(function (DocumentLigne $ligne) {
            $baseHt          = $ligne->quantity * $ligne->unit_price;
            $discountAmt     = $baseHt * ($ligne->discount_percent / 100);
            $ligne->total_ligne_ht = $baseHt - $discountAmt;
            $ligne->total_tax      = $ligne->total_ligne_ht * ($ligne->tax_percent / 100);
            $ligne->total_ttc      = $ligne->total_ligne_ht + $ligne->total_tax;
        });
    }

    public function isActive(): bool    { return $this->status === 'active'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
