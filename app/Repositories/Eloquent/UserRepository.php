<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->model->where('email', $email)->first();
    }

    public function revokeApiTokens(User $user, string $tokenName): void
    {
        $user->tokens()->where('name', $tokenName)->delete();
    }

    public function createToken(User $user, string $tokenName): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }
}
