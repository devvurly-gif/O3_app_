<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Repositories\Contracts\ProductDocumentRepositoryInterface;
use App\Services\ProductDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDocumentController extends Controller
{
    public function __construct(
        private ProductDocumentRepositoryInterface $documents,
        private ProductDocumentService $documentService,
    ) {
    }

    public function index(Product $product): JsonResponse
    {
        return response()->json($this->documents->allForProduct($product));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'file'  => ['required', 'file', 'mimes:doc,docx,xls,xlsx,pdf', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $document = $this->documentService->upload(
            $product,
            $request->file('file'),
            $request->title,
        );

        return response()->json($document, 201);
    }

    public function destroy(Product $product, ProductDocument $document): JsonResponse
    {
        $this->documentService->delete($document);

        return response()->json(null, 204);
    }
}
