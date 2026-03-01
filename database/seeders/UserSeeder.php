<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'System Administrator',
                'email'    => 'admin@containeryard.com',
                'password' => Hash::make('password'),
                'phone'    => '+60123456789',
                'role'     => 'administrator',
                'status'   => 'active',
            ],
            [
                'name'     => 'Yard Supervisor',
                'email'    => 'supervisor@containeryard.com',
                'password' => Hash::make('password'),
                'phone'    => '+60123456790',
                'role'     => 'yard_supervisor',
                'status'   => 'active',
            ],
            [
                'name'     => 'Gate Officer',
                'email'    => 'gate@containeryard.com',
                'password' => Hash::make('password'),
                'phone'    => '+60123456791',
                'role'     => 'gate_officer',
                'status'   => 'active',
            ],
            [
                'name'     => 'Container Inspector',
                'email'    => 'inspector@containeryard.com',
                'password' => Hash::make('password'),
                'phone'    => '+60123456792',
                'role'     => 'inspector',
                'status'   => 'active',
            ],
            [
                'name'     => 'Billing Clerk',
                'email'    => 'billing@containeryard.com',
                'password' => Hash::make('password'),
                'phone'    => '+60123456793',
                'role'     => 'billing_clerk',
                'status'   => 'active',
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }
    }
}
