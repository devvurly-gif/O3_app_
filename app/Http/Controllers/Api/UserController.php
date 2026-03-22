<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->userService->list());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role_id'   => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        $user = $this->userService->create($data);

        return response()->json($user->makeHidden(['password']), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->makeHidden(['password']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_id'   => ['sometimes', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->userService->update($user, $data);

        return response()->json($updated->makeHidden(['password']));
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            $this->userService->delete($user, auth()->id());
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }
}
