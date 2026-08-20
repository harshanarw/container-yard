<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-editable "overdue" thresholds for the M&R stages.
 *
 * A single JSON map of status code => days, rather than one column per stage.
 * There are sixteen stages that can be flagged and the catalogue is expected to
 * grow; sixteen-plus columns on company_settings would bloat a table that is
 * read on nearly every request, and each new status would need another
 * migration.
 *
 * Null means "never configured" — the shipped defaults in
 * MrStatusCatalogue::AGE_THRESHOLD_DAYS apply. A saved map only overrides the
 * keys it contains, so a yard that tunes two stages keeps the defaults for the
 * rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('mr_age_thresholds')->nullable()->after('mr_dimension_uom');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('mr_age_thresholds');
        });
    }
};
