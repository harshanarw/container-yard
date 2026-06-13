<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 30)->unique();
            $table->unsignedInteger('job_seq');
            $table->foreignId('job_type_id')->constrained('yard_job_types');
            $table->string('job_type_code', 30);
            $table->string('type_short_code', 5);
            $table->foreignId('customer_id')->constrained('customers');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['job_type_id', 'job_seq']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_jobs');
    }
};
