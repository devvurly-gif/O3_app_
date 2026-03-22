<?php

namespace App\Models;

use App\Models\Traits\BelongsToStructure;
use App\Notifications\PaymentReceived;
use App\Services\PaymentNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, BelongsToStructure, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['document_header_id', 'amount', 'method', 'paid_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Paiement {$eventName}");
    }

    public bool $skipStructureId = true;
    public string $codeField = 'payment_code';

    /**
     * When true, skip sending email/WhatsApp notifications on create.
     * Used by bulkPayment() to avoid sending N individual notifications,
     * then send a single grouped one after the transaction.
     */
    public static bool $skipNotification = false;

    protected $fillable = [
        'payment_code',
        'document_header_id',
        'amount',
        'method',
        'paid_at',
        'reference',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'document_header_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // After creating a payment, update footer, header status, partner encours, and send notification
    protected static function booted(): void
    {
        static::created(function (Payment $payment) {
            $payment->document->footer->recalculateAmountDue();
            $payment->document->footer->syncHeaderStatus();

            // Decrement partner encours_actuel
            $partner = $payment->document->thirdPartner;
            if ($partner) {
                $partner->decrement('encours_actuel', (float) $payment->amount);
                // Never go below zero
                if ($partner->encours_actuel < 0) {
                    $partner->update(['encours_actuel' => 0]);
                }
            }

            // Send notification (email + WhatsApp) for individual payments
            if (!static::$skipNotification && $partner) {
                try {
                    $footer = $payment->document->footer->fresh();

                    app(PaymentNotificationService::class)->send(
                        partner: $partner,
                        totalPaid: (float) $payment->amount,
                        method: $payment->method,
                        reference: $payment->reference,
                        affectedInvoices: [[
                            'reference'      => $payment->document->reference,
                            'amount_applied' => (float) $payment->amount,
                            'amount_due'     => (float) $footer->amount_due,
                            'is_paid'        => $footer->isPaid(),
                        ]],
                    );
                } catch (\Throwable $e) {
                    Log::warning("Payment notification failed: {$e->getMessage()}");
                }
            }

            // In-app notification for admin/manager users
            if (!static::$skipNotification) {
                try {
                    $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                        ->where('is_active', true)
                        ->get();

                    foreach ($recipients as $recipient) {
                        $recipient->notify(new PaymentReceived($payment));
                    }
                } catch (\Throwable $e) {
                    Log::warning("PaymentReceived in-app notification failed: {$e->getMessage()}");
                }
            }
        });

        static::deleted(function (Payment $payment) {
            $payment->document->footer->recalculateAmountDue();
            $payment->document->footer->syncHeaderStatus();

            // Re-increment partner encours_actuel
            $partner = $payment->document->thirdPartner;
            if ($partner) {
                $partner->increment('encours_actuel', (float) $payment->amount);
            }
        });
    }
}
