<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            // ── System Administrator ─────────────────────────────────────────
            'sysadmin@containeryard.com' => [
                'title'             => 'Mr',
                'first_name'        => 'System',
                'last_name'         => 'Administrator',
                'gender'            => 'male',
                'date_of_birth'     => '1980-06-01',
                'national_id'       => '198006015678',
                'employee_reg_no'   => 'SYS-0001',
                'department'        => 'IT & Systems',
                'joined_date'       => '2019-01-01',
                'emergency_contact' => 'IT Support Desk',
                'emergency_phone'   => '+94 11 000 0000',
            ],

            // ── Administrator ────────────────────────────────────────────────
            'admin@containeryard.com' => [
                'title'             => 'Mr',
                'first_name'        => 'Roshan',
                'last_name'         => 'Perera',
                'gender'            => 'male',
                'date_of_birth'     => '1985-03-15',
                'national_id'       => '198503157823',
                'employee_reg_no'   => 'EMP-0001',
                'department'        => 'Administration',
                'joined_date'       => '2020-01-15',
                'emergency_contact' => 'Nilmini Perera',
                'emergency_phone'   => '+94 71 123 4567',
            ],

            // ── Yard Supervisor ──────────────────────────────────────────────
            'supervisor@containeryard.com' => [
                'title'             => 'Mr',
                'first_name'        => 'Sunil',
                'last_name'         => 'Fernando',
                'gender'            => 'male',
                'date_of_birth'     => '1988-07-22',
                'national_id'       => '198807225612',
                'employee_reg_no'   => 'EMP-0002',
                'department'        => 'Operations',
                'joined_date'       => '2020-03-01',
                'emergency_contact' => 'Kamala Fernando',
                'emergency_phone'   => '+94 71 234 5678',
            ],

            // ── Gate Officer ─────────────────────────────────────────────────
            'gate@containeryard.com' => [
                'title'             => 'Mr',
                'first_name'        => 'Kasun',
                'last_name'         => 'Silva',
                'gender'            => 'male',
                'date_of_birth'     => '1992-11-05',
                'national_id'       => '199211056734',
                'employee_reg_no'   => 'EMP-0003',
                'department'        => 'Gate Operations',
                'joined_date'       => '2021-06-15',
                'emergency_contact' => 'Dilani Silva',
                'emergency_phone'   => '+94 71 345 6789',
            ],

            // ── Inspector ────────────────────────────────────────────────────
            'inspector@containeryard.com' => [
                'title'             => 'Ms',
                'first_name'        => 'Priyanka',
                'last_name'         => 'Jayasinghe',
                'gender'            => 'female',
                'date_of_birth'     => '1990-04-18',
                'national_id'       => '199004182398',
                'employee_reg_no'   => 'EMP-0004',
                'department'        => 'Inspection & Survey',
                'joined_date'       => '2021-02-01',
                'emergency_contact' => 'Nimal Jayasinghe',
                'emergency_phone'   => '+94 71 456 7890',
            ],

            // ── Billing Clerk ────────────────────────────────────────────────
            'billing@containeryard.com' => [
                'title'             => 'Ms',
                'first_name'        => 'Dilrukshi',
                'last_name'         => 'Wickramasinghe',
                'gender'            => 'female',
                'date_of_birth'     => '1993-09-30',
                'national_id'       => '199309304521',
                'employee_reg_no'   => 'EMP-0005',
                'department'        => 'Finance & Billing',
                'joined_date'       => '2022-01-10',
                'emergency_contact' => 'Chamara Wickramasinghe',
                'emergency_phone'   => '+94 71 567 8901',
            ],
        ];

        foreach ($profiles as $email => $data) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->command->warn("  –  User [{$email}] not found — skipped.");
                continue;
            }

            // Sync name field with first + last name
            $data['name'] = trim($data['first_name'] . ' ' . $data['last_name']);

            $user->update($data);

            $this->command->info("  ✔  Profile updated: {$data['name']} ({$email})");
        }

        $this->command->info('');
        $this->command->info('User profile seeding completed successfully.');
    }
}
