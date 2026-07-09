<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Container holds (Phase 4). A hold is a cross-cutting block that is independent
 * of disposition — a container keeps its status (available / reserved) but a hold
 * prevents it from being allocated or gated out until cleared. Multiple holds can
 * stack (e.g. customs + damage) and clear independently; a container is "on hold"
 * while any row has cleared_at IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->enum('hold_type', ['customs', 'damage', 'stop_release', 'survey_pending', 'other'])->default('other');
            $table->text('reason')->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->text('clear_notes')->nullable();
            $table->timestamps();

            $table->index(['container_id', 'cleared_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_holds');
    }
};
