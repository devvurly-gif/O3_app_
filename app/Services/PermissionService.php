<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    public function __construct(
        private PermissionRepositoryInterface $repository,
    ) {
    }

    public function list(): Collection
    {
        return $this->repository->all(orderBy: 'module');
    }

    public function listGroupedByModule(): array
    {
        return $this->repository->allGroupedByModule();
    }
}
