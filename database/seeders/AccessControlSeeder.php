<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One-shot, idempotent seeder that closes every gap in the RBAC data so roles
 * are consistent everywhere and always pickable from the system-defined list.
 *
 * Run it any time roles/permissions look out of sync:
 *     php artisan db:seed --class=AccessControlSeeder
 *
 * It is safe to run repeatedly — each step only fills what's missing:
 *   1. PermissionSeeder     — ensure every permission from config/modules.php exists
 *   2. RoleSeeder           — ensure every system-defined role row exists
 *   3. RolePermissionSeeder — (re)assign each role's permission set
 *   4. UserRoleSeeder       — link each active user's primary role into user_roles
 * Finally it reconciles any user whose primary `role` string has no matching
 * pivot row (e.g. accounts created before the roles existed).
 */
class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Synchronising roles, permissions and user assignments…');

        $this->call([
            PermissionSeeder::class,       // permissions ← config/modules.php
            RoleSeeder::class,             // system-defined roles
            RolePermissionSeeder::class,   // role → permissions
            UserRoleSeeder::class,         // user → role pivot (active users)
        ]);

        // Safety net: make sure every user's primary role string is reflected in
        // the user_roles pivot, so permissions resolve for everyone — including
        // inactive accounts the UserRoleSeeder skips.
        $roles   = Role::pluck('id', 'name');
        $linked  = 0;
        $skipped = 0;

        User::query()->each(function (User $user) use ($roles, &$linked, &$skipped) {
            $name = $user->role;

            // Super-user roles bypass RBAC and have no Role row — nothing to link.
            if (! $name || ! $roles->has($name)) {
                $skipped++;
                return;
            }

            $roleId = $roles->get($name);
            if (! $user->roles()->where('roles.id', $roleId)->exists()) {
                $user->roles()->syncWithoutDetaching([$roleId]);
                $linked++;
            }
        });

        $this->command->info("  ✔  Reconciled user role pivots — {$linked} linked, {$skipped} skipped (super-user / no matching role).");
        $this->command->info('Access control sync complete.');
    }
}
