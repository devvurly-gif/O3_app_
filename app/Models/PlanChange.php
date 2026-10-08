<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Historique des modifications du catalogue : qui, quand, avant et après, et combien de clients sont concernés.
 *
 * @property array<string, mixed> $before
 * @property array<string, mixed> $after
 */
class PlanChange extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'plan_changes';

    protected $fillable = ['kind', 'item_key', 'action', 'user_id', 'user_name', 'before', 'after', 'tenants_concerned'];

    protected $casts = ['before' => 'array', 'after' => 'array'];
}
