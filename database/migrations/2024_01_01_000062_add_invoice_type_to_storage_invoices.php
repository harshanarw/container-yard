<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->string('invoice_type', 20)->default('invoice')->after('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
        });
    }
};
