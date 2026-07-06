<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking_applicable flags the gate-out purposes (e.g. Export Release) that
 * expect a booking; enforce_export_booking makes that a hard requirement
 * instead of a warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_job_types', function (Blueprint $table) {
            $table->boolean('booking_applicable')->default(false)->after('cargo_transfer_applicable');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('enforce_export_booking')->default(false)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('yard_job_types', function (Blueprint $table) {
            $table->dropColumn('booking_applicable');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('enforce_export_booking');
        });
    }
};
