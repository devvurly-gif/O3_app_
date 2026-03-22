<?php

namespace App\Providers;

use App\Models\DocumentHeader;
use App\Observers\DocumentAchatObserver;
use App\Observers\DocumentNotificationObserver;
use App\Observers\DocumentVenteObserver;
use App\Observers\NotificationObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DocumentHeader::observe(DocumentVenteObserver::class);
        DocumentHeader::observe(DocumentAchatObserver::class);
        DocumentHeader::observe(DocumentNotificationObserver::class);

        DatabaseNotification::observe(NotificationObserver::class);
    }
}
