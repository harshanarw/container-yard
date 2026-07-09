<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reefer pre-trip inspection (PTI) — Phase 4. A reefer must pass a PTI before it
 * is released for export. Each inspection records a pass/fail (with set-point and
 * optional validity); the latest result is denormalised onto the container for
 * cheap gating. enforce_reefer_pti makes a passing PTI a hard requirement at
 * gate-out instead of a warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reefer_pti_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->decimal('set_point_temp', 6, 2)->nullable();
            $table->enum('result', ['pass', 'fail'])->default('pass');
            $table->text('findings')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['container_id', 'inspected_at']);
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->enum('pti_status', ['none', 'passed', 'failed'])->default('none')->after('reserved_at');
            $table->timestamp('pti_at')->nullable()->after('pti_status');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('enforce_reefer_pti')->default(false)->after('enforce_export_booking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reefer_pti_inspections');

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn(['pti_status', 'pti_at']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('enforce_reefer_pti');
        });
    }
};
