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

            // Recalculate partner encours_actuel from source data
            // (formula respects `ventes.paiement_sur_bl` setting)
            $partner = $payment->document->thirdPartner;
            if ($partner) {
                $partner->recalculateEncours();
            }

            // Dispatch notifications asynchronously (non-blocking)
            if (!static::$skipNotification) {
                $paymentId = $payment->id;
                dispatch(function () use ($paymentId) {
                    try {
                        $payment = Payment::with(['document.footer', 'document.thirdPartner'])->find($paymentId);
                        if (!$payment) return;

                        $partner = $payment->document->thirdPartner;
                        $footer = $payment->document->footer;

                        // Send email + WhatsApp notification to partner
                        if ($partner) {
                            app(PaymentNotificationService::class)->send(
                                partner: $partner,
                                totalPaid: (float) $payment->amount,
                                method: $payment->method,
                                reference: $payment->reference,
                                affectedInvoices: [[
                                    'reference'      => $payment->document->reference,
                                    'amount_applied' => (float) $payment->amount,
                                    'amount_due'     => (float) ($footer->amount_due ?? 0),
                                    'is_paid'        => $footer ? $footer->isPaid() : false,
                                ]],
                            );
                        }

                        // In-app notification for admin/manager users
                        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                            ->where('is_active', true)
                            ->get();

                        foreach ($recipients as $recipient) {
                            $recipient->notify(new PaymentReceived($payment));
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Payment notification failed: {$e->getMessage()}");
                    }
                })->afterResponse();
            }
        });

        static::deleted(function (Payment $payment) {
            $payment->document->footer->recalculateAmountDue();
            $payment->document->footer->syncHeaderStatus();

            // Recalculate partner encours_actuel from source data
            $partner = $payment->document->thirdPartner;
            if ($partner) {
                $partner->recalculateEncours();
            }
        });
    }
}
