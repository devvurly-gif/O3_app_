<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $repository,
    ) {
    }

    public function list(): Collection
    {
        return $this->repository->all(
            with: ['permissions'],
            orderBy: 'name',
        )->loadCount('users');
    }

    public function show(Role $role): Role
    {
        return $role->load('permissions');
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            /** @var Role $role */
            $role = $this->repository->create([
                'name'         => $data['name'],
                'display_name' => $data['display_name'],
                'description'  => $data['description'] ?? null,
                'is_system'    => false,
            ]);

            if (!empty($data['permissions'])) {
                $this->repository->syncPermissions($role, $data['permissions']);
            }

            return $role->load('permissions');
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $updateData = [];

            // System roles cannot have their name changed
            if (!$role->is_system && isset($data['display_name'])) {
                $updateData['display_name'] = $data['display_name'];
            } elseif (isset($data['display_name'])) {
                $updateData['display_name'] = $data['display_name'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            // System roles cannot have their slug name changed
            if (!$role->is_system && isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (!empty($updateData)) {
                $this->repository->update($role, $updateData);
            }

            if (array_key_exists('permissions', $data)) {
                $this->repository->syncPermissions($role, $data['permissions'] ?? []);
            }

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new \RuntimeException('Les rôles système ne peuvent pas être supprimés.');
        }

        if ($role->users()->count() > 0) {
            throw new \RuntimeException('Ce rôle est encore assigné à des utilisateurs.');
        }

        $this->repository->delete($role);
    }
}
