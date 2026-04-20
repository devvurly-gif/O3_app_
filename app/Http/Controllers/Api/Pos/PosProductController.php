<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosProductController extends Controller
{
    public function __construct(
        private PosService $posService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $session = PosSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->with('terminal')
            ->firstOrFail();

        $products = $this->posService->searchProducts(
            $request->input('search', ''),
            $session->terminal->warehouse_id,
            $request->input('category_id') ? (int) $request->input('category_id') : null,
            $request->input('limit', 50),
            $request->input('min_price') ? (float) $request->input('min_price') : null,
            $request->input('max_price') ? (float) $request->input('max_price') : null,
        );

        return response()->json($products);
    }
}
