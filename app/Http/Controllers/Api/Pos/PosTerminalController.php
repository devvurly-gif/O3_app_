<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosTerminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PosTerminal::with('warehouse')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'code'         => ['required', 'string', 'max:50', 'unique:pos_terminals,code'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $terminal = PosTerminal::create($data);

        return response()->json($terminal->load('warehouse'), 201);
    }

    public function show(PosTerminal $terminal): JsonResponse
    {
        return response()->json($terminal->load('warehouse'));
    }

    public function update(Request $request, PosTerminal $terminal): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:100'],
            'code'         => ['sometimes', 'string', 'max:50', 'unique:pos_terminals,code,' . $terminal->id],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $terminal->update($data);

        return response()->json($terminal->fresh('warehouse'));
    }

    public function destroy(PosTerminal $terminal): JsonResponse
    {
        // Don't allow if sessions exist
        if ($terminal->sessions()->exists()) {
            return response()->json(['message' => 'Ce terminal a des sessions, impossible de le supprimer.'], 422);
        }

        $terminal->delete();

        return response()->json(null, 204);
    }
}
