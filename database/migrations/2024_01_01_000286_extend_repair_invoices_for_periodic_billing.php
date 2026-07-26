<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing repair-invoice tables so one RepairInvoice can be a
 * PERIODIC (consolidated) bill spanning many estimates, in addition to the
 * current one-estimate invoice. Estimate-based and periodic invoices remain the
 * same type, so posting / IRD / numbering / screens are shared.
 *
 * Header: estimate/container become optional (a periodic bill spans many), plus
 * period, billing-mode, billing-party and selected-categories fields.
 * Lines: carry their own container_no + repair_category_id so a multi-container,
 * multi-category invoice can group and print correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->enum('billing_mode', ['estimate', 'periodic'])->default('estimate')->after('invoice_no');
            $table->string('period_basis', 20)->nullable()->after('due_date')
                  ->comment('wo_completed | approved | estimate — which date puts an estimate in range');
            $table->date('billing_period_from')->nullable()->after('period_basis');
            $table->date('billing_period_to')->nullable()->after('billing_period_from');
            $table->json('bill_categories')->nullable()->after('billing_period_to')
                  ->comment('selected repair_category_ids for a periodic bill (display/reprint)');
            $table->foreignId('billing_party_id')->nullable()->after('customer_id')
                  ->constrained('customers')->nullOnDelete();
        });

        // Relax NOT NULL on the estimate-based columns so a periodic invoice (which
        // spans many estimates/containers) can leave them empty. FKs are preserved.
        DB::statement('ALTER TABLE repair_invoices MODIFY estimate_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE repair_invoices MODIFY container_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE repair_invoices MODIFY container_no VARCHAR(12) NULL');

        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->after('estimate_line_item_id')
                  ->constrained('containers')->nullOnDelete();
            $table->string('container_no', 12)->nullable()->after('container_id');
            $table->foreignId('repair_category_id')->nullable()->after('container_no')
                  ->constrained('repair_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('container_id');
            $table->dropColumn('container_no');
            $table->dropConstrainedForeignId('repair_category_id');
        });

        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_party_id');
            $table->dropColumn([
                'billing_mode', 'period_basis',
                'billing_period_from', 'billing_period_to', 'bill_categories',
            ]);
        });

        DB::statement('ALTER TABLE repair_invoices MODIFY estimate_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE repair_invoices MODIFY container_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE repair_invoices MODIFY container_no VARCHAR(12) NOT NULL');
    }
};
