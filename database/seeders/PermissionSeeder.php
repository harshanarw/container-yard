<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = config('modules', []);
        $created = 0;

        foreach ($modules as $moduleKey => $moduleConfig) {
            $label = $moduleConfig['label'];

            foreach ($moduleConfig['actions'] as $sortIdx => $action) {
                $permName    = "{$moduleKey}.{$action}";
                $actionLabel = Permission::actionLabel($action);

                $perm = Permission::firstOrCreate(
                    ['name' => $permName],
                    [
                        'module'       => $moduleKey,
                        'action'       => $action,
                        'display_name' => "{$actionLabel} — {$label}",
                        'sort_order'   => $sortIdx,
                    ]
                );

                if ($perm->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        Cache::forget('_gate_permission_names');

        $total = Permission::count();
        $this->command->info("  ✔  Permissions synced — {$created} created, {$total} total.");
    }
}
