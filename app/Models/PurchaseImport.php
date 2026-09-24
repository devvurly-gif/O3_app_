<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal des imports API (un enregistrement par ID registre, ex. FA-2026-0001). */
class PurchaseImport extends Model
{
    protected $fillable = [
        'external_id', 'status', 'payload_hash', 'payload', 'response',
        'document_id', 'document_reference', 'user_id',
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
    ];
}
