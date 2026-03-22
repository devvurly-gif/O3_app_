<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make optional columns nullable so that documents can be created
 * without a title, a third-party, a company_role, or an issue date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_headers', function (Blueprint $table) {
            $table->string('document_title')->nullable()->change();
            $table->foreignId('thirdPartner_id')->nullable()->change();
            $table->string('company_role')->nullable()->change();
            $table->date('issued_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('document_headers')->whereNull('document_title')->update(['document_title' => '']);
        DB::table('document_headers')->whereNull('company_role')->update(['company_role' => 'customer']);
        DB::table('document_headers')->whereNull('issued_at')->update(['issued_at' => now()]);

        Schema::table('document_headers', function (Blueprint $table) {
            $table->string('document_title')->nullable(false)->change();
            $table->string('company_role')->nullable(false)->change();
            $table->date('issued_at')->nullable(false)->change();
        });
    }
};
