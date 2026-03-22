<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function revokeApiTokens(User $user, string $tokenName): void;

    public function createToken(User $user, string $tokenName): string;
}
