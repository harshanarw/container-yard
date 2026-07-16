<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture washing intent at the survey stage so it flows Survey → Estimate like
 * repair categories do. Washing is a flat per-container service (internal /
 * external / both), so it lives on the survey header, not per damage. A survey
 * can be washing-only (no damages) — the estimate import then produces just the
 * washing line(s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->boolean('wash_required')->default(false)->after('recommended_action');
            $table->enum('wash_scope', ['internal', 'external', 'both'])->nullable()->after('wash_required');
            $table->string('wash_type', 20)->nullable()->after('wash_scope');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['wash_required', 'wash_scope', 'wash_type']);
        });
    }
};
