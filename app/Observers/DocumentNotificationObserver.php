<?php

namespace App\Observers;

use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Services\SaleNotificationService;
use Illuminate\Support\Facades\Log;

class DocumentNotificationObserver
{
    public function created(DocumentHeader $doc): void
    {
        $this->notifyIfConfirmed($doc);
    }

    public function updated(DocumentHeader $doc): void
    {
        if (!$doc->wasChanged('status')) {
            return;
        }

        $this->notifyIfConfirmed($doc);
    }

    private function notifyIfConfirmed(DocumentHeader $doc): void
    {
        if (!in_array($doc->status, ['confirmed', 'pending'])) {
            return;
        }

        $doc->loadMissing(['thirdPartner', 'footer']);

        $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
            ->where('is_active', true)
            ->get();

        // Notifications are queued (ShouldQueue) — they won't block the response
        foreach ($recipients as $user) {
            $user->notify(new OrderConfirmation($doc));
        }

        $this->notifyCustomer($doc);
    }

    /**
     * WhatsApp the customer, but only once the sale is actually confirmed.
     *
     * Staff are notified on 'pending' too because they're the ones who have
     * to act on it; the customer isn't. A pending document is a document
     * awaiting staff validation — telling the buyer "confirmed" at that
     * point is a promise the tenant hasn't made yet. Ecommerce orders get
     * their own "commande reçue" message from EcomOrderController instead,
     * which is why 'pending' stays silent here rather than duplicating it.
     */
    private function notifyCustomer(DocumentHeader $doc): void
    {
        if ($doc->status !== 'confirmed') {
            return;
        }

        $documentId = $doc->id;

        // Deferred like the payment notifications in Payment.php: Twilio is a
        // network call and must never sit inside the request that saved the sale.
        dispatch(function () use ($documentId) {
            try {
                $document = DocumentHeader::with(['thirdPartner', 'footer', 'lignes'])->find($documentId);

                if (!$document) {
                    return;
                }

                app(SaleNotificationService::class)->send($document, 'confirmed');
            } catch (\Throwable $e) {
                Log::warning("SaleNotification: WhatsApp failed for document {$documentId}: {$e->getMessage()}");
            }
        })->afterResponse();
    }
}
