<?php

namespace App\Observers;

use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\OrderConfirmation;

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
    }
}
