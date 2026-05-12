<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // Polymorphic owner (GateMovement, Inquiry, Invoice, Customer, ...)
            $table->morphs('documentable');
            // Storage
            $table->string('provider', 20)->default('local'); // local|dropbox|gdrive
            $table->string('path', 500);                       // path on the provider
            $table->string('disk', 50)->default('public');     // for local: which disk
            // Metadata
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);    // bytes
            $table->string('label', 100)->nullable();          // user-defined label
            $table->string('document_type', 50)->nullable();   // photo|document|certificate|other
            // Ownership
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
