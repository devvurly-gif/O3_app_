<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Repositories\Contracts\DocumentLigneRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentLigneController extends Controller
{
    public function __construct(private DocumentLigneRepositoryInterface $lignes)
    {
    }

    public function index(DocumentHeader $documentHeader): JsonResponse
    {
        return response()->json(
            $this->lignes->allForDocument($documentHeader, with: ['product'])
        );
    }

    public function store(Request $request, DocumentHeader $documentHeader): JsonResponse
    {
        $data = $request->validate([
            'product_id'       => ['nullable', 'integer', 'exists:products,id'],
            'sort_order'       => ['nullable', 'integer'],
            'line_type'        => ['nullable', 'string', 'max:50'],
            'designation'      => ['required', 'string', 'max:255'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'quantity'         => ['required', 'numeric', 'min:0'],
            'unit'             => ['nullable', 'string', 'max:50'],
            'unit_price'       => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent'      => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string'],
        ]);

        $ligne = $this->lignes->createForDocument($documentHeader, $data);

        return response()->json($ligne->load('product'), 201);
    }

    public function update(Request $request, DocumentHeader $documentHeader, DocumentLigne $documentLigne): JsonResponse
    {
        $data = $request->validate([
            'sort_order'       => ['nullable', 'integer'],
            'designation'      => ['sometimes', 'string', 'max:255'],
            'quantity'         => ['sometimes', 'numeric', 'min:0'],
            'unit'             => ['nullable', 'string', 'max:50'],
            'unit_price'       => ['sometimes', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent'      => ['nullable', 'numeric', 'min:0'],
            'status'           => ['nullable', 'string'],
        ]);

        $this->lignes->update($documentLigne, $data);

        return response()->json($documentLigne->load('product'));
    }

    public function destroy(DocumentHeader $documentHeader, DocumentLigne $documentLigne): JsonResponse
    {
        $this->lignes->delete($documentLigne);

        return response()->json(null, 204);
    }
}
