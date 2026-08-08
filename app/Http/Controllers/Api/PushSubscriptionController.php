<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers the browser push subscriptions of the logged-in user.
 *
 * One row per browser, not per user: the owner may want alerts on their
 * phone and on the shop counter machine at once, and each has its own
 * endpoint. Re-subscribing from the same browser updates in place because
 * `endpoint` is unique.
 */
class PushSubscriptionController extends Controller
{
    /**
     * POST /api/push-subscriptions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Push service URLs are long (FCM in particular) — the column
            // holds 500, so reject above that rather than truncate into a
            // silently unreachable endpoint.
            'endpoint'         => ['required', 'string', 'max:500'],
            'keys.p256dh'      => ['required', 'string'],
            'keys.auth'        => ['required', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aesgcm',
        );

        return response()->json(['message' => 'Notifications activées sur cet appareil.'], 201);
    }

    /**
     * DELETE /api/push-subscriptions
     *
     * Called when the user turns alerts off. Only removes the subscription if
     * it belongs to them — deletePushSubscription scopes to the current user,
     * so a stale endpoint from another account cannot be dropped by guessing.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Notifications désactivées sur cet appareil.']);
    }

    /**
     * GET /api/push-subscriptions/vapid-key
     *
     * The browser needs the VAPID *public* key to build a subscription. It is
     * public by design — it identifies this server to the push service and is
     * useless without the private half.
     */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'key'     => config('webpush.vapid.public_key'),
            'enabled' => (bool) config('webpush.vapid.public_key'),
        ]);
    }
}
