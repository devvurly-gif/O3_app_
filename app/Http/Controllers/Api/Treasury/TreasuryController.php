<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Http\Controllers\Controller;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(private TreasuryService $treasury)
    {
    }

    /** Synthèse de la période : entrées, sorties, net, soldes, postes. */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->treasury->summary($request->query('from'), $request->query('to'))
        );
    }

    /** Journal unifié : écritures manuelles + règlements documents. */
    public function journal(Request $request): JsonResponse
    {
        $request->validate([
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date'],
            'direction' => ['nullable', 'in:in,out'],
            'source'    => ['nullable', 'in:all,manual,payment'],
        ]);

        $filters = $request->only(['from', 'to', 'direction', 'account_id', 'category_id', 'source', 'search']);

        return response()->json(
            $this->treasury->journal($filters, (int) $request->input('per_page', 25))
        );
    }
}
