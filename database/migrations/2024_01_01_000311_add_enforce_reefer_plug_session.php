<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a yard decide whether an unrecorded reefer plug-in stops a release.
 *
 * A laden reefer gated in under a plug service gets a `pending` plug session.
 * If nobody records the plug-in, gate-out closed that session as `not_plugged`
 * and said nothing -- and `not_plugged` is excluded from electricity billing by
 * two separate conditions, so the container simply never appeared on an
 * invoice.
 *
 * The trouble is that `pending` at gate-out means one of two things, and the
 * system cannot tell them apart:
 *
 *   - the box was never physically plugged, and billing nothing is correct;
 *   - the box ran on power for the whole stay and nobody recorded it, which is
 *     revenue lost with nothing said.
 *
 * `reefer:unplugged-sessions` exists to find the second kind after the fact,
 * and it reports real ones, so this is not hypothetical. The person who can
 * actually answer is the operator standing at the gate, which is where the
 * question now gets asked.
 *
 * Default **false**, matching `enforce_reefer_pti`: warn by default, and let a
 * yard that wants a hard stop turn one on. A default of true would start
 * refusing releases the moment this migration ran, which is not a decision a
 * migration gets to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('enforce_reefer_plug_session')
                  ->default(false)
                  ->after('enforce_reefer_pti');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('enforce_reefer_plug_session');
        });
    }
};
