<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->enum('category', ['consignee', 'owned', 'leased'])->default('consignee')->after('container_no');
            $table->smallInteger('manufacture_year')->unsigned()->nullable()->after('type_code');
            $table->string('manufacturer', 100)->nullable()->after('manufacture_year');
            $table->string('owner_code', 20)->nullable()->after('manufacturer');
            $table->string('owner_name', 100)->nullable()->after('owner_code');
            $table->decimal('gross_weight_kg', 8, 2)->nullable()->after('owner_name');
            $table->decimal('tare_weight_kg', 8, 2)->nullable()->after('gross_weight_kg');
            $table->decimal('max_payload_kg', 8, 2)->nullable()->after('tare_weight_kg');
            $table->string('csc_plate_no', 50)->nullable()->after('max_payload_kg');
            $table->date('csc_expiry_date')->nullable()->after('csc_plate_no');
            $table->text('notes')->nullable()->after('csc_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'manufacture_year', 'manufacturer',
                'owner_code', 'owner_name',
                'gross_weight_kg', 'tare_weight_kg', 'max_payload_kg',
                'csc_plate_no', 'csc_expiry_date', 'notes',
            ]);
        });
    }
};
