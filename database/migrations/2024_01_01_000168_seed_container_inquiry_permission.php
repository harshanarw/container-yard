<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name'         => 'container-inquiry.view',
            'module'       => 'container-inquiry',
            'action'       => 'view',
            'display_name' => 'View — Container Inquiry',
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'container-inquiry.view')->delete();
    }
};
