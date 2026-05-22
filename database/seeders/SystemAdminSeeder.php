<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = 'sysadmin@containeryard.com';
        $password = 'SysAdmin@2024!';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'System Admin',
                'password' => Hash::make($password),
                'phone'    => null,
                'role'     => 'system_administrator',
                'status'   => 'active',
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info('');
            $this->command->info('  ✔  System Administrator account created.');
            $this->command->info('     Email    : ' . $email);
            $this->command->info('     Password : ' . $password);
            $this->command->warn('     ⚠  Change this password immediately after first login.');
            $this->command->info('');
        } else {
            $this->command->info('  –  System Administrator account already exists — skipped.');
        }
    }
}
