<?php

namespace App\Observers;

use App\Events\PartnerEncoursUpdated;
use App\Models\Payment;

/**
 * Automatically recalculate customer/supplier encours_actuel
 * whenever a payment changes.
 */
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->recalculatePartnerEncours($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->recalculatePartnerEncours($payment);
    }

    public function restored(Payment $payment): void
    {
        $this->recalculatePartnerEncours($payment);
    }

    /**
     * Both hops are optional and both really happen:
     *   - `thirdPartner_id` is nullable by design (walk-in sale, invoice
     *     issued before the customer record exists), and the walk-in
     *     fallback itself resolves to null when CLIENT-COMPTOIR is absent;
     *   - `document` is null once the header is soft-deleted, which the
     *     `deleted` / `restored` hooks below both reach.
     *
     * There is simply no encours to recompute in either case.
     */
    private function recalculatePartnerEncours(Payment $payment): void
    {
        $partner = $payment->document?->thirdPartner;

        if (! $partner) {
            return;
        }

        PartnerEncoursUpdated::dispatch($partner, $partner->recalculateEncours());
    }
}
