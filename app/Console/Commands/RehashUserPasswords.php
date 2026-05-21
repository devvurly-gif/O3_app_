<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RehashUserPasswords extends Command
{
    protected $signature = 'users:rehash-passwords {--only-plain-text : Only rehash passwords that are clearly plain text}';
    protected $description = 'Rehash all plain-text passwords in the database that were not properly hashed';

    public function handle(): int
    {
        $users = User::all();
        $rehashed = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // Bcrypt hashes always start with $2y$ (the algorithm identifier)
            $isAlreadyHashed = str_starts_with($user->password, '$2y$');

            if ($isAlreadyHashed) {
                $this->line("✓ {$user->email}: already hashed");
                $skipped++;
            } else {
                $this->warn("→ {$user->email}: rehashing plain-text password");

                // Use DB to directly update the password field with proper hashing
                $hashedPassword = Hash::make($user->password);
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => $hashedPassword]);

                $rehashed++;
            }
        }

        $this->info("");
        $this->info("═══════════════════════════════════");
        $this->info("Rehashed: $rehashed passwords");
        $this->info("Skipped:  $skipped (already hashed)");
        $this->info("═══════════════════════════════════");

        return Command::SUCCESS;
    }
}
