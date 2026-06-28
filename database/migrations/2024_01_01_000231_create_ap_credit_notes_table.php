<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| AP Credit Notes — received from vendors/suppliers, reducing what we owe them
| (purchase returns / allowances / corrections). Posts the reverse of a supplier
| bill: DR AP Control, CR Expense + CR Input VAT. May be applied against open
| supplier invoices (non-cash settlement) or left as a vendor credit balance.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_no', 30)->unique();         // our internal reference
            $table->string('supplier_credit_no', 50)->nullable();   // vendor's own CN number
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete(); // the contact/vendor
            $table->date('credit_date');
            $table->string('currency', 10)->default('LKR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->unsignedBigInteger('reference_supplier_invoice_id')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);   // input VAT being reversed
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('base_amount', 18, 4)->default(0);
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

        DB::table('number_sequences')->updateOrInsert(
            ['module_code' => 'ap_credit_note'],
            [
                'label'              => 'AP Credit Notes',
                'prefix'             => 'APCN',
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
        Schema::dropIfExists('ap_credit_notes');
        DB::table('number_sequences')->where('module_code', 'ap_credit_note')->delete();
    }
};
