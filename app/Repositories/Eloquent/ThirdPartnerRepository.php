<?php

namespace App\Repositories\Eloquent;

use App\Models\ThirdPartner;
use App\Repositories\Contracts\ThirdPartnerRepositoryInterface;

class ThirdPartnerRepository extends BaseRepository implements ThirdPartnerRepositoryInterface
{
    public function __construct(ThirdPartner $model)
    {
        parent::__construct($model);
    }
}
