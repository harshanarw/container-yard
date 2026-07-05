<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line SSCL / VAT on AP credit notes, mirroring the supplier invoice so a
 * credit note reverses exactly what the bill booked: SSCL back into the expense
 * cost, VAT back into the recoverable input-VAT account resolved per tax code.
 *
 * Header keeps tax_amount = VAT (its existing "Input VAT" meaning); sscl_amount
 * is added for the SSCL total so total_amount = subtotal + sscl_amount + tax_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ap_credit_note_lines', function (Blueprint $table) {
            $table->foreignId('tax_code_id')->nullable()->after('charge_code_id')
                ->constrained('tax_codes')->nullOnDelete();
            $table->decimal('tax1_rate', 8, 4)->default(0)->after('amount');   // SSCL %
            $table->decimal('tax2_rate', 8, 4)->default(0)->after('tax1_rate'); // VAT %
            $table->decimal('tax1_amount', 18, 2)->default(0)->after('tax2_rate');   // SSCL — embedded in expense
            $table->decimal('tax2_amount', 18, 2)->default(0)->after('tax1_amount'); // VAT — recoverable input tax
            $table->decimal('gross_amount', 18, 2)->default(0)->after('tax2_amount');
        });

        Schema::table('ap_credit_notes', function (Blueprint $table) {
            $table->decimal('sscl_amount', 18, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('ap_credit_note_lines', function (Blueprint $table) {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn(['tax_code_id', 'tax1_rate', 'tax2_rate', 'tax1_amount', 'tax2_amount', 'gross_amount']);
        });

        Schema::table('ap_credit_notes', function (Blueprint $table) {
            $table->dropColumn('sscl_amount');
        });
    }
};
