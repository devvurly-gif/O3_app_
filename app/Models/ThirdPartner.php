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

    // SECURITY (M2): `encours_actuel` is intentionally NOT fillable.
    // It reflects the computed outstanding credit of a customer and
    // must only be set by `recalculateEncours()` (via `forceFill`) or
    // the payment flow — never by a mass-assigned request payload.
    // DB default is 0, so create calls that used to pass 0 explicitly
    // still work (the value is dropped silently by mass-assignment
    // and the column default takes over).
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

    /**
     * Recalculate encours_actuel authoritatively from source data.
     *
     * Formula (sales side, when `ventes.paiement_sur_bl` active):
     *   encours = TOTAL_FACTURES + TOTAL_BL_UNINVOICED
     *           - TOTAL_PAIEMENTS - TOTAL_BONS_DE_RETOUR
     *
     * When `paiement_sur_bl` is OFF, BLs don't contribute (only the invoice does).
     *
     * Purchase side mirrors the same logic (InvoicePurchase / BR / ReturnPurchase).
     *
     * Never goes below zero. Persists when $persist is true (default) and returns the value.
     */
    public function recalculateEncours(bool $persist = true): float
    {
        // ── Customer side ────────────────────────────────────────────
        // Which document types contribute to encours:
        //   - InvoiceSale and TicketSale: always.
        //   - DeliveryNote (BL): every confirmed BL that has not yet been
        //     converted to an InvoiceSale. A confirmed BL represents a real
        //     delivery → a real debt, regardless of whether it originated at
        //     the POS or via the sales form. The previous `paiement_sur_bl`
        //     setting has been retired to keep the "Reste dû" column of the
        //     Documents tab and the Crédit tab perfectly aligned.
        //     The whereDoesntHave(InvoiceSale) guard still prevents double
        //     counting once a BL is billed.
        $invoicesAndBlTotal = (float) DocumentHeader::query()
            ->where('thirdPartner_id', $this->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(function ($q) {
                $q->whereIn('document_type', ['InvoiceSale', 'TicketSale'])
                  ->orWhere(function ($sub) {
                      $sub->where('document_type', 'DeliveryNote')
                          ->whereDoesntHave('children', fn ($c) => $c->where('document_type', 'InvoiceSale'));
                  });
            })
            ->join('document_footers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->sum('document_footers.total_ttc');

        $returnSalesTotal = (float) DocumentHeader::query()
            ->where('thirdPartner_id', $this->id)
            ->where('document_type', 'ReturnSale')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->join('document_footers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->sum('document_footers.total_ttc');

        // ── Supplier side (mirrors sales) ────────────────────────────
        $purchaseIncoming = ['InvoicePurchase'];
        // (No separate "paiement sur BR" setting today; keep receipt note excluded.)

        $purchasesTotal = (float) DocumentHeader::query()
            ->where('thirdPartner_id', $this->id)
            ->whereIn('document_type', $purchaseIncoming)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->join('document_footers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->sum('document_footers.total_ttc');

        $returnPurchasesTotal = (float) DocumentHeader::query()
            ->where('thirdPartner_id', $this->id)
            ->where('document_type', 'ReturnPurchase')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->join('document_footers', 'document_footers.document_header_id', '=', 'document_headers.id')
            ->sum('document_footers.total_ttc');

        // ── Payments (both sides) ────────────────────────────────────
        // Exclude POS "credit" payments — those are IOUs (promise to pay later),
        // not actual cash received, so they must not reduce encours.
        $paymentsTotal = (float) Payment::query()
            ->whereHas('document', fn ($q) => $q->where('thirdPartner_id', $this->id))
            ->where('method', '!=', 'credit')
            ->sum('amount');

        $encours = ($invoicesAndBlTotal - $returnSalesTotal)
                 + ($purchasesTotal   - $returnPurchasesTotal)
                 -  $paymentsTotal;

        $encours = max(0.0, round($encours, 2));

        if ($persist) {
            // Persist without triggering activity log / observers
            $this->forceFill(['encours_actuel' => $encours])->saveQuietly();
            $this->encours_actuel = $encours;
        }

        return $encours;
    }
}
