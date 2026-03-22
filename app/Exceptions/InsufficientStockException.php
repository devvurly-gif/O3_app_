<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int    $productId,
        public readonly float  $requested,
        public readonly float  $available,
        public readonly ?int   $warehouseId = null,
    ) {
        parent::__construct(
            "Stock insuffisant pour « {$productName} » : demandé {$requested}, disponible {$available}."
        );
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message'    => $this->getMessage(),
            'product_id' => $this->productId,
            'requested'  => $this->requested,
            'available'  => $this->available,
        ], 422);
    }
}
