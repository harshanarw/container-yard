<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completion (cargo-collected) leg for cargo_transfers: when the cargo is
 * collected the substitute box is gated out, its storage / reefer are closed,
 * and the transfer is marked completed. Record the substitute box's out movement
 * and the completion date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_transfers', function (Blueprint $t) {
            $t->foreignId('substitute_gate_out_movement_id')
                ->nullable()
                ->after('source_gate_out_movement_id')
                ->constrained('gate_movements')
                ->nullOnDelete();
            $t->date('completed_date')->nullable()->after('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_transfers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('substitute_gate_out_movement_id');
            $t->dropColumn('completed_date');
        });
    }
};
