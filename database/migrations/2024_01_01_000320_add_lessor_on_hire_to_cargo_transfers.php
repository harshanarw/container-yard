<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which lease a substitution was made under.
 *
 * `cargo_transfers.substitute_source` has always had an `on_hired` value, and
 * 000270 added `container_hire_id` beside it with the note *"kept for a later
 * phase"*. This is that phase, and the column it left behind points at the
 * wrong model.
 *
 * `ContainerHire` is the yard as **lessor** — the box going out to a renting
 * customer, AR. A substitute the yard is holding **on hire** is the opposite
 * direction: `LessorOnHire`, the yard as lessee, AP. The two were one concept
 * when 000270 was written and are not now. A substitution under a lease links
 * to the lease.
 *
 * It also stops `substitute_source` being a claim. Nothing wrote
 * `container_hire_id`, so the enum was whatever the operator picked on the
 * form, and it could say `on_hired` for a box the yard owns outright or
 * `yard_owned` for one it is paying rent on — with the P&L reading the same
 * either way. Derived from this link now, so the two cannot disagree.
 *
 * `container_hire_id` is left in place and unused. Dropping a column that live
 * rows might carry buys nothing; it is documented on the model instead, so the
 * next reader does not wire something to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_transfers', function (Blueprint $t) {
            $t->foreignId('lessor_on_hire_id')->nullable()->after('container_hire_id')
              ->constrained('lessor_on_hires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cargo_transfers', function (Blueprint $t) {
            $t->dropForeign(['lessor_on_hire_id']);
            $t->dropColumn('lessor_on_hire_id');
        });
    }
};
