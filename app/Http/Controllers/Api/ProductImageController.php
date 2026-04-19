<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(
        private ProductImageRepositoryInterface $images,
        private ProductImageService $imageService,
    ) {
    }

    public function index(Product $product): JsonResponse
    {
        return response()->json($this->images->allForProduct($product));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'images'     => ['required_without:image', 'array', 'min:1'],
            'images.*'   => ['image', 'max:2048'],
            'image'      => ['required_without:images', 'image', 'max:2048'],
            'title'      => ['nullable', 'string', 'max:255'],
            'altContent' => ['nullable', 'string', 'max:255'],
            'isPrimary'  => ['boolean'],
        ]);

        // Support both single and multiple images (backwards compatible)
        if ($request->hasFile('images')) {
            $images = $this->imageService->uploadMultiple(
                $product,
                $request->file('images'),
                $request->title,
                $request->altContent,
                $request->boolean('isPrimary'),
            );
            return response()->json($images, 201);
        }

        // Single image upload (legacy)
        $image = $this->imageService->upload(
            $product,
            $request->file('image'),
            $request->title,
            $request->altContent,
            $request->boolean('isPrimary'),
        );

        return response()->json($image, 201);
    }

    public function setPrimary(Product $product, ProductImage $image): JsonResponse
    {
        $image = $this->imageService->setPrimary($product, $image);

        return response()->json($image);
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        $this->imageService->delete($image);

        return response()->json(null, 204);
    }
}
