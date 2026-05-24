<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('tax1_label', 50)->default('Tax 1')->after('product_icon_path');
            $table->string('tax2_label', 50)->default('Tax 2')->after('tax1_label');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['tax1_label', 'tax2_label']);
        });
    }
};
