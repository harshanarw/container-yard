<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make "which boxes did this truck move?" a cheap question.
 *
 * `vehicle_plate` is recorded at both gates and shown on the container
 * timeline, but nothing ever queried by it, so it carries no index. The gate
 * movement search now does -- on both gates, through a correlated subquery --
 * and an unindexed column there means a full scan of the movement table per
 * search.
 *
 * **The index only helps because the plate is matched as a prefix.** A leading
 * wildcard (`LIKE '%ABC%'`) cannot use an index at all, so adding one while
 * matching on "contains" would be decoration. `container_no` in the same search
 * is already matched this way for the same reason, and a plate -- like a
 * container number -- is something people type from the front.
 *
 * `driver_name` is deliberately left unindexed: a name is searched by any part
 * of it, that has to stay a "contains" match, and an index it cannot use is
 * just a slower write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->index('vehicle_plate', 'gm_vehicle_plate_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropIndex('gm_vehicle_plate_idx');
        });
    }
};
