<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StructureIncrementor;
use App\Repositories\Contracts\StructureIncrementorRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructureIncrementorController extends Controller
{
    public function __construct(private StructureIncrementorRepositoryInterface $incrementors)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->incrementors->all(orderBy: 'si_title'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'si_title'     => ['required', 'string', 'max:255'],
            'si_model'     => ['nullable', 'string', 'max:255'],
            'si_template'  => ['nullable', 'string'],
            'si_nextTrick' => ['nullable', 'integer'],
            'si_status'    => ['boolean'],
        ]);

        $structure = $this->incrementors->create($data);

        return response()->json($structure, 201);
    }

    public function show(StructureIncrementor $structureIncrementor): JsonResponse
    {
        return response()->json($structureIncrementor);
    }

    public function update(Request $request, StructureIncrementor $structureIncrementor): JsonResponse
    {
        $data = $request->validate([
            'si_title'     => ['sometimes', 'string', 'max:255'],
            'si_model'     => ['nullable', 'string', 'max:255'],
            'si_template'  => ['nullable', 'string'],
            'si_nextTrick' => ['nullable', 'integer'],
            'si_status'    => ['sometimes', 'boolean'],
        ]);

        $this->incrementors->update($structureIncrementor, $data);

        return response()->json($structureIncrementor);
    }

    public function destroy(StructureIncrementor $structureIncrementor): JsonResponse
    {
        $this->incrementors->delete($structureIncrementor);

        return response()->json(null, 204);
    }
}
