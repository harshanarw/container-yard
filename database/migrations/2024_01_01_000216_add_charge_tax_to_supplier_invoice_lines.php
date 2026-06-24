<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            // Charge code drives expense account via charge_expense AccountMapping
            $table->foreignId('charge_code_id')->nullable()->after('description')
                ->constrained('charge_codes')->nullOnDelete();

            // Tax code snapshot — carried over from charge code at line creation time
            $table->foreignId('tax_code_id')->nullable()->after('charge_code_id')
                ->constrained('tax_codes')->nullOnDelete();

            // Rate snapshots (frozen at invoice creation so historical accuracy is preserved)
            $table->decimal('tax1_rate', 10, 4)->default(0)->after('expense_account_id');
            $table->decimal('tax2_rate', 10, 4)->default(0)->after('tax1_rate');

            // Computed tax amounts (SSCL embedded in expense; VAT is recoverable input tax)
            $table->decimal('tax1_amount', 15, 2)->default(0)->after('tax2_rate');
            $table->decimal('tax2_amount', 15, 2)->default(0)->after('tax1_amount');

            // gross = amount (net) + tax1_amount + tax2_amount
            $table->decimal('gross_amount', 15, 2)->default(0)->after('tax2_amount');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['charge_code_id']);
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn([
                'charge_code_id', 'tax_code_id',
                'tax1_rate', 'tax2_rate',
                'tax1_amount', 'tax2_amount', 'gross_amount',
            ]);
        });
    }
};
