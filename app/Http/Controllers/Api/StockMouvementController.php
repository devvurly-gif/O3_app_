<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMouvement;
use App\Repositories\Contracts\StockMouvementRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMouvementController extends Controller
{
    public function __construct(private StockMouvementRepositoryInterface $mouvements)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockMouvement::with(['product', 'warehouse', 'user'])
            ->where('status', 'applied');

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }
        if ($request->filled('product_search')) {
            $search = $request->product_search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('p_title', 'like', "%{$search}%")
                  ->orWhere('p_sku', 'like', "%{$search}%")
                  ->orWhere('p_ean13', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('order', 'desc')
            )->paginate((int) $request->input('per_page', 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'           => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'         => ['required', 'integer', 'exists:warehouses,id'],
            'document_header_id'   => ['nullable', 'integer', 'exists:document_headers,id'],
            'document_reference'   => ['nullable', 'string', 'max:100'],
            'document_type'        => ['nullable', 'string', 'max:50'],
            'direction'            => ['required', 'in:in,out'],
            'reason'               => ['nullable', 'string', 'max:255'],
            'quantity'             => ['required', 'numeric', 'min:0.01'],
            'unit_cost'            => ['nullable', 'numeric', 'min:0'],
            'stock_before'         => ['nullable', 'numeric'],
            'stock_after'          => ['nullable', 'numeric'],
            'notes'                => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;

        $mouvement = $this->mouvements->create($data);
        CacheService::flushProducts();

        return response()->json($mouvement->load(['product', 'warehouse', 'user']), 201);
    }

    public function show(StockMouvement $stockMouvement): JsonResponse
    {
        return response()->json(
            $stockMouvement->load(['product', 'warehouse', 'documentHeader', 'user'])
        );
    }
}
