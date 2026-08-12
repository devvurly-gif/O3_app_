<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Widget keys this user chose to hide. Storing what is *hidden*
            // (rather than what is shown) means any widget added later shows
            // up for everyone by default instead of silently disappearing.
            $table->json('dashboard_hidden_widgets')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_hidden_widgets');
        });
    }
};
