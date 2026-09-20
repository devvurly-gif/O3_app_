<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Facture d'abonnement emise par O3App a un tenant.
 *
 * Vit sur la connexion CENTRALE. Les montants sont en centimes de dirham.
 *
 * @property string  $number
 * @property string  $tenant_id
 * @property InvoiceStatus $status
 * @property int     $amount_ht_cents
 * @property int     $vat_cents
 * @property int     $amount_ttc_cents
 * @property array|null $snapshot   Identite des deux parties, figee a l'emission
 */
class TenantInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'tenant_id',
        'plan',
        'billing_period',
        'issued_at',
        'due_at',
        'period_starts_at',
        'period_ends_at',
        'subtotal_cents',
        'setup_fee_cents',
        'amount_ht_cents',
        'vat_rate',
        'vat_cents',
        'amount_ttc_cents',
        'status',
        'sent_at',
        'paid_at',
        'tenant_payment_id',
        'pdf_path',
        'snapshot',
        'note',
    ];

    protected $casts = [
        'status'            => InvoiceStatus::class,
        'issued_at'         => 'date',
        'due_at'            => 'date',
        'period_starts_at'  => 'date',
        'period_ends_at'    => 'date',
        'paid_at'           => 'date',
        'sent_at'           => 'datetime',
        'subtotal_cents'    => 'integer',
        'setup_fee_cents'   => 'integer',
        'amount_ht_cents'   => 'integer',
        'vat_cents'         => 'integer',
        'amount_ttc_cents'  => 'integer',
        'vat_rate'          => 'decimal:2',
        'snapshot'          => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TenantPayment::class, 'tenant_payment_id');
    }

    /**
     * Statut garanti non nul — meme precaution que Tenant::currentStatus().
     */
    public function currentStatus(): InvoiceStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof InvoiceStatus
            ? $status
            : InvoiceStatus::tryFrom((string) $status) ?? InvoiceStatus::Draft;
    }

    public function isOverdue(): bool
    {
        return !$this->currentStatus()->isSettled()
            && $this->due_at !== null
            && $this->due_at->endOfDay()->isPast();
    }

    public function scopeForTenant(Builder $query, string $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    /** Factures qui couvrent encore leur periode (tout sauf les annulees). */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Cancelled->value);
    }

    /** Factures emises et non encore reglees. */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Sent->value]);
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('issued_at')->orderByDesc('id');
    }
}
