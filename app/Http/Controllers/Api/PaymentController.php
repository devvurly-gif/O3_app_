<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentRepositoryInterface $payments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->payments->paginate(
                perPage: (int) $request->input('per_page', 15),
                with: ['document', 'user'],
                orderBy: $request->input('sort', 'paid_at'),
                direction: $request->input('order', 'desc'),
                filters: array_filter([
                    'method' => $request->method,
                ])
            )
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $payment = $this->payments->create($data);
        CacheService::flushDocuments();

        return response()->json($payment->load(['document', 'user']), 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['document', 'user']));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->payments->delete($payment);
        CacheService::flushDocuments();

        return response()->json(null, 204);
    }
}
