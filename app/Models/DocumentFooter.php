<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFooter extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_header_id',
        'total_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'amount_paid',
        'amount_due',
        'payment_method',
        'payment_date',
        'total_in_words',
        'is_signed',
        'stamp_path',
        'is_printed',
        'is_sent',
        'sent_via',
        'bank_details',
        'legal_mentions',
    ];

    protected function casts(): array
    {
        return [
            'total_ht'       => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax'      => 'decimal:2',
            'total_ttc'      => 'decimal:2',
            'amount_paid'    => 'decimal:2',
            'amount_due'     => 'decimal:2',
            'is_signed'      => 'boolean',
            'is_printed'     => 'boolean',
            'is_sent'        => 'boolean',
            'sent_via'       => 'array',
            'payment_date'   => 'date',
        ];
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    // ── Payment status helpers ─────────────────────────────────────
    public function isPaid(): bool
    {
        return $this->amount_due <= 0;
    }

    public function isPartial(): bool
    {
        return $this->amount_paid > 0 && $this->amount_due > 0;
    }

    // Recalculate amount_due when a payment is added
    public function recalculateAmountDue(): void
    {
        $totalPaid = $this->header->payments()->sum('amount');
        $this->update([
            'amount_paid' => $totalPaid,
            'amount_due'  => $this->total_ttc - $totalPaid,
        ]);
    }

    // Auto sync payment status to document header.
    //
    // DeliveryNote (BL) is excluded: a BL's status reflects the *goods*
    // lifecycle (draft → confirmed/livré → cancelled), not payment.
    // Payment state is tracked separately via amount_paid / amount_due in
    // the footer and surfaced in the credit/encours columns. This keeps
    // the BL badge as "Livré" regardless of whether the customer paid
    // cash at the till, on credit, or partially.
    public function syncHeaderStatus(): void
    {
        $this->loadMissing('header');
        if ($this->header?->document_type === 'DeliveryNote') {
            return;
        }

        $status = match(true) {
            $this->isPaid()    => 'paid',
            $this->isPartial() => 'partial',
            default            => null,
        };

        if ($status) {
            $this->header()->update(['status' => $status]);
        }
    }
}
