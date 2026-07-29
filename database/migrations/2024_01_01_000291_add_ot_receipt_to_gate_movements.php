<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an out-of-hours gate-in to the overtime receipt that authorized it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('ot_receipt_id')->nullable()->after('bl_number')->constrained('ot_receipts')->nullOnDelete();
            $table->boolean('is_overtime')->default(false)->after('ot_receipt_id');
            $table->string('ot_override_reason', 255)->nullable()->after('is_overtime');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ot_receipt_id');
            $table->dropColumn(['is_overtime', 'ot_override_reason']);
        });
    }
};
