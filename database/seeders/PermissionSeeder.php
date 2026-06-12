<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PermissionSeeder extends Seeder
{
    private array $actionLabels = [
        'view'             => 'View',
        'create'           => 'Create',
        'edit'             => 'Edit',
        'delete'           => 'Delete',
        'approve'          => 'Approve',
        'reject'           => 'Reject',
        'pdf'              => 'Generate PDF',
        'email'            => 'Send by Email',
        'toggle'           => 'Activate / Deactivate',
        'gate-in'          => 'Record Gate In',
        'gate-out'         => 'Record Gate Out',
        'plug-in'          => 'Record Plug-In',
        'plug-out'         => 'Record Plug-Out',
        'temp-log'         => 'Record Temperature Log',
        'movement-edit'    => 'Edit Movement',
        'movement-delete'  => 'Delete Movement',
    ];

    public function run(): void
    {
        $modules = config('modules', []);
        $created = 0;

        foreach ($modules as $moduleKey => $moduleConfig) {
            $label = $moduleConfig['label'];

            foreach ($moduleConfig['actions'] as $sortIdx => $action) {
                $permName    = "{$moduleKey}.{$action}";
                $actionLabel = $this->actionLabels[$action]
                    ?? ucwords(str_replace('-', ' ', $action));

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
