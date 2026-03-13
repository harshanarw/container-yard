<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('billing_period_from');
            $table->date('billing_period_to');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_invoices');
    }
};
