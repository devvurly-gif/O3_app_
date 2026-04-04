<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageGalleryController extends Controller
{
    public function products(): JsonResponse
    {
        $files = Storage::disk('public')->files('products');

        $images = collect($files)->map(fn (string $path) => [
            'name' => basename($path),
            'url'  => '/tenancy/assets/' . $path,
            'size' => Storage::disk('public')->size($path),
        ])->values();

        return response()->json($images);
    }

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'files'      => ['required', 'array', 'min:1'],
            'files.*'    => ['required', 'string'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $hasImages = $product->images()->count() > 0;
        $created = [];

        foreach ($validated['files'] as $i => $filename) {
            $path = 'products/' . $filename;

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            $isPrimary = !$hasImages && $i === 0;

            $created[] = $product->images()->create([
                'url'        => '/tenancy/assets/' . $path,
                'title'      => pathinfo($filename, PATHINFO_FILENAME),
                'altContent' => null,
                'isPrimary'  => $isPrimary,
            ]);
        }

        return response()->json([
            'message' => count($created) . ' image(s) affectée(s) au produit.',
            'images'  => $created,
        ]);
    }
}
