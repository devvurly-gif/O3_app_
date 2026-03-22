<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    public function index(): JsonResponse
    {
        $data = CacheService::remember(
            CacheService::categoriesKey(),
            CacheService::TTL_LONG,
            fn () => $this->categories->all(orderBy: 'ctg_title')
        );

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ctg_title'  => ['required', 'string', 'max:255'],
            'ctg_status' => ['boolean'],
        ]);

        $category = $this->categories->create($data);
        CacheService::flushCategories();

        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load('products'));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'ctg_title'  => ['sometimes', 'string', 'max:255'],
            'ctg_status' => ['sometimes', 'boolean'],
        ]);

        $this->categories->update($category, $data);
        CacheService::flushCategories();

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);
        CacheService::flushCategories();

        return response()->json(null, 204);
    }
}
