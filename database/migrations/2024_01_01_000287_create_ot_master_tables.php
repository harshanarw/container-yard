<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime (OT) module masters — Phase 1:
 *   - working_hour_sets / weekly_working_hours : normal working hours per weekday
 *   - holidays                                 : holiday calendar (mercantile flag)
 *   - ot_tariff_versions / ot_tariff_rules     : effective-dated OT tariff rules
 *
 * Rates are NOT hard-coded in code — they live in ot_tariff_rules, seeded from the
 * ACDO circular and versionable by administrators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hour_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active'); // active | draft | retired
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('weekly_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('working_hour_set_id')->constrained('working_hour_sets')->cascadeOnDelete();
            $table->string('day_of_week', 10);              // monday..sunday
            $table->boolean('is_regular_working_day')->default(true);
            $table->time('normal_start_time')->nullable();  // null = closed
            $table->time('normal_end_time')->nullable();
            $table->string('after_hours_policy', 20)->default('ot_required'); // ot_required | block | manual_approval
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['working_hour_set_id', 'day_of_week']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('holiday_name', 150);
            $table->string('holiday_type', 20)->default('mercantile'); // mercantile | public | poya | company_special
            $table->boolean('is_mercantile')->default(true);
            $table->string('working_hour_override', 10)->default('closed'); // closed | custom | normal
            $table->time('custom_start_time')->nullable();
            $table->time('custom_end_time')->nullable();
            $table->string('ot_day_category_override', 40)->nullable(); // default resolves to sunday_mercantile_holiday
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('ot_tariff_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code', 40)->unique();
            $table->string('name', 150);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->char('currency', 3)->default('LKR');
            $table->string('source_reference', 255)->nullable();
            $table->string('approval_status', 20)->default('active'); // draft | approved | active | retired
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ot_tariff_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_tariff_version_id')->constrained('ot_tariff_versions')->cascadeOnDelete();
            $table->string('rule_code', 40);
            $table->string('movement_type', 20)->default('gate_in'); // gate_in | gate_out | both
            $table->string('day_category', 40);   // weekday | saturday | sunday_mercantile_holiday | custom_holiday
            $table->string('period_code', 10);     // a | b | custom
            $table->string('display_name', 150);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->decimal('rate_amount', 15, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->string('charge_basis', 30)->default('per_bl_receipt'); // per_bl_receipt | per_container | per_gate_transaction | per_request
            $table->boolean('allow_receipt_extension')->default(true);
            $table->string('billing_mode_on_extension', 20)->default('full_new_charge'); // full_new_charge | difference_only | manual_amount
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['ot_tariff_version_id', 'rule_code']);
            $table->index(['day_category', 'period_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_tariff_rules');
        Schema::dropIfExists('ot_tariff_versions');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('weekly_working_hours');
        Schema::dropIfExists('working_hour_sets');
    }
};
