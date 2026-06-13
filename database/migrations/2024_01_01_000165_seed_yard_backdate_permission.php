<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert the yard.backdate permission if it does not already exist.
        // This mirrors what `php artisan permissions:sync` would produce.
        DB::table('permissions')->insertOrIgnore([
            'name'         => 'yard.backdate',
            'module'       => 'yard',
            'action'       => 'backdate',
            'display_name' => 'Backdate — Yard Gate Operations',
            'sort_order'   => 5, // after movement-delete (index 4)
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'yard.backdate')->delete();
    }
};
