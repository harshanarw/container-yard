<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reefer_electricity_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('invoice_date');
            $table->date('billing_period_from');
            $table->date('billing_period_to');
            $table->char('invoice_currency', 3)->default('LKR');
            $table->decimal('exchange_rate', 12, 4)->default(1.0000);
            // All monetary totals stored in default (LKR) currency
            $table->decimal('subtotal',        12, 2)->default(0);
            $table->decimal('sscl_percentage', 5, 2)->default(0);
            $table->decimal('sscl_amount',     12, 2)->default(0);
            $table->decimal('vat_percentage',  5, 2)->default(0);
            $table->decimal('vat_amount',      12, 2)->default(0);
            $table->decimal('total_amount',    12, 2)->default(0); // LKR total
            $table->decimal('total_value',     12, 2)->default(0); // same as total_amount (LKR)
            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reefer_electricity_invoices'); }
};
