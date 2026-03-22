<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentIncrementor;
use App\Repositories\Contracts\DocumentIncrementorRepositoryInterface;
use App\Services\DocumentIncrementorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentIncrementorController extends Controller
{
    public function __construct(
        private DocumentIncrementorRepositoryInterface $incrementors,
        private DocumentIncrementorService $incrementorService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->incrementors->all(orderBy: 'di_title'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'di_title'     => ['required', 'string', 'max:255'],
            'di_model'     => ['nullable', 'string', 'max:255'],
            'di_domain'    => ['nullable', 'string', 'max:100'],
            'template'     => ['nullable', 'string'],
            'nextTrick'    => ['nullable', 'integer'],
            'status'       => ['boolean'],
            'operatorSens' => ['nullable', 'string', 'max:10'],
        ]);

        $incrementor = $this->incrementors->create($data);

        return response()->json($incrementor, 201);
    }

    public function show(DocumentIncrementor $documentIncrementor): JsonResponse
    {
        return response()->json($documentIncrementor);
    }

    public function update(Request $request, DocumentIncrementor $documentIncrementor): JsonResponse
    {
        $data = $request->validate([
            'di_title'     => ['sometimes', 'string', 'max:255'],
            'di_model'     => ['nullable', 'string', 'max:255'],
            'di_domain'    => ['nullable', 'string', 'max:100'],
            'template'     => ['nullable', 'string'],
            'nextTrick'    => ['nullable', 'integer'],
            'status'       => ['sometimes', 'boolean'],
            'operatorSens' => ['nullable', 'string', 'max:10'],
        ]);

        $this->incrementors->update($documentIncrementor, $data);

        return response()->json($documentIncrementor);
    }

    public function destroy(DocumentIncrementor $documentIncrementor): JsonResponse
    {
        $this->incrementors->delete($documentIncrementor);

        return response()->json(null, 204);
    }

    public function reserveNext(DocumentIncrementor $documentIncrementor): JsonResponse
    {
        $result = $this->incrementorService->reserveNext($documentIncrementor);

        return response()->json($result);
    }

    public function confirmNext(Request $request, DocumentIncrementor $documentIncrementor): JsonResponse
    {
        $result = $this->incrementorService->confirmNext($documentIncrementor, $request->input('token'));

        if ($result === false) {
            return response()->json(['message' => 'Invalid or expired reservation token.'], 422);
        }

        return response()->json($result);
    }
}
