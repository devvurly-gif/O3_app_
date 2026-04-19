<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property bool $is_active
 * @property Carbon|null $licensed_until
 * @property string|null $tenant_id
 */
class Module extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'tenant_id',
        'name',
        'display_name',
        'description',
        'is_active',
        'license_key',
        'licensed_until',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'licensed_until'  => 'datetime',
            'settings'        => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isEnabled(): bool
{
    // Use optional() or check if it's already a Carbon instance
    $date = $this->licensed_until instanceof \Carbon\Carbon 
            ? $this->licensed_until 
            : \Illuminate\Support\Carbon::parse($this->licensed_until);

    return $this->is_active && ($this->licensed_until === null || $date->isFuture());
}

    public static function enabled(string $name): bool
    {
        $tenantId = tenant('id') ?? null;
        return Cache::remember("module.{$tenantId}.{$name}", 60, function () use ($name, $tenantId) {
            $module = static::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->first();
            return $module?->isEnabled() ?? false;
        });
    }

    public static function clearCache(string $name): void
    {
        $tenantId = tenant('id') ?? null;
        Cache::forget("module.{$tenantId}.{$name}");
    }

    public static function allActiveNames(): array
    {
        $tenantId = tenant('id') ?? null;
        return Cache::remember("modules.active.{$tenantId}", 60, function () use ($tenantId) {
            return static::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get()
                ->filter(fn (self $m) => $m->isEnabled())
                ->pluck('name')
                ->toArray();
        });
    }

    public static function clearAllCache(): void
    {
        $tenantId = tenant('id') ?? null;
        Cache::forget("modules.active.{$tenantId}");
    }
}
