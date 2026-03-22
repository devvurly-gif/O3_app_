<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE document_headers MODIFY COLUMN status ENUM(
            'draft',
            'confirmed',
            'sent',
            'delivered',
            'received',
            'pending',
            'paid',
            'partial',
            'cancelled',
            'converted'
        ) DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE document_headers MODIFY COLUMN status ENUM(
            'draft',
            'confirmed',
            'sent',
            'paid',
            'partial',
            'cancelled',
            'converted'
        ) DEFAULT 'draft'");
    }
};
