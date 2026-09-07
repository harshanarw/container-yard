<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a reefer container's machinery is in use for this visit.
 *
 * A reefer carrying dry cargo with the compressor off is a NOR — Non-Operating
 * Reefer — and it is ordinary practice: repositioning equipment rather than
 * shipping air, filling reefer boxes when dry capacity is short, or moving a
 * defective unit as dry until it reaches a repair facility. For that voyage it
 * is a dry box with a compressor bolted to one end.
 *
 * **On the movement, not the container.** The same box is a NOR this visit and
 * an operating reefer the next, exactly like `cargo_status`.
 *
 * **Deliberately not folded into `cargo_status`.** Whether the box is loaded and
 * whether its machinery runs are independent questions; one field for both would
 * lose "empty reefer, machinery running" and could contradict itself. It would
 * also reach billing, since handling tariffs, storage invoice lines and the
 * Weekly Performance grid all key on `cargo_status`.
 *
 * Null means the question does not arise — a dry box — and, on a historical
 * reefer movement, is read as `operating`, which is how every one of them
 * behaved when it was recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->enum('reefer_mode', ['operating', 'non_operating'])
                  ->nullable()
                  ->after('cargo_status');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn('reefer_mode');
        });
    }
};
