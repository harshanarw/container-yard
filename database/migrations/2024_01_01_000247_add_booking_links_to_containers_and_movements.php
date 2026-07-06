<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a reserved container to the booking line it is allocated against, and
 * records on the gate movement which booking (and gate-out purpose) a release
 * fulfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->foreignId('container_booking_line_id')->nullable()->after('available_since')
                ->constrained('container_booking_lines')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable()->after('container_booking_line_id');
        });

        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('container_booking_id')->nullable()->after('release_order')
                ->constrained('container_bookings')->nullOnDelete();
            $table->string('gate_out_purpose', 30)->nullable()->after('container_booking_id'); // yard_job_types code
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropForeign(['container_booking_line_id']);
            $table->dropColumn(['container_booking_line_id', 'reserved_at']);
        });

        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropForeign(['container_booking_id']);
            $table->dropColumn(['container_booking_id', 'gate_out_purpose']);
        });
    }
};
