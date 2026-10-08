<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ce que le super-administrateur a modifié dans une formule ou une option (voir PlanCatalog). Table CENTRALE : lue aussi
 * depuis le contexte d'un tenant, d'où la connexion centrale explicite.
 *
 * @property string $kind     plan | addon
 * @property string $item_key
 * @property array<string, mixed> $data
 */
class PlanOverride extends Model
{
    use CentralConnection;

    protected $table = 'plan_overrides';

    protected $fillable = ['kind', 'item_key', 'data', 'updated_by'];

    protected $casts = ['data' => 'array'];
}
