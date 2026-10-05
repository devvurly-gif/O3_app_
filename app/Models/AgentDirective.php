<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une règle de la maison (« marge minimale 20 % ») lue par les agents recrutés et par les routines. */
class AgentDirective extends Model
{
    protected $fillable = ['body', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
