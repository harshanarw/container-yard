<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add hire tracking columns to yard_storage.
 *
 * hire_type distinguishes normal records from the two special record types
 * created by the on-hire / off-hire workflow:
 *   - 'on_hire'  → storage record belonging to the hire customer (or internal use)
 *   - 'resumed'  → storage record for the original customer after off-hire
 *
 * effective_gate_in_date carries the original physical gate-in date on 'resumed'
 * records so that the billing engine can correctly compute how many free days
 * have already been consumed since the container first entered the yard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_storage', function (Blueprint $table) {
            $table->enum('hire_type', ['normal', 'on_hire', 'resumed'])
                  ->default('normal')
                  ->after('tariff_tier');

            // FK populated on 'on_hire' and 'resumed' records
            $table->foreignId('hire_id')
                  ->nullable()
                  ->after('hire_type')
                  ->constrained('container_hires')
                  ->nullOnDelete();

            // Null for 'normal' and 'on_hire' records; set to original gate_in_date on 'resumed'
            $table->date('effective_gate_in_date')
                  ->nullable()
                  ->after('hire_id');
        });
    }

    public function down(): void
    {
        Schema::table('yard_storage', function (Blueprint $table) {
            $table->dropForeign(['hire_id']);
            $table->dropColumn(['hire_type', 'hire_id', 'effective_gate_in_date']);
        });
    }
};
