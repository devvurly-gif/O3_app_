<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

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
        'url_ready'      => 'boolean',  // stored in JSON data column
    ];

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast() && $this->plan === 'trial';
    }

    /**
     * Build an absolute URL against this tenant's own domain, not the
     * central app's. Needed anywhere a link is generated outside an HTTP
     * request context (queued notifications/mailables) — Laravel's url()
     * helper falls back to config('app.url') there, which is the central
     * domain, so e.g. a "view this document" email link would send staff
     * to the central admin instead of the tenant's own app.
     */
    public function appUrl(string $path = ''): string
    {
        $domain = $this->domains()->first()?->domain;

        if (!$domain) {
            return url($path);
        }

        return 'https://' . $domain . '/' . ltrim($path, '/');
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
