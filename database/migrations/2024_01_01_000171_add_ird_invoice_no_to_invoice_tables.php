<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->string('ird_invoice_no', 40)->nullable()->unique()->after('invoice_no');
        });

        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->string('ird_invoice_no', 40)->nullable()->unique()->after('invoice_no');
        });

        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->string('ird_invoice_no', 40)->nullable()->unique()->after('invoice_no');
        });

        Schema::table('reefer_electricity_invoices', function (Blueprint $table) {
            $table->string('ird_invoice_no', 40)->nullable()->unique()->after('invoice_no');
        });
    }

    public function down(): void
    {
        foreach (['storage_invoices', 'storage_handling_invoices', 'repair_invoices', 'reefer_electricity_invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('ird_invoice_no');
            });
        }
    }
};
