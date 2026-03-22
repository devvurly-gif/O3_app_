<?php

namespace App\Policies;

use App\Models\ThirdPartner;
use App\Models\User;

class ThirdPartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ThirdPartner $partner): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('third_partners.create');
    }

    public function update(User $user, ThirdPartner $partner): bool
    {
        return $user->hasPermission('third_partners.update');
    }

    public function delete(User $user, ThirdPartner $partner): bool
    {
        return $user->hasPermission('third_partners.delete');
    }
}
