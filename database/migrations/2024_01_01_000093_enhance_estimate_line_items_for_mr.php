<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            // Link back to the survey damage line that triggered this estimate line
            $table->foreignId('damage_id')->nullable()->after('estimate_id')
                  ->constrained('damages')->nullOnDelete();

            // Tariff rule reference (if auto-populated from tariff)
            $table->foreignId('mr_tariff_rule_id')->nullable()->after('damage_id')
                  ->constrained('mr_tariff_rules')->nullOnDelete();

            // M&R code references
            $table->foreignId('location_code_id')->nullable()->after('mr_tariff_rule_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('component_code_id')->nullable()->after('location_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('damage_code_id')->nullable()->after('component_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->after('damage_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('material_code_id')->nullable()->after('repair_code_id')
                  ->constrained('mr_codes')->nullOnDelete();

            // Labor breakdown
            $table->decimal('std_labor_hours', 6, 2)->default(0)->after('unit_price');
            $table->decimal('labor_rate', 10, 2)->default(0)->after('std_labor_hours');
            $table->decimal('labor_amount', 15, 2)->default(0)->after('labor_rate');

            // Material breakdown
            $table->decimal('material_qty', 8, 3)->default(0)->after('labor_amount');
            $table->decimal('material_rate', 10, 2)->default(0)->after('material_qty');
            $table->decimal('material_amount', 15, 2)->default(0)->after('material_rate');

            // Ancillary
            $table->decimal('ancillary_amount', 10, 2)->default(0)->after('material_amount');

            // Per-line approval workflow (depot marks each line)
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'amended'])
                  ->default('pending')->after('line_amount');
            $table->boolean('is_override')->default(false)->after('approval_status');
            $table->string('override_reason', 255)->nullable()->after('is_override');
            $table->foreignId('override_by')->nullable()->after('override_reason')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('override_at')->nullable()->after('override_by');

            // Inline CEDEX code for this line
            $table->string('cedex_code', 50)->nullable()->after('override_at');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_id');
            $table->dropConstrainedForeignId('mr_tariff_rule_id');
            $table->dropConstrainedForeignId('location_code_id');
            $table->dropConstrainedForeignId('component_code_id');
            $table->dropConstrainedForeignId('damage_code_id');
            $table->dropConstrainedForeignId('repair_code_id');
            $table->dropConstrainedForeignId('material_code_id');
            $table->dropConstrainedForeignId('override_by');
            $table->dropColumn([
                'std_labor_hours', 'labor_rate', 'labor_amount',
                'material_qty', 'material_rate', 'material_amount',
                'ancillary_amount', 'approval_status', 'is_override',
                'override_reason', 'override_at', 'cedex_code',
            ]);
        });
    }
};
