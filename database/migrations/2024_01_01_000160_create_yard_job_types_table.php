<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_job_types', function (Blueprint $table) {
            $table->id();
            $table->string('job_type_code', 30)->unique();
            $table->string('job_type_name', 100);
            $table->string('movement_direction', 20)->default('gate_in');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // ── Revenue / workflow applicability flags ────────────────────────
            $table->boolean('handling_applicable')->default(false);
            $table->boolean('survey_applicable')->default(false);
            $table->boolean('estimate_applicable')->default(false);
            $table->boolean('repair_applicable')->default(false);
            $table->boolean('storage_applicable')->default(false);
            $table->boolean('wash_applicable')->default(false);
            $table->boolean('reefer_applicable')->default(false);
            $table->boolean('customs_applicable')->default(false);
            $table->boolean('cargo_transfer_applicable')->default(false);

            // ── Operational requirement flags ─────────────────────────────────
            $table->boolean('approval_required')->default(false);
            $table->boolean('damage_capture_required')->default(false);

            // ── Downstream status hint ────────────────────────────────────────
            $table->string('default_next_status', 50)->nullable();

            $table->text('remarks')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_job_types');
    }
};
