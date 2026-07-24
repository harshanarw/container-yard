<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File-storage control (Phase 1). A ledger of every uploaded file (size + section
 * + owner) so usage can be summed instantly, enforced against a company-wide
 * limit, and broken down for the storage report. Plus the settings for the limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->string('section', 40)->index();          // guard_post, gate_ocr, document, company, customer, user…
            $table->unsignedBigInteger('size')->default(0);   // bytes
            $table->string('mime_type', 100)->nullable();
            $table->nullableMorphs('owner');                  // owning model, when known
            $table->foreignId('document_id')->nullable();     // link when the file is a Document
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['disk', 'path']);                 // one ledger row per physical file
            $table->index('size');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->unsignedInteger('max_storage_mb')->nullable()->after('yard_capacity');
            $table->boolean('enforce_storage_limit')->default(false)->after('max_storage_mb');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['max_storage_mb', 'enforce_storage_limit']);
        });

        Schema::dropIfExists('file_assets');
    }
};
