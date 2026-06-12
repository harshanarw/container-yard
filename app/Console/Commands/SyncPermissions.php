<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncPermissions extends Command
{
    protected $signature   = 'permissions:sync {--dry-run : Preview changes without writing to DB}';
    protected $description = 'Sync permission records from config/modules.php to the permissions table';

    // Human-readable labels for common action slugs
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

    public function handle(): int
    {
        $modules  = config('modules', []);
        $dryRun   = $this->option('dry-run');
        $created  = 0;
        $existing = 0;
        $rows     = [];

        foreach ($modules as $moduleKey => $moduleConfig) {
            $label = $moduleConfig['label'];

            foreach ($moduleConfig['actions'] as $sortIdx => $action) {
                $permName    = "{$moduleKey}.{$action}";
                $actionLabel = $this->actionLabels[$action]
                    ?? ucwords(str_replace('-', ' ', $action));
                $displayName = "{$actionLabel} — {$label}";

                $exists = Permission::where('name', $permName)->exists();

                if ($exists) {
                    $existing++;
                    $rows[] = ['<fg=gray>exists</>', $permName, $displayName];
                } else {
                    $created++;
                    $rows[] = ['<fg=green>create</>', $permName, $displayName];

                    if (!$dryRun) {
                        Permission::create([
                            'name'         => $permName,
                            'module'       => $moduleKey,
                            'action'       => $action,
                            'display_name' => $displayName,
                            'sort_order'   => $sortIdx,
                        ]);
                    }
                }
            }
        }

        $this->table(['Status', 'Permission', 'Display Name'], $rows);

        if ($dryRun) {
            $this->warn("Dry run — no changes written.");
        } else {
            Cache::forget('_gate_permission_names');
            $this->info("Done. Created: {$created}  |  Already existed: {$existing}");
        }

        return self::SUCCESS;
    }
}
