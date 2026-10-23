<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refuse a seal number on a container recorded as empty.
 *
 * An empty box has nothing to seal, so a seal number on one is always one of
 * two mistakes: a seal typed into the wrong row, or a cargo status left at
 * Empty on a box that turned out to be loaded. The second is the expensive one
 * — `cargo_status` picks the storage and handling tariff rows, so a laden box
 * recorded as empty is billed at the empty rate for the whole stay, and the
 * seal sitting in the field beside it is the one piece of evidence that
 * something is wrong.
 *
 * Which is why this blocks rather than quietly clearing the seal: clearing it
 * would destroy the only hint that the cargo status is the thing at fault. The
 * operator is asked to say which of the two it was.
 *
 * **Two settings, one per direction**, because the gates are not the same
 * event. An arrival is a box the yard is taking responsibility for and the
 * cargo status drives what it is billed; a departure is handing it back, often
 * under a release order that already says what is inside. A yard may want the
 * stop at one gate and not the other.
 *
 * **Default true**, unlike `enforce_reefer_plug_session` next door, which
 * deliberately shipped off. That one encodes a policy a yard may legitimately
 * not want. This one encodes an impossibility — an empty container with a seal
 * on it is wrong wherever it appears — so the default is to say so.
 *
 * The consequence of that default is worth stating plainly: any existing
 * movement that already carries an empty-with-a-seal combination will be
 * refused the first time somebody saves it from the edit screen, because the
 * rule reads what is submitted. The fix is in the same form, one field away.
 * Count them before turning this on:
 *
 *   SELECT movement_type, COUNT(*) FROM gate_movements
 *   WHERE cargo_status = 'empty' AND seal_no IS NOT NULL AND seal_no <> ''
 *   GROUP BY movement_type;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('block_seal_on_empty_in')
                  ->default(true)
                  ->after('require_seal_for_laden');

            $table->boolean('block_seal_on_empty_out')
                  ->default(true)
                  ->after('block_seal_on_empty_in');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['block_seal_on_empty_in', 'block_seal_on_empty_out']);
        });
    }
};
