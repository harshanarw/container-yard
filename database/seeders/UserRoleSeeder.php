<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles    = Role::all()->keyBy('name');
        $assigned = 0;
        $skipped  = 0;

        User::where('status', 'active')->each(function (User $user) use ($roles, &$assigned, &$skipped) {
            $roleName = $user->role;

            // system_administrator / administrator bypass RBAC via isSuperUser() — no pivot row needed
            if (!$roleName || !$roles->has($roleName)) {
                $this->command->line("  –  {$user->name} ({$roleName}) — no matching RBAC role, skipped.");
                $skipped++;
                return;
            }

            $role = $roles->get($roleName);
            $user->roles()->syncWithoutDetaching([$role->id]);
            $assigned++;

            $this->command->info("  ✔  {$user->name} → {$role->display_name}");
        });

        $this->command->info("  ✔  User role assignments complete — {$assigned} assigned, {$skipped} skipped.");
    }
}
