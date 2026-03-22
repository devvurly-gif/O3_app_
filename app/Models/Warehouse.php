<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Warehouse extends Model
{
    use HasFactory, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['wh_title', 'wh_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Entrepôt {$eventName}");
    }

    public string $codeField = 'wh_code';

    protected $fillable = ['wh_title', 'wh_code', 'wh_status', 'structure_id'];

    protected function casts(): array
    {
        return ['wh_status' => 'boolean'];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseHasStock::class, 'warehouse_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'warehouse_has_stock')
                    ->withPivot(['stockLevel', 'stockAtTime', 'wh_average'])
                    ->withTimestamps();
    }

    public function stockMouvements(): HasMany
    {
        return $this->hasMany(StockMouvement::class, 'warehouse_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }

    // Get stock level for a specific product
    public function stockFor(int $productId): float
    {
        return $this->stocks()
                    ->where('product_id', $productId)
                    ->value('stockLevel') ?? 0;
    }
}
