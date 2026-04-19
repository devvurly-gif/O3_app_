<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('modules');
    }

    public function down(): void
    {
        // Cannot rollback - modules are now managed from central database
    }
};
