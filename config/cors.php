<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // SECURITY: whitelist instead of `['*']`. APP_URL covers local
    // Laragon (http://o3app.test) and the central VPS, the regex
    // covers every tenant subdomain on o3app.ma (https only). If a
    // new origin is ever needed (e.g. a partner domain) add it here
    // explicitly — never revert to `['*']` when `supports_credentials`
    // is off, it still leaks response bodies to malicious pages.
    'allowed_origins' => array_values(array_filter([
        env('APP_URL'),
        env('FRONTEND_URL'),
    ])),

    // Match any number of subdomain levels under o3app.ma:
    //   tenant.o3app.ma         → app principal du tenant
    //   shop.tenant.o3app.ma    → boutique e-com du tenant
    //   admin.tenant.o3app.ma   → futurs sous-portails
    // The previous single-level pattern silently broke the storefront.
    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)+o3app\.ma$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
