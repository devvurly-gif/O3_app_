<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VariantOptionType;
use App\Models\VariantOptionValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VariantOptionController extends Controller
{
    // GET /variant-options  — all types with their values
    public function index(): JsonResponse
    {
        $types = VariantOptionType::with('values')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    // POST /variant-options  — create a new option type
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'slug'     => 'nullable|string|max:100|unique:variant_option_types,slug',
            'position' => 'nullable|integer',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $type = VariantOptionType::create($data);
        $type->load('values');

        return response()->json($type, 201);
    }

    // PUT /variant-options/{id}  — update option type
    public function update(Request $request, int $id): JsonResponse
    {
        $type = VariantOptionType::findOrFail($id);

        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:100',
            'slug'      => 'sometimes|required|string|max:100|unique:variant_option_types,slug,' . $id,
            'position'  => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $type->update($data);
        $type->load('values');

        return response()->json($type);
    }

    // DELETE /variant-options/{id}  — delete type + cascade values
    public function destroy(int $id): JsonResponse
    {
        VariantOptionType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // POST /variant-options/{id}/values  — add a value to a type
    public function storeValue(Request $request, int $id): JsonResponse
    {
        $type = VariantOptionType::findOrFail($id);

        $data = $request->validate([
            'key'      => 'required|string|max:100',
            'value'    => 'required|string|max:255',
            'position' => 'nullable|integer',
        ]);

        $value = $type->values()->create($data);

        return response()->json($value, 201);
    }

    // PUT /variant-options/{id}/values/{valueId}  — update a value
    public function updateValue(Request $request, int $id, int $valueId): JsonResponse
    {
        $value = VariantOptionValue::where('variant_option_type_id', $id)
            ->findOrFail($valueId);

        $data = $request->validate([
            'key'      => 'sometimes|required|string|max:100',
            'value'    => 'sometimes|required|string|max:255',
            'position' => 'nullable|integer',
        ]);

        $value->update($data);

        return response()->json($value);
    }

    // DELETE /variant-options/{id}/values/{valueId}
    public function destroyValue(int $id, int $valueId): JsonResponse
    {
        VariantOptionValue::where('variant_option_type_id', $id)
            ->findOrFail($valueId)
            ->delete();

        return response()->json(null, 204);
    }
}
