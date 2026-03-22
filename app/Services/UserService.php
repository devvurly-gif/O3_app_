<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users->all(orderBy: 'name');
    }

    public function create(array $data): User
    {
        /** @var User $user */
        $user = $this->users->create($data);
        return $user;
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        $this->users->update($user, $data);
        return $user->fresh();
    }

    /**
     * @throws \Exception
     */
    public function delete(User $user, int $currentUserId): void
    {
        if ($user->id === $currentUserId) {
            throw new \LogicException('Cannot delete your own account.');
        }

        $this->users->delete($user);
    }
}
