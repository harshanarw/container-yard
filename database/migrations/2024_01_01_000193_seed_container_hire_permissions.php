<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            [
                'name'         => 'yard.hire.view',
                'module'       => 'yard',
                'action'       => 'hire-view',
                'display_name' => 'View — Container Hires',
                'sort_order'   => 10,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'yard.hire.create',
                'module'       => 'yard',
                'action'       => 'hire-create',
                'display_name' => 'Create — On Hire',
                'sort_order'   => 11,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'yard.hire.off_hire',
                'module'       => 'yard',
                'action'       => 'hire-off-hire',
                'display_name' => 'Off Hire — Complete Container Hire',
                'sort_order'   => 12,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'yard.hire.cancel',
                'module'       => 'yard',
                'action'       => 'hire-cancel',
                'display_name' => 'Cancel — Container Hire',
                'sort_order'   => 13,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', [
            'yard.hire.view',
            'yard.hire.create',
            'yard.hire.off_hire',
            'yard.hire.cancel',
        ])->delete();
    }
};
