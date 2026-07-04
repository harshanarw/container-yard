<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bill type lets the Storage & Handling module generate three kinds of
     * invoice from one screen: the combined bill, storage-only, or handling-only.
     * Existing rows default to the combined type, preserving current behaviour.
     */
    public function up(): void
    {
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->string('bill_type', 20)->default('storage_handling')->after('invoice_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->dropIndex(['bill_type']);
            $table->dropColumn('bill_type');
        });
    }
};
