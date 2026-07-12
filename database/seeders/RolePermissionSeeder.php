<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission patterns per role.
     * Supports wildcards: 'billing.*' matches billing.reefer.view, billing.storage.create, etc.
     * Use '*' alone to grant ALL permissions.
     */
    private array $rolePermissions = [

        'administrator' => ['*'],

        'billing_manager' => [
            'billing.*',
            'customers.view',
            'containers.view',
            'masters.*.view',
            'masters.*.toggle',     // can activate/deactivate tariffs
            'reports.view',
        ],

        'billing_clerk' => [
            'billing.*.view',
            'billing.*.create',
            'billing.*.pdf',
            'customers.view',
            'containers.view',
        ],

        'yard_supervisor' => [
            'yard.*',
            'yard.reefer.*',
            'surveys.*',
            'estimates.*',
            'work-orders.*',
            'approvals.*',
            'containers.*',
            'customers.view',
            'reports.view',
        ],

        'gate_officer' => [
            'yard.view',
            'yard.gate-in',
            'yard.gate-out',
            'yard.reefer.view',
            'yard.reefer.plug-in',
            'yard.reefer.plug-out',
            'yard.reefer.temp-log',
            'yard.hire.view',
            'yard.hire.create',
            'yard.cargo-transfer.view',
            'yard.cargo-transfer.create',
            'yard.cargo-transfer.complete',
            'guard-post.view',
            'guard-post.create',
            'containers.view',
            'customers.view',
        ],

        'inspector' => [
            'surveys.*',
            'estimates.*',
            'containers.view',
            'customers.view',
        ],

        'security_officer' => [
            'guard-post.*',
            'containers.view',
        ],

    ];

    public function run(): void
    {
        $allPermissions = Permission::all();

        foreach ($this->rolePermissions as $roleName => $patterns) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("  ⚠  Role '{$roleName}' not found — skipped.");
                continue;
            }

            $ids = $this->resolvePermissionIds($allPermissions, $patterns);
            $role->permissions()->sync($ids);

            $this->command->info("  ✔  {$role->display_name} — assigned " . count($ids) . " permissions.");
        }
    }

    private function resolvePermissionIds($allPermissions, array $patterns): array
    {
        if (in_array('*', $patterns)) {
            return $allPermissions->pluck('id')->toArray();
        }

        $matched = collect();

        foreach ($patterns as $pattern) {
            // Convert glob-style pattern to regex
            // 'billing.*'        → /^billing\..*$/
            // 'masters.*.view'   → /^masters\..*\.view$/
            $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/';

            $matched = $matched->merge(
                $allPermissions->filter(fn($p) => preg_match($regex, $p->name))->pluck('id')
            );
        }

        return $matched->unique()->values()->toArray();
    }
}
