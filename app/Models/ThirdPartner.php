<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ThirdPartner extends Model
{
    use HasFactory, SoftDeletes, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tp_title', 'tp_Role', 'tp_status', 'tp_email', 'tp_phone'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Tiers {$eventName}");
    }

    public string $codeField = 'tp_code';

    protected $fillable = [
        'tp_title',
        'tp_code',
        'tp_Ice_Number',
        'tp_Rc_Number',
        'tp_patente_Number',
        'tp_IdenFiscal',
        'tp_Role',
        'tp_status',
        'tp_phone',
        'tp_email',
        'tp_address',
        'tp_city',
        'encours_actuel',
        'seuil_credit',
        'type_compte',
        'frequence_facturation',
        'structure_id',
        'price_list_id',
    ];

    protected function casts(): array
    {
        return [
            'tp_status'       => 'boolean',
            'encours_actuel'  => 'decimal:2',
            'seuil_credit'    => 'decimal:2',
        ];
    }

    // ── Incrementor resolution ────────────────────────────────────
    // Suppliers and customers use separate code sequences.
    // 'both' defaults to the Supplier incrementor.
    public function resolveIncrementorModel(): string
    {
        return $this->tp_Role === 'customer' ? 'Customer' : 'Supplier';
    }

    // ── Role helpers ──────────────────────────────────────────────
    public function isCustomer(): bool { return in_array($this->tp_Role, ['customer', 'both']); }
    public function isSupplier(): bool { return in_array($this->tp_Role, ['supplier', 'both']); }

    // ── Account-type helpers ────────────────────────────────────
    public function isEnCompte(): bool { return $this->type_compte === 'en_compte'; }
    public function isNormal(): bool   { return $this->type_compte !== 'en_compte'; }

    // ── Relations ─────────────────────────────────────────────────
    public function structure(): BelongsTo
    {
        return $this->belongsTo(StructureIncrementor::class, 'structure_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function documentHeaders(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'thirdPartner_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'thirdPartner_id')
                    ->where('document_type', 'InvoiceSale');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'thirdPartner_id')
                    ->where('document_type', 'PurchaseOrder');
    }

    /**
     * Confirmed BLs that have NOT yet been converted to an invoice.
     */
    public function uninvoicedDeliveryNotes(): HasMany
    {
        return $this->hasMany(DocumentHeader::class, 'thirdPartner_id')
            ->where('document_type', 'DeliveryNote')
            ->where('status', 'confirmed')
            ->whereDoesntHave('children', fn ($q) => $q->where('document_type', 'InvoiceSale'));
    }
}
