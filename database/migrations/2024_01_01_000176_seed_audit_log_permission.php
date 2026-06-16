<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'audit-log.view'],
            [
                'module'       => 'audit-log',
                'action'       => 'view',
                'display_name' => 'View — Audit Log',
                'sort_order'   => 0,
            ]
        );

        Cache::forget('_gate_permission_names');
    }

    public function down(): void
    {
        Permission::where('module', 'audit-log')->delete();
        Cache::forget('_gate_permission_names');
    }
};
