<?php

namespace App\Providers;

use App\Mail\Transport\ResendTransport;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\ProductImage;
use App\Models\ProductDocument;
use App\Models\Tenant;
use App\Observers\DocumentAchatObserver;
use App\Observers\DocumentHeaderObserver;
use App\Observers\DocumentNotificationObserver;
use App\Observers\DocumentVenteObserver;
use App\Observers\NotificationObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductImageObserver;
use App\Observers\ProductDocumentObserver;
use App\Observers\TenantObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register Resend mail transport (HTTP-based, no SMTP ports needed)
        Mail::extend('resend', function (array $config) {
            return new ResendTransport($config['key'] ?? config('services.resend.key'));
        });

        DocumentHeader::observe(DocumentVenteObserver::class);
        DocumentHeader::observe(DocumentAchatObserver::class);
        DocumentHeader::observe(DocumentNotificationObserver::class);
        DocumentHeader::observe(DocumentHeaderObserver::class);
        Payment::observe(PaymentObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        ProductDocument::observe(ProductDocumentObserver::class);
        DatabaseNotification::observe(NotificationObserver::class);
        Tenant::observe(TenantObserver::class);
    }
}
