<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_name', 50)->nullable();         // module key: yard, surveys, estimates …
            $table->string('event', 50);                        // created | updated | deleted | approved | rejected | gate-in | gate-out | plug-in | plug-out | temp-log
            $table->string('description', 500)->nullable();     // human-readable sentence
            $table->string('reference', 100)->nullable();       // PRIMARY SEARCH KEY: container_no, job_no, invoice_no …
            $table->string('subject_type', 150)->nullable();    // App\Models\GateMovement
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_name', 150)->nullable();     // name snapshot (survives user rename/delete)
            $table->string('causer_role', 100)->nullable();     // role snapshot
            $table->json('properties')->nullable();             // { old: {…}, new: {…} } or { attributes: {…} }
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast search
            $table->index('reference');
            $table->index(['log_name', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['causer_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
