<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry washing traceability from the estimate line onto the repair invoice line
 * so internal/external cleaning revenue can be split from the invoice side too.
 * Both nullable — ordinary repair lines leave them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->foreignId('washing_tariff_id')->nullable()->after('tax_code_id')
                  ->constrained('washing_tariffs')->nullOnDelete();
            $table->string('wash_scope', 10)->nullable()->after('washing_tariff_id');
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('washing_tariff_id');
            $table->dropColumn('wash_scope');
        });
    }
};
