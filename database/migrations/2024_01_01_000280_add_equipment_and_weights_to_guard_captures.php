<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the guard capture should carry a resolved equipment type (from the
 * ISO size/type code, whether typed or OCR-read) and the tare / max-gross
 * weights OCR extracts, so the gate-in hand-off is exact instead of re-inferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_captures', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')->nullable()->after('iso_code')
                  ->constrained('equipment_types')->nullOnDelete();
            $table->unsignedInteger('tare_kg')->nullable()->after('equipment_type_id');
            $table->unsignedInteger('max_gross_kg')->nullable()->after('tare_kg');
        });
    }

    public function down(): void
    {
        Schema::table('guard_captures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_type_id');
            $table->dropColumn(['tare_kg', 'max_gross_kg']);
        });
    }
};
