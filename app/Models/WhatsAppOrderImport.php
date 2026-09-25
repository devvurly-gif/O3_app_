<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal des imports de commandes WhatsApp (un enregistrement par ID registre, ex. CMD-2026-0001). */
class WhatsAppOrderImport extends Model
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
