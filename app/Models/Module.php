<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property bool $is_active
 * @property Carbon|null $licensed_until
 */
class Module extends Model
{
    protected $fillable = [
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
        return Cache::remember("module.{$name}", 60, function () use ($name) {
            $module = static::where('name', $name)->first();
            return $module?->isEnabled() ?? false;
        });
    }

    public static function clearCache(string $name): void
    {
        Cache::forget("module.{$name}");
    }

    public static function allActiveNames(): array
    {
        return Cache::remember('modules.active', 60, function () {
            return static::where('is_active', true)
                ->get()
                ->filter(fn (self $m) => $m->isEnabled())
                ->pluck('name')
                ->toArray();
        });
    }

    public static function clearAllCache(): void
    {
        Cache::forget('modules.active');
    }
}
