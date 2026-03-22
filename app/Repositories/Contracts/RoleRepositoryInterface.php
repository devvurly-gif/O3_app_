<?php

namespace App\Repositories\Contracts;

use App\Models\Role;

interface RoleRepositoryInterface extends BaseRepositoryInterface
{
    public function syncPermissions(Role $role, array $permissionIds): void;
}
