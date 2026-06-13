<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            [
                'name'         => 'settings.cloud-storage.view',
                'module'       => 'settings.cloud-storage',
                'action'       => 'view',
                'display_name' => 'View — Document Storage',
                'sort_order'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'settings.cloud-storage.edit',
                'module'       => 'settings.cloud-storage',
                'action'       => 'edit',
                'display_name' => 'Edit — Document Storage',
                'sort_order'   => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['settings.cloud-storage.view', 'settings.cloud-storage.edit'])
            ->delete();
    }
};
