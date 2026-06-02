<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_id')
                  ->constrained('approval_requests')
                  ->cascadeOnDelete();

            // Step metadata — denormalised so records remain readable after workflow edits
            $table->unsignedTinyInteger('step_order');
            $table->string('step_key', 60);
            $table->string('step_label', 100);

            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');

            $table->foreignId('actioned_by')->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->text('remarks')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['approval_request_id', 'step_order'], 'aa_request_order');
            $table->index(['status', 'step_order'],               'aa_status_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
