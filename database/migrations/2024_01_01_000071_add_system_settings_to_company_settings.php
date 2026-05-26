<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Operational
            $table->unsignedInteger('yard_capacity')->default(440)->after('software_provider');
            $table->unsignedSmallInteger('free_storage_days')->default(7)->after('yard_capacity');
            $table->string('timezone', 100)->default('Asia/Colombo')->after('free_storage_days');

            // Document number prefixes
            $table->string('prefix_invoice', 20)->default('INV')->after('timezone');
            $table->string('prefix_sh_invoice', 20)->default('SH')->after('prefix_invoice');
            $table->string('prefix_survey', 20)->default('SRV')->after('prefix_sh_invoice');
            $table->string('prefix_estimate', 20)->default('RE')->after('prefix_survey');
            $table->string('prefix_gate_in', 20)->default('GIN')->after('prefix_estimate');
            $table->string('prefix_gate_out', 20)->default('GOUT')->after('prefix_gate_in');

            // Billing defaults
            $table->decimal('default_tax_rate', 5, 2)->default(0)->after('prefix_gate_out');
            $table->decimal('surcharge_overtime', 5, 2)->default(50)->after('default_tax_rate');
            $table->decimal('surcharge_night', 5, 2)->default(75)->after('surcharge_overtime');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'yard_capacity', 'free_storage_days', 'timezone',
                'prefix_invoice', 'prefix_sh_invoice', 'prefix_survey',
                'prefix_estimate', 'prefix_gate_in', 'prefix_gate_out',
                'default_tax_rate', 'surcharge_overtime', 'surcharge_night',
            ]);
        });
    }
};
