<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['p_title', 'p_sku', 'p_salePrice', 'p_costPrice', 'p_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Produit {$eventName}");
    }

    public string $codeField = 'p_code';

    protected $appends = ['total_stock'];

    protected $fillable = [
        'p_title',
        'p_code',
        'p_description',
        'p_sku',
        'p_ean13',
        'p_imei',
        'p_purchasePrice',
        'p_salePrice',
        'p_cost',
        'p_status',
        'p_taxRate',
        'p_unit',
        'category_id',
        'brand_id',
        'structure_id',
        'is_ecom',
        'p_slug',
        'p_long_description',
        'p_notes',
    ];

    protected function casts(): array
    {
        return [
            'p_purchasePrice' => 'decimal:2',
            'p_salePrice'     => 'decimal:2',
            'p_cost'          => 'decimal:2',
            'p_taxRate'       => 'decimal:2',
            'p_status'        => 'boolean',
            'is_ecom'         => 'boolean',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('isPrimary', true);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseHasStock::class, 'product_id');
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_has_stock')
                    ->withPivot(['stockLevel', 'stockAtTime', 'wh_average'])
                    ->withTimestamps();
    }

    public function stockMouvements(): HasMany
    {
        return $this->hasMany(StockMouvement::class, 'product_id');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withPivot('promo_price')
            ->withTimestamps();
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class, 'price_list_items')
            ->withPivot(['price_ht', 'price_ttc', 'min_qty', 'valid_from', 'valid_to'])
            ->withTimestamps();
    }

    /**
     * Get the currently active promotion with highest priority.
     */
    public function activePromotion(): ?Promotion
    {
        return $this->promotions()->active()->orderByDesc('priority')->first();
    }

    // ── Boot ─────────────────────────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (self $product) {
            if ($product->is_ecom && empty($product->p_slug)) {
                $product->p_slug = Str::slug($product->p_title);
            }

            // Auto-generate SKU if not provided
            if (empty($product->p_sku)) {
                $slug = Str::slug($product->p_title);
                $date = now()->format('Ymd');
                $product->p_sku = "SKU-{$date}-{$slug}";
            }
        });

        static::updating(function (self $product) {
            if ($product->is_ecom && empty($product->p_slug)) {
                $product->p_slug = Str::slug($product->p_title);
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Accessor: total stock across all warehouses.
     * Appended to JSON via $appends so it's always available in API responses.
     */
    public function getTotalStockAttribute(): float
    {
        // If warehouseStocks is already loaded, use the collection to avoid N+1
        if ($this->relationLoaded('warehouseStocks')) {
            return (float) $this->warehouseStocks->sum('stockLevel');
        }
        return (float) $this->warehouseStocks()->sum('stockLevel');
    }

    /**
     * Keep the old method for backward compatibility.
     */
    public function totalStock(): float
    {
        return $this->total_stock;
    }

    public function stockInWarehouse(int $warehouseId): float
    {
        return $this->warehouseStocks()
                    ->where('warehouse_id', $warehouseId)
                    ->value('stockLevel') ?? 0;
    }

    public function salePriceTtc(): float
    {
        return $this->p_salePrice * (1 + $this->p_taxRate / 100);
    }
}
