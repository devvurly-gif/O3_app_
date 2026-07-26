<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Set default package to BUSINESS for existing installations
        $exists = DB::table('settings')
            ->where('st_domain', 'billing')
            ->where('st_key', 'package_type')
            ->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'st_domain' => 'billing',
                'st_key' => 'package_type',
                'st_value' => 'BUSINESS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
