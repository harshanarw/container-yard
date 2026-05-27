<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->enum('cargo_status', ['laden', 'empty'])
                  ->nullable()
                  ->after('gate_in_date');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropColumn('cargo_status');
        });
    }
};
