<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            // Gate-In: import shipment information
            $table->string('vessel_name', 100)->nullable()->after('seal_no');
            $table->string('voyage_no', 50)->nullable()->after('vessel_name');
            $table->date('berthing_date')->nullable()->after('voyage_no');
            $table->string('bl_number', 50)->nullable()->after('berthing_date');
            $table->date('do_expiry_date')->nullable()->after('bl_number');
            $table->string('consignee', 150)->nullable()->after('do_expiry_date');

            // Gate-Out: export information
            $table->string('loading_vessel', 100)->nullable()->after('consignee');
            $table->string('loading_voyage', 50)->nullable()->after('loading_vessel');
            $table->date('sailing_date')->nullable()->after('loading_voyage');
            $table->string('shipper', 150)->nullable()->after('sailing_date');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn([
                'vessel_name', 'voyage_no', 'berthing_date', 'bl_number',
                'do_expiry_date', 'consignee', 'loading_vessel', 'loading_voyage',
                'sailing_date', 'shipper',
            ]);
        });
    }
};
