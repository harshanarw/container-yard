<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('estimate_line_item_id')->nullable()
                  ->constrained('estimate_line_items')->nullOnDelete()
                  ->comment('Null = header-level action; set = per-line action');
            $table->enum('action', [
                'submitted',        // Estimate submitted to owner
                'line_approved',    // Owner approved individual line
                'line_rejected',    // Owner rejected individual line
                'line_amended',     // Amount amended by owner
                'partially_approved', // Partial approval at header level
                'fully_approved',   // All lines approved
                'returned',         // Returned for amendment
            ]);
            $table->decimal('amended_amount', 15, 2)->nullable()
                  ->comment('Owner-proposed amount if line was amended');
            $table->text('notes')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_approval_actions');
    }
};
