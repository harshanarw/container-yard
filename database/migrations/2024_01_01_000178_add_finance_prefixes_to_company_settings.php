<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('prefix_receipt', 10)->default('RCP')->after('default_gate_out_format');
            $table->string('prefix_voucher', 10)->default('PV')->after('prefix_receipt');
            $table->string('prefix_journal', 10)->default('JV')->after('prefix_voucher');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['prefix_receipt', 'prefix_voucher', 'prefix_journal']);
        });
    }
};
