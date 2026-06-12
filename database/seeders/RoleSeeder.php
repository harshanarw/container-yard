<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'        => 'administrator',
                'display_name'=> 'Administrator',
                'description' => 'Full access to all modules. Cannot be deleted.',
                'is_system'   => true,
            ],
            [
                'name'        => 'billing_manager',
                'display_name'=> 'Billing Manager',
                'description' => 'Full access to all billing modules and read access to tariffs, customers, and containers.',
                'is_system'   => true,
            ],
            [
                'name'        => 'billing_clerk',
                'display_name'=> 'Billing Clerk',
                'description' => 'Can view and create invoices. Cannot approve, delete, or manage tariffs.',
                'is_system'   => true,
            ],
            [
                'name'        => 'yard_supervisor',
                'display_name'=> 'Yard Supervisor',
                'description' => 'Full yard operations, surveys, estimates, and work orders.',
                'is_system'   => true,
            ],
            [
                'name'        => 'gate_officer',
                'display_name'=> 'Gate Officer',
                'description' => 'Records gate-in/out movements and reefer plug events.',
                'is_system'   => true,
            ],
            [
                'name'        => 'inspector',
                'display_name'=> 'Inspector',
                'description' => 'Creates and manages surveys and repair estimates.',
                'is_system'   => true,
            ],
            [
                'name'        => 'security_officer',
                'display_name'=> 'Security Officer',
                'description' => 'Manages guard post captures.',
                'is_system'   => true,
            ],
        ];

        foreach ($roles as $data) {
            $role = Role::firstOrCreate(['name' => $data['name']], $data);
            $this->command->info("  " . ($role->wasRecentlyCreated ? '✔  Created' : '–  Exists ') . "  {$data['display_name']}");
        }
    }
}
