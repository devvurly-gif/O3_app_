<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Journal des imports de commandes WhatsApp (un enregistrement par ID registre, ex. CMD-2026-0001). */
class WhatsAppOrderImport extends Model
{
    // Sans ceci, Laravel déduit « whats_app_order_imports » du nom de classe
    // (WhatsApp → whats_app) alors que la migration crée whatsapp_order_imports.
    protected $table = 'whatsapp_order_imports';

    protected $fillable = [
        'external_id', 'status', 'payload_hash', 'payload', 'response',
        'document_id', 'document_reference', 'user_id',
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
    ];
}
