<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes on Gate Data Check findings that were looked at and accepted.
 *
 * Some findings have no correct answer. A container released with no arrival on
 * record cannot be corrected — the arrival was never captured, and inventing one
 * would replace a visible gap with an invented fact. Without somewhere to say
 * "looked at, nothing to do", that row shows red forever, and a list that always
 * shows red is a list people stop opening.
 *
 * Keyed on the **movement and the check**, not on the movement alone. A row
 * accepted for having no gate-in that is later edited into a different problem
 * is a new finding, and should reappear. The note records that one finding was
 * accepted, not that the row is exempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_check_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_movement_id')->constrained('gate_movements')->cascadeOnDelete();
            $table->string('check', 40);
            $table->string('note', 500);
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One standing note per finding; accepting again replaces it.
            $table->unique(['gate_movement_id', 'check'], 'gate_check_review_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_check_reviews');
    }
};
