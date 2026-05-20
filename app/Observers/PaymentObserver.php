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

    private function recalculatePartnerEncours(Payment $payment): void
    {
        if ($payment->document_header_id) {
            $partner = $payment->document->thirdPartner;
            $newEncours = $partner->recalculateEncours();
            PartnerEncoursUpdated::dispatch($partner, $newEncours);
        }
    }
}
