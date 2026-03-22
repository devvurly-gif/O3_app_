<?php

namespace App\Repositories\Eloquent;

use App\Models\PosTerminal;
use App\Repositories\Contracts\PosTerminalRepositoryInterface;

class PosTerminalRepository extends BaseRepository implements PosTerminalRepositoryInterface
{
    public function __construct(PosTerminal $model)
    {
        parent::__construct($model);
    }
}
