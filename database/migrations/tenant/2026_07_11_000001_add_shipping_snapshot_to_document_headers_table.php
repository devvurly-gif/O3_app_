<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_headers', function (Blueprint $table) {
            $table->string('ship_name')->nullable()->after('notes');
            $table->string('ship_phone')->nullable()->after('ship_name');
            $table->string('ship_address', 500)->nullable()->after('ship_phone');
            $table->string('ship_city')->nullable()->after('ship_address');
        });
    }

    public function down(): void
    {
        Schema::table('document_headers', function (Blueprint $table) {
            $table->dropColumn(['ship_name', 'ship_phone', 'ship_address', 'ship_city']);
        });
    }
};
