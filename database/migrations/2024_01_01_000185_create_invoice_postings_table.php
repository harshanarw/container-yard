<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_postings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_type', 50);
            $table->unsignedBigInteger('invoice_id');
            $table->foreignId('journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->enum('status', ['pending', 'posted', 'failed', 'voided'])->default('pending');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['invoice_type', 'invoice_id']);
            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_postings');
    }
};
