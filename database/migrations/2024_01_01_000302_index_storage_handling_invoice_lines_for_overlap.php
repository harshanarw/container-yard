<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make "was this container already billed for these days?" a cheap question.
 *
 * `storage_handling_invoice_lines` carried no index of its own — only the
 * implicit one on the `invoice_id` foreign key — because nothing ever queried it
 * by container. Billing now does, on every preview and every save, and a table
 * that grows by one row per container per month is not one to scan.
 *
 * The columns are in lookup order: `container_id` alone narrows to a handful of
 * rows, and the two dates let the overlap comparison read straight from the
 * index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->index(['container_id', 'storage_from', 'storage_to'], 'shil_container_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->dropIndex('shil_container_period_idx');
        });
    }
};
