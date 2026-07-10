<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traceability for washing lines on an estimate: which washing tariff priced the
 * line and which scope (internal/external) it represents, so revenue can be split
 * and a scope isn't added twice. Both nullable — ordinary repair lines leave them
 * empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->foreignId('washing_tariff_id')->nullable()->after('mr_tariff_item_id')
                  ->constrained('washing_tariffs')->nullOnDelete();
            $table->string('wash_scope', 10)->nullable()->after('washing_tariff_id');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('washing_tariff_id');
            $table->dropColumn('wash_scope');
        });
    }
};
