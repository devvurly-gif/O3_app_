<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    /**
     * Authenticate a user by email/password credentials.
     *
     * @return array{token: string, user: array}
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email)?->load('role.permissions');

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        $this->users->revokeApiTokens($user, 'api');
        $token = $this->users->createToken($user, 'api');

        return [
            'token' => $token,
            'user'  => $this->formatProfile($user),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function getProfile(User $user): array
    {
        $user->loadMissing('role.permissions');
        return $this->formatProfile($user);
    }

    private function formatProfile(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role?->name,
            'role_id'     => $user->role_id,
            'permissions' => $user->role?->permissions->pluck('name')->toArray() ?? [],
            'avatar'         => $user->avatar,
            'active_modules' => Module::allActiveNames(),
        ];
    }
}
