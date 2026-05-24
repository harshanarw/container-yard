<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('local_agent_id')->nullable()->after('tax_exempt')
                  ->constrained('customers')->nullOnDelete();
            $table->foreignId('billing_party_id')->nullable()->after('local_agent_id')
                  ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Customer::class, 'local_agent_id');
            $table->dropForeignIdFor(\App\Models\Customer::class, 'billing_party_id');
        });
    }
};
