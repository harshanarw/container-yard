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

        // **No backfill, deliberately.**
        //
        // The obvious next line is to re-label every completed session with no
        // plug-in. On the yard that found this, that would have been seventeen
        // sessions totalling 310 container-days, including stays of 35, 37 and
        // 95 days — on *laden* reefers, whose cargo does not survive five weeks
        // without power. They were plugged in. What was never recorded is the
        // plug-in, and the screen agrees: Currently Active 0, Billed 0, so that
        // step has not been used once.
        //
        // Stamping "not plugged" on unbilled electricity would bury it under a
        // label that reads like a decision. `reefer:unplugged-sessions` lists
        // them instead, so the yard can enter the times it can reconstruct and
        // mark only the rest.
    }

    /** Anything already marked not_plugged goes back to completed first. */
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
