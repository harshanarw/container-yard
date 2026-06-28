<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| AR Credit Notes — issued to customers to reduce their receivable
| (sales returns / allowances / corrections). Posts the reverse of an invoice:
| DR Revenue + DR Output VAT, CR AR Control. May be applied against open AR
| invoices (non-cash settlement) or left as a customer credit balance.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_no', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('credit_date');
            $table->string('currency', 10)->default('LKR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            // Optional link to the original invoice this credit note relates to.
            $table->string('reference_invoice_type', 30)->nullable();
            $table->unsignedBigInteger('reference_invoice_id')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);     // net of tax
            $table->decimal('tax_amount', 18, 4)->default(0);   // output VAT being reversed
            $table->decimal('total_amount', 18, 4)->default(0); // subtotal + tax
            $table->decimal('base_amount', 18, 4)->default(0);  // total × exchange_rate
            $table->string('reason', 255)->nullable();
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->string('posting_error', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('credit_date');
            $table->index('status');
        });

        // Number sequence for credit-note numbering (CRN-YYYYMM-0001).
        DB::table('number_sequences')->updateOrInsert(
            ['module_code' => 'ar_credit_note'],
            [
                'label'              => 'AR Credit Notes',
                'prefix'             => 'CRN',
                'use_company_prefix' => true,
                'separator'          => '-',
                'date_format'        => 'Ym',
                'seq_padding'        => 4,
                'reset_period'       => 'monthly',
                'current_period'     => now()->format('Ym'),
                'last_number'        => 0,
                'is_system'          => true,
                'updated_at'         => now(),
                'created_at'         => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_credit_notes');
        DB::table('number_sequences')->where('module_code', 'ar_credit_note')->delete();
    }
};
