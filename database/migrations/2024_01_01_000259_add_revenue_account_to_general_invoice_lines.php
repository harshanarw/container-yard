<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line revenue account override for general invoices. Defaults to the charge
 * code's mapped charge_revenue account, but the user may pick a different income
 * account; the GL posting then credits the chosen account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_invoice_lines', function (Blueprint $table) {
            $table->foreignId('revenue_account_id')->nullable()->after('charge_code_id')
                  ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('general_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revenue_account_id');
        });
    }
};
