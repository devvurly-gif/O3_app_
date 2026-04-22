<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\ThirdPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosCustomerController extends Controller
{
    /**
     * Search customers for POS (lightweight fields only).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search', '');

        $query = ThirdPartner::whereIn('tp_Role', ['customer', 'both'])
            ->select(['id', 'tp_title', 'tp_phone', 'tp_email', 'type_compte', 'encours_actuel', 'seuil_credit', 'price_list_id']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tp_title', 'like', "%{$search}%")
                  ->orWhere('tp_phone', 'like', "%{$search}%")
                  ->orWhere('tp_email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('tp_title')
            ->limit(20)
            ->get();

        return response()->json($customers);
    }

    /**
     * Quick-create a customer from POS.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tp_title'     => 'required|string|max:255',
            'tp_phone'     => 'nullable|string|max:50',
            'tp_email'     => 'nullable|email|max:255',
            'type_compte'  => 'nullable|in:normal,en_compte',
            'seuil_credit' => 'nullable|numeric|min:0',
        ]);

        $customer = ThirdPartner::create([
            'tp_title'     => $validated['tp_title'],
            'tp_phone'     => $validated['tp_phone'] ?? null,
            'tp_email'     => $validated['tp_email'] ?? null,
            'tp_Role'      => 'customer',
            'type_compte'  => $validated['type_compte'] ?? 'normal',
            'seuil_credit' => $validated['seuil_credit'] ?? 0,
            'encours_actuel' => 0,
        ]);

        return response()->json($customer->only([
            'id', 'tp_title', 'tp_phone', 'tp_email', 'type_compte', 'encours_actuel', 'seuil_credit', 'price_list_id',
        ]), 201);
    }
}
