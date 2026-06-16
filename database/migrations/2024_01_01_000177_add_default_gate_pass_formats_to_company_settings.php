<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('default_gate_in_format', 20)->default('full')->after('enable_guard_post');
            $table->string('default_gate_out_format', 20)->default('full')->after('default_gate_in_format');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['default_gate_in_format', 'default_gate_out_format']);
        });
    }
};
