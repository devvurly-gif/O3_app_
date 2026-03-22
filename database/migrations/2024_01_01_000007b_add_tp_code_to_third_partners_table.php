<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->string('tp_code')->nullable()->unique()->after('tp_title');
        });
    }

    public function down(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->dropColumn('tp_code');
        });
    }
};
