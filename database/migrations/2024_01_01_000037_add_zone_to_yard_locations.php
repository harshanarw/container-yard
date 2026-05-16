<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('yard_locations', function (Blueprint $table) {
            $table->string('zone', 10)->default('A')->after('id');
        });

        // Assign existing locations to zone A
        DB::table('yard_locations')->update(['zone' => 'A']);

        Schema::table('yard_locations', function (Blueprint $table) {
            // Drop old unique on (row, bay, tier), add new on (zone, row, bay, tier)
            $table->dropUnique(['row', 'bay', 'tier']);
            $table->unique(['zone', 'row', 'bay', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::table('yard_locations', function (Blueprint $table) {
            $table->dropUnique(['zone', 'row', 'bay', 'tier']);
            $table->unique(['row', 'bay', 'tier']);
            $table->dropColumn('zone');
        });
    }
};
