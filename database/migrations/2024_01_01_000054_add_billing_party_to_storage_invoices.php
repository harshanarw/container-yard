<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->foreignId('billing_party_id')->nullable()->after('customer_id')
                  ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Customer::class, 'billing_party_id');
        });
    }
};
