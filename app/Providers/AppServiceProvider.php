<?php

namespace App\Providers;

use App\Mail\Transport\ResendTransport;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\AgentTriggerObserver;
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
use Laravel\Sanctum\Sanctum;

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
        // Événements internes pouvant déclencher une routine de l'orchestrateur (sans effet tant qu'aucune n'écoute).
        Product::observe(AgentTriggerObserver::class);
        Payment::observe(AgentTriggerObserver::class);
        DocumentHeader::observe(AgentTriggerObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        ProductDocument::observe(ProductDocumentObserver::class);
        DatabaseNotification::observe(NotificationObserver::class);
        Tenant::observe(TenantObserver::class);

        // Tous les jetons Sanctum expirent 12 h après leur création
        // (config sanctum.expiration) : c'est voulu pour les sessions des
        // utilisateurs, mais un jeton de service (agents IA, émis par
        // achats:agent-token / ventes:agent-token) serait alors inutilisable le
        // lendemain. Un tel jeton porte sa propre date de fin (expires_at) : il
        // vaut jusqu'à cette date. Jamais pour un jeton à pleins droits (« * ») :
        // une session ne peut pas s'offrir une durée de vie plus longue.
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid): bool {
            if ($isValid) {
                return true;
            }

            return $token->expires_at !== null
                && ! $token->expires_at->isPast()
                && ! in_array('*', $token->abilities ?? [], true)
                && $token->tokenable instanceof User;
        });
    }
}
