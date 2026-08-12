<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-user dashboard layout: which widgets the signed-in user chose to hide.
 *
 * Deliberately kept out of the /dashboard payload — that response is cached
 * per tenant and shared by every user, so a per-user preference has no
 * business in it.
 */
class DashboardWidgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'hidden' => $request->user()->dashboard_hidden_widgets ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden'   => ['present', 'array', 'max:100'],
            'hidden.*' => ['string', 'max:64'],
        ]);

        $user = $request->user();
        $user->dashboard_hidden_widgets = array_values(array_unique($data['hidden']));
        $user->save();

        return response()->json(['hidden' => $user->dashboard_hidden_widgets]);
    }
}
