<?php

namespace App\Observers;

use App\Models\DocumentHeader;
use App\Models\User;
use App\Notifications\OrderConfirmation;
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

        foreach ($recipients as $user) {
            try {
                $user->notify(new OrderConfirmation($doc));
            } catch (\Throwable $e) {
                Log::warning("Email notification failed for document {$doc->reference}, falling back to database only.", [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);

                try {
                    $user->notify((new OrderConfirmation($doc))->onlyDatabase());
                } catch (\Throwable) {
                }
            }
        }
    }
}
