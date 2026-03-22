<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    public function allByDomain(?string $domain = null): Collection;

    public function upsert(string $domain, string $key, string $value): void;

    public function deleteByDomainAndKey(string $domain, string $key): void;
}
