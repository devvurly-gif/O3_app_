<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->roleService->list());
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['name' => strtolower($request->input('name', ''))]);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z][a-z0-9_]*$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:255'],
            'permissions'  => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->create($data);

        return response()->json($role, 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($this->roleService->show($role));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($request->has('name')) {
            $request->merge(['name' => strtolower($request->input('name'))]);
        }

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role->id), 'regex:/^[a-z][a-z0-9_]*$/'],
            'display_name' => ['sometimes', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:255'],
            'permissions'  => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->update($role, $data);

        return response()->json($role);
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->delete($role);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }
}
