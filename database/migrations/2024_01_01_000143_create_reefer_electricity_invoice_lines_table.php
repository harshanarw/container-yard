<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reefer_electricity_invoice_lines', function (Blueprint $table) {
            $table->id();

            // FK names kept under MySQL's 64-char identifier limit
            $table->unsignedBigInteger('reefer_electricity_invoice_id');
            $table->foreign('reefer_electricity_invoice_id', 'ref_inv_lines_invoice_fk')
                  ->references('id')->on('reefer_electricity_invoices')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('plug_session_id')->nullable();
            $table->foreign('plug_session_id', 'ref_inv_lines_session_fk')
                  ->references('id')->on('reefer_plug_sessions')
                  ->nullOnDelete();

            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('container_no', 12);

            $table->dateTime('plug_in_at')->nullable();
            $table->dateTime('plug_out_at')->nullable();

            $table->enum('billing_mode', ['hourly', 'daily']);

            // Duration breakdown
            $table->decimal('total_hours',       8, 2)->nullable();
            $table->unsignedInteger('total_days')->nullable();
            $table->decimal('free_hours',        5, 2)->nullable();
            $table->unsignedInteger('free_days')->nullable();
            $table->decimal('chargeable_hours',  8, 2)->nullable();
            $table->unsignedInteger('chargeable_days')->nullable();

            // Rate applied
            $table->decimal('rate',     12, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->decimal('subtotal', 12, 2)->default(0);

            // Tax
            $table->foreignId('charge_code_id')->nullable()->constrained('charge_codes')->nullOnDelete();
            $table->decimal('tax1_rate', 5, 2)->default(0);   // SSCL
            $table->decimal('tax2_rate', 5, 2)->default(0);   // VAT

            // Line totals (in invoice_currency)
            $table->decimal('line_sscl',  12, 2)->default(0);
            $table->decimal('line_vat',   12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            // line_value stored in system default currency (for accounting)
            $table->decimal('line_value', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reefer_electricity_invoice_lines');
    }
};
