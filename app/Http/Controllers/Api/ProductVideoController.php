<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use App\Services\ProductVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVideoController extends Controller
{
    public function __construct(
        private ProductVideoRepositoryInterface $videos,
        private ProductVideoService $videoService,
    ) {
    }

    public function index(Product $product): JsonResponse
    {
        return response()->json($this->videos->allForProduct($product));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url'   => ['required', 'url', 'max:2048'],
        ]);

        $video = $this->videoService->add($product, $data['url'], $data['title'] ?? null);

        return response()->json($video, 201);
    }

    public function destroy(Product $product, ProductVideo $video): JsonResponse
    {
        $this->videoService->delete($video);

        return response()->json(null, 204);
    }
}
