<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            // Link to the Bank master. Nullable so legacy rows (and the FK backfill)
            // are not blocked; new records are required to pick a bank at the form.
            $table->foreignId('bank_id')->nullable()->after('account_name')
                  ->constrained('banks')->nullOnDelete();
        });

        // bank_name is kept as a denormalised snapshot of the linked bank so existing
        // display code (BankAccount::display_name, receipts, payment vouchers) keeps
        // working untouched; the controller writes it from the selected bank on save.
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};
