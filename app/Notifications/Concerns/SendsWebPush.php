<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;

/**
 * Adds the 'webpush' channel to a notification, but only when it can work.
 *
 * Minishlink\WebPush throws in its constructor when VAPID keys are absent.
 * Inside a queued notification that exception fails the whole job — including
 * the 'database' and 'mail' channels that already succeeded — so a retry
 * re-delivers them and the user sees the same alert twice. An unconfigured
 * environment (a fresh clone, CI, a tenant restored before the keys were set)
 * would therefore break notifications that used to work.
 *
 * Advertising the channel only when the keys exist keeps push strictly
 * additive: no keys, no push, everything else untouched.
 */
trait SendsWebPush
{
    protected function webPushChannel(): array
    {
        $configured = config('webpush.vapid.public_key')
            && config('webpush.vapid.private_key');

        // The class name, not the string 'webpush'. This package's service
        // provider only wires contextual bindings — it never calls
        // ChannelManager::extend(), so there is no 'webpush' alias to resolve
        // and Laravel would throw "Driver [webpush] not supported". The
        // manager's fallback is to container-resolve a channel given by class.
        return $configured ? [WebPushChannel::class] : [];
    }

    /**
     * Absolute URL the browser should open when the notification is clicked.
     *
     * Built against the tenant's own domain rather than url(): a queued job
     * runs outside any HTTP request, where url() falls back to the central
     * APP_URL and would send the owner to the admin app instead of their own.
     */
    protected function webPushUrl(string $path): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return $tenant ? $tenant->appUrl($path) : url($path);
    }
}
