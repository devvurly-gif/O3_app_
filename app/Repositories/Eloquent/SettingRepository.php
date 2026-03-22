<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Collection;

class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(protected Setting $model)
    {
    }

    public function allByDomain(?string $domain = null): Collection
    {
        return $this->model->query()
            ->when($domain, fn ($q, $d) => $q->where('st_domain', $d))
            ->get()
            ->groupBy('st_domain')
            ->map(fn ($group) => $group->pluck('st_value', 'st_key'));
    }

    public function upsert(string $domain, string $key, string $value): void
    {
        Setting::set($domain, $key, $value);
    }

    public function deleteByDomainAndKey(string $domain, string $key): void
    {
        $this->model->where('st_domain', $domain)
            ->where('st_key', $key)
            ->delete();
    }
}
