<?php

namespace App\Observers;

use App\Events\NewNotification;
use Illuminate\Notifications\DatabaseNotification;

class NotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        broadcast(new NewNotification(
            userId: $notification->notifiable_id,
            notification: [
                'id'         => $notification->id,
                'type'       => $notification->type,
                'data'       => $notification->data,
                'read_at'    => null,
                'created_at' => $notification->created_at->toISOString(),
            ],
        ))->toOthers();
    }
}
