<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime receipts — one per BL and selected service window (A/B). Carries the
 * snapshotted tariff rule + amount, the validity window, count-based utilization,
 * the received bank/cash account and the posted GL journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->string('bl_number', 50)->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('ot_tariff_version_id')->constrained('ot_tariff_versions')->restrictOnDelete();
            $table->foreignId('ot_tariff_rule_id')->constrained('ot_tariff_rules')->restrictOnDelete();

            $table->date('operational_date');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');

            $table->decimal('receipt_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->char('currency', 3)->default('LKR');

            $table->unsignedInteger('expected_container_count')->default(1);
            $table->unsignedInteger('used_container_count')->default(0);

            // draft | generated | paid | partially_used | fully_used | cancelled | void
            $table->string('status', 20)->default('generated');
            $table->foreignId('extension_of_receipt_id')->nullable()->constrained('ot_receipts')->nullOnDelete();
            $table->string('billing_mode', 20)->default('full_new_charge');

            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('payment_method', 20)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_receipts');
    }
};
