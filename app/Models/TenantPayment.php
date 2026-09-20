<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un règlement d'abonnement encaissé par O3App auprès d'un tenant.
 *
 * Vit sur la connexion CENTRALE. Ne pas confondre avec App\Models\Payment, qui
 * est le règlement d'un client du tenant, dans la base du tenant.
 *
 * @property string $tenant_id
 * @property string $plan
 * @property string $billing_period
 * @property int    $amount_cents
 * @property \Carbon\Carbon $paid_at
 * @property \Carbon\Carbon $period_starts_at
 * @property \Carbon\Carbon $period_ends_at
 */
class TenantPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan',
        'billing_period',
        'amount_cents',
        'paid_at',
        'period_starts_at',
        'period_ends_at',
        'method',
        'reference',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount_cents'     => 'integer',
        'paid_at'          => 'date',
        'period_starts_at' => 'date',
        'period_ends_at'   => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('paid_at')->orderByDesc('id');
    }
}
