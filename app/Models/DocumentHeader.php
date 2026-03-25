<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentHeader extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'document_type', 'status', 'thirdPartner_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Document {$eventName}");
    }

    protected $fillable = [
        'document_incrementor_id',
        'reference',
        'document_type',
        'document_title',
        'parent_id',
        'thirdPartner_id',
        'company_role',
        'user_id',
        'warehouse_id',
        'warehouse_dest_id',
        'pos_session_id',
        'status',
        'issued_at',
        'due_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at'    => 'date',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────
    public function incrementor(): BelongsTo
    {
        return $this->belongsTo(DocumentIncrementor::class, 'document_incrementor_id');
    }

    public function thirdPartner(): BelongsTo
    {
        return $this->belongsTo(ThirdPartner::class, 'thirdPartner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseDest(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_dest_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'parent_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(DocumentLigne::class, 'document_header_id')
                    ->orderBy('sort_order');
    }

    public function footer(): HasOne
    {
        return $this->hasOne(DocumentFooter::class, 'document_header_id');
    }

    public function stockMouvements(): HasMany
    {
        return $this->hasMany(StockMouvement::class, 'document_header_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'document_header_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    // ── Type helpers ──────────────────────────────────────────────
    public function isType(string $type): bool         { return $this->document_type === $type; }
    public function isInvoiceSale(): bool               { return $this->isType('InvoiceSale'); }
    public function isPurchaseOrder(): bool              { return $this->isType('PurchaseOrder'); }
    public function isDeliveryNote(): bool               { return $this->isType('DeliveryNote'); }
    public function isCreditNoteSale(): bool             { return $this->isType('CreditNoteSale'); }
    public function isCreditNotePurchase(): bool         { return $this->isType('CreditNotePurchase'); }
    public function isQuoteSale(): bool                  { return $this->isType('QuoteSale'); }
    public function isCustomerOrder(): bool              { return $this->isType('CustomerOrder'); }
    public function isReceiptNotePurchase(): bool        { return $this->isType('ReceiptNotePurchase'); }
    public function isInvoicePurchase(): bool            { return $this->isType('InvoicePurchase'); }
    public function isReturnSale(): bool                 { return $this->isType('ReturnSale'); }
    public function isReturnPurchase(): bool             { return $this->isType('ReturnPurchase'); }
    public function isStockEntry(): bool                 { return $this->isType('StockEntry'); }
    public function isStockExit(): bool                  { return $this->isType('StockExit'); }
    public function isStockAdjustment(): bool            { return $this->isType('StockAdjustmentNote'); }
    public function isTicketSale(): bool                  { return $this->isType('TicketSale'); }
    public function isStockTransfer(): bool              { return $this->isType('StockTransfer'); }
    public function isStockDocument(): bool              { return in_array($this->document_type, ['StockEntry','StockExit','StockAdjustmentNote','StockTransfer']); }
    public function isConverted(): bool                  { return $this->status === 'converted'; }
    public function isPaid(): bool                       { return $this->status === 'paid'; }
    public function isDraft(): bool                      { return $this->status === 'draft'; }
    public function isApplied(): bool                    { return $this->status === 'applied'; }

    // Backward-compatible aliases (deprecated)
    public function isInvoice(): bool             { return $this->isInvoiceSale(); }
    public function isQuote(): bool               { return $this->isQuoteSale(); }
    public function isCreditNote(): bool          { return $this->isCreditNoteSale(); }
    public function isReceiptNote(): bool         { return $this->isReceiptNotePurchase(); }
    public function isPurchaseInvoice(): bool     { return $this->isInvoicePurchase(); }

    // ── Conversion matrix ─────────────────────────────────────────
    public function canConvertTo(string $targetType): bool
    {
        $allowed = [
            'QuoteSale'            => ['CustomerOrder'],
            'CustomerOrder'        => ['DeliveryNote'],
            'DeliveryNote'         => ['InvoiceSale'],
            'InvoiceSale'          => ['CreditNoteSale'],
            'PurchaseOrder'        => ['ReceiptNotePurchase'],
            'ReceiptNotePurchase'  => ['InvoicePurchase'],
            'InvoicePurchase'      => ['CreditNotePurchase'],
            // Les documents de stock ne se convertissent pas
        ];

        return !$this->isConverted()
            && in_array($targetType, $allowed[$this->document_type] ?? []);
    }
}
