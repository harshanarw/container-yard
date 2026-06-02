<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            // Polymorphic link to any approvable document
            $table->morphs('approvable');

            $table->string('workflow_type', 50)
                  ->comment('Matches document_type in approval_workflows');

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                  ->default('pending');

            $table->foreignId('initiated_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'status'], 'ar_approvable_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
