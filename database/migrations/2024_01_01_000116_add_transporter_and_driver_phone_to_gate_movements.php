<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('transporter_id')->nullable()->after('customer_id')->constrained('customers')->nullOnDelete();
            $table->string('driver_phone', 20)->nullable()->after('driver_ic');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropForeign(['transporter_id']);
            $table->dropColumn(['transporter_id', 'driver_phone']);
        });
    }
};
