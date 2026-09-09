<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A reefer that left without ever being plugged in now says so.
 *
 * `reefer_plug_sessions.status` offered only `pending | active | completed |
 * billed`, so gate-out had nowhere to put a session whose plug-in was never
 * recorded. It marked them **completed** — the code even said so:
 *
 *     // Still pending (plug-in never recorded) - mark completed without billing
 *
 * The consequences all followed from that one word. The screen showed
 * "Completed" against blank Plug-In and Plug-Out columns; the "Ready to Bill"
 * counter included sessions that can never be billed; and
 * `ReeferBillingService` selected them (`where status = completed`) and then
 * dropped them, because it returns null when either timestamp is missing. A
 * silent no-op, and no way to tell it apart from a session that genuinely had
 * nothing to charge.
 *
 * `not_plugged` rather than `cancelled`: nobody cancelled anything. The word a
 * supervisor needs is the one that says what happened to the box.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE reefer_plug_sessions MODIFY status
             ENUM('pending','active','completed','billed','not_plugged') NOT NULL DEFAULT 'pending'"
        );

        // Re-label the history. A completed session with no plug-in never ran,
        // whatever its status said — this changes the label, not the facts.
        //
        // Deliberately keyed on plug_in_at alone: a session with a plug-in but
        // no plug-out is a different problem (someone forgot to unplug it) and
        // is not this migration's to reinterpret.
        $relabelled = DB::table('reefer_plug_sessions')
            ->where('status', 'completed')
            ->whereNull('plug_in_at')
            ->update(['status' => 'not_plugged', 'updated_at' => now()]);

        if ($relabelled > 0) {
            info("[reefer] {$relabelled} plug session(s) re-labelled completed → not_plugged.");
        }
    }

    /**
     * Puts them back the way they were, wrong label and all, so the rollback is
     * a true reversal rather than a second opinion.
     */
    public function down(): void
    {
        DB::table('reefer_plug_sessions')
            ->where('status', 'not_plugged')
            ->update(['status' => 'completed', 'updated_at' => now()]);

        DB::statement(
            "ALTER TABLE reefer_plug_sessions MODIFY status
             ENUM('pending','active','completed','billed') NOT NULL DEFAULT 'pending'"
        );
    }
};
