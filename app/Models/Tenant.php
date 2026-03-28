<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Custom columns (not stored in 'data' JSON).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'plan',
            'is_active',
            'trial_ends_at',
        ];
    }

    protected $casts = [
        'is_active'      => 'boolean',
        'trial_ends_at'  => 'date',
    ];

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast() && $this->plan === 'trial';
    }

    public function hasModule(string $module): bool
    {
        $modules = match ($this->plan) {
            'starter'    => ['ventes', 'stock'],
            'business'   => ['ventes', 'achats', 'stock', 'pos'],
            'enterprise' => ['ventes', 'achats', 'stock', 'pos', 'ecom', 'whatsapp'],
            default      => ['ventes', 'stock'],
        };

        return in_array($module, $modules);
    }
}
