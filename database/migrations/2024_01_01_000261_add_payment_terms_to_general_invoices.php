<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General invoices now carry the credit term used to derive the due date,
 * mirroring the customer profile's AR payment terms. Stored so it prints on
 * the invoice and stays auditable independently of any later profile change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_invoices', function (Blueprint $table) {
            $table->enum('payment_terms', ['cod', 'net15', 'net30', 'net45', 'net60'])
                  ->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('general_invoices', function (Blueprint $table) {
            $table->dropColumn('payment_terms');
        });
    }
};
