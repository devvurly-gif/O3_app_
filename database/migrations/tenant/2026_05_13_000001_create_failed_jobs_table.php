<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `failed_jobs` table to tenant databases.
 *
 * Without this table, failed queue jobs dispatched within a tenant
 * context (e.g. OrderConfirmation notifications) are logged only in
 * the central DB — making per-tenant debugging impossible.
 *
 * Schema mirrors the standard Laravel failed_jobs table created by
 * `2019_08_19_000000_create_failed_jobs_table` on the central DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('failed_jobs')) {
            return;
        }

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
