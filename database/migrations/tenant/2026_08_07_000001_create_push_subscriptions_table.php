<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser push subscriptions for tenant staff.
 *
 * Lives in the tenant migrations because `users` and `notifications` do:
 * a subscription belongs to one user of one tenant database, and the
 * webpush channel resolves it through that same connection.
 *
 * Schema matches laravel-notification-channels/webpush so the package's
 * PushSubscription model works unmodified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable');

            // A browser endpoint is a long URL. Capped at 500 rather than the
            // default 255 because FCM endpoints routinely exceed 255 chars,
            // and unique() on utf8mb4 needs the shorter index prefix below.
            $table->string('endpoint', 500)->unique();

            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
