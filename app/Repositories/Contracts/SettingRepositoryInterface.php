<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    public function allByDomain(?string $domain = null): Collection;

    /**
     * Ecrit un reglage. `null` efface la valeur — c'est ce que fait la
     * suppression d'un logo, et le contrat le refusait alors que
     * `settings.st_value` est nullable en base.
     */
    public function upsert(string $domain, string $key, ?string $value): void;

    public function deleteByDomainAndKey(string $domain, string $key): void;
}
