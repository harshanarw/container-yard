<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'rejected' to work_orders status enum
        DB::statement("ALTER TABLE work_orders MODIFY COLUMN status
            ENUM('pending','in_progress','on_hold','completed','rejected','closed','cancelled')
            NOT NULL DEFAULT 'pending'");

        // QC sign-off columns on the header
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('qc_by')->nullable()->after('closed_by')
                  ->constrained('users')->nullOnDelete()
                  ->comment('User who performed the QC inspection');
            $table->timestamp('qc_at')->nullable()->after('qc_by')
                  ->comment('When QC was last submitted');
            $table->text('qc_notes')->nullable()->after('qc_at')
                  ->comment('Overall QC remarks from inspector');
        });

        // Per-line QC result columns
        Schema::table('work_order_lines', function (Blueprint $table) {
            $table->enum('qc_status', ['passed', 'failed'])->nullable()->after('technician_notes')
                  ->comment('null = not yet inspected');
            $table->text('qc_notes')->nullable()->after('qc_status')
                  ->comment('Inspector notes for this line (required when failed)');
            $table->foreignId('qc_by')->nullable()->after('qc_notes')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('qc_at')->nullable()->after('qc_by');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_lines', function (Blueprint $table) {
            $table->dropForeign(['qc_by']);
            $table->dropColumn(['qc_status', 'qc_notes', 'qc_by', 'qc_at']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['qc_by']);
            $table->dropColumn(['qc_by', 'qc_at', 'qc_notes']);
        });

        DB::statement("ALTER TABLE work_orders MODIFY COLUMN status
            ENUM('pending','in_progress','on_hold','completed','closed','cancelled')
            NOT NULL DEFAULT 'pending'");
    }
};
