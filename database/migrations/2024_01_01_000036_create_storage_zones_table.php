<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('storage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default zones
        DB::table('storage_zones')->insert([
            ['code' => 'A', 'name' => 'Zone A', 'description' => 'Primary import zone', 'color' => '#3B82F6', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B', 'name' => 'Zone B', 'description' => 'Secondary storage zone', 'color' => '#10B981', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'C', 'name' => 'Zone C', 'description' => 'Export staging zone', 'color' => '#F59E0B', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'D', 'name' => 'Zone D', 'description' => 'Damaged/repair holding zone', 'color' => '#8B5CF6', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_zones');
    }
};
