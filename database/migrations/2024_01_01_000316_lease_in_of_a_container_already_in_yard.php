<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taking a container on hire that is **already on the ground**.
 *
 * `LessorOnHireService::onHire()` models a box *arriving* on hire: it fabricates
 * a gate-in movement and, at off-hire, a gate-out. For a container already in
 * the yard under a shipping line's own job, that is wrong in a way that reaches
 * every report — the ledger gains an arrival and a departure for a box that
 * never moved, so the gate search shows a phantom departure and the stock
 * reports drop the container from the line's list entirely.
 *
 * The rule this establishes: **a job creates gate movements only when the
 * container physically moves.** The stay does, on arrival and final return. A
 * re-let does, each time a customer takes it away and brings it back. A lease-in
 * never does — only commercial custody changes.
 *
 * `on_hire_mode` keeps both shapes and says which is which, rather than leaving
 * it to be inferred from a null `gate_movement_id`:
 *
 *   arrival  — the box arrives on hire. The existing behaviour, so it is the
 *              default and every existing row keeps its meaning.
 *   in_yard  — a box already here is taken on hire. No gate movements.
 *
 * The storage columns mirror `container_hires`, which already suspends and
 * resumes a customer's storage correctly. Storage must pause for the lease: the
 * yard cannot bill a shipping line for storing a container it is simultaneously
 * paying that same line rent for.
 *
 * `expected_off_hire_date` is separate from `off_hire_date` because a lease is
 * often agreed with no end date at all. One is a plan and editable; the other is
 * what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessor_on_hires', function (Blueprint $t) {
            $t->enum('on_hire_mode', ['arrival', 'in_yard'])
              ->default('arrival')
              ->after('gate_movement_id');

            // The customer's storage this lease suspended, and the one opened
            // when it resumes. Null on an `arrival` lease, which suspends none.
            $t->foreignId('original_yard_storage_id')->nullable()
              ->after('on_hire_mode')->constrained('yard_storage')->nullOnDelete();
            $t->foreignId('resumed_yard_storage_id')->nullable()
              ->after('original_yard_storage_id')->constrained('yard_storage')->nullOnDelete();

            // The free-day anchor, denormalised so it survives the original
            // storage row being deleted — the same reason container_hires
            // carries it.
            $t->date('original_gate_in_date')->nullable()->after('resumed_yard_storage_id');

            // A plan, not a fact. Editable, and often unknown at the start.
            $t->date('expected_off_hire_date')->nullable()->after('on_hire_date');
        });

        // A lease-period storage row is zero-rated and explicitly typed, rather
        // than simply absent. A gap in the timeline is indistinguishable from
        // missing data; a row that says `lease_in` says *why* nothing is
        // charged. Queries that look for billable storage already filter
        // `whereIn('hire_type', ['normal', 'resumed'])`, so they skip it.
        DB::statement(
            "ALTER TABLE yard_storage MODIFY COLUMN hire_type "
            . "ENUM('normal', 'on_hire', 'resumed', 'lease_in') NOT NULL DEFAULT 'normal'"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE yard_storage SET hire_type = 'normal' WHERE hire_type = 'lease_in'");
        DB::statement(
            "ALTER TABLE yard_storage MODIFY COLUMN hire_type "
            . "ENUM('normal', 'on_hire', 'resumed') NOT NULL DEFAULT 'normal'"
        );

        Schema::table('lessor_on_hires', function (Blueprint $t) {
            $t->dropForeign(['original_yard_storage_id']);
            $t->dropForeign(['resumed_yard_storage_id']);
            $t->dropColumn([
                'on_hire_mode',
                'original_yard_storage_id',
                'resumed_yard_storage_id',
                'original_gate_in_date',
                'expected_off_hire_date',
            ]);
        });
    }
};
