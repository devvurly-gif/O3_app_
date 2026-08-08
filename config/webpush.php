<?php

return [

    /**
     * These are the keys for authentication (VAPID).
     * These keys must be safely stored and should not change.
     */
    'vapid' => [
        // Push services require a contact point for the application server.
        // Falls back to APP_URL so a fresh install is never rejected for a
        // missing subject.
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /**
     * This is model that will be used to for push subscriptions.
     */
    'model' => \NotificationChannels\WebPush\PushSubscription::class,

    /**
     * This is the name of the table that will be created by the migration and
     * used by the PushSubscription model shipped with this package.
     */
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /**
     * Database connection for push_subscriptions.
     *
     * MUST stay null in this multi-tenant app. PushSubscription::__construct
     * calls setConnection() with this value, so naming a connection here pins
     * the model to it — the package default, env('DB_CONNECTION'), is the
     * *central* database. Every tenant's subscriptions would then pile into
     * one central table while the tenant migration that actually creates
     * push_subscriptions sits unused, and a push meant for tenant A could be
     * resolved for tenant B.
     *
     * Null lets the model follow the default connection, which stancl/tenancy
     * repoints to the current tenant's database on every request.
     */
    'database_connection' => env('WEBPUSH_DB_CONNECTION') ?: null,

    /**
     * The Guzzle client options used by Minishlink\WebPush.
     */
    'client_options' => [],

    /**
     * Google Cloud Messaging.
     *
     * @deprecated
     */
    'gcm' => [
        'key' => env('GCM_KEY'),
        'sender_id' => env('GCM_SENDER_ID'),
    ],

];
