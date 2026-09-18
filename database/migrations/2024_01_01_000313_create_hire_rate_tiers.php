<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered rental rates — the foundation nothing else could be built on.
 *
 * Until now no rental rate was stored anywhere in this codebase: the hire-period
 * `YardStorage` row is created with `daily_rate => 0`, and
 * `lessor_on_hires.per_diem_rate` was the only rate field, unused for billing.
 * A sub-hire could therefore be recorded in full and billed for nothing.
 *
 * A rental is priced in **ordered steps by elapsed duration**: "the first 30
 * days at the monthly rate, daily thereafter". 37 days is one month plus seven
 * days. The tiers are duration-based rather than calendar-based on purpose —
 * the off-hire date is usually unknown when the agreement is written, and a
 * duration tier stays correct whether the box comes back on day 20 or day 200.
 *
 * `max_units` null means "everything left", so the last tier is normally
 * open-ended. A tier order of daily-then-monthly is equally valid; the sequence
 * and the unit are both the operator's choice.
 *
 * **The monthly rate is a block price, not thirty daily rates**, so both are
 * stored and neither is derived — see {@see \App\Services\Billing\HireTierPricing}
 * for the arithmetic and why.
 *
 * Polymorphic because the two directions have the same shape and must stay
 * comparable: what the shipping line charges the yard (`LessorOnHire`, AP) and
 * what the yard charges its customer (`ContainerHire`, AR). The margin is one
 * minus the other, which only works if both are priced the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hire_rate_tiers', function (Blueprint $t) {
            $t->id();

            // LessorOnHire (lease-in, AP) or ContainerHire (sub-hire, AR).
            $t->morphs('hireable');

            // Ordered. Tier 1 is consumed first.
            $t->unsignedSmallInteger('sequence');

            $t->enum('unit', ['monthly', 'daily']);
            $t->decimal('rate', 15, 2);

            // null = "everything left". The last tier is normally open-ended;
            // when every tier is capped and they do not cover the hire, the
            // pricing reports the shortfall rather than dropping the days.
            $t->unsignedSmallInteger('max_units')->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            // "The tiers of this agreement, in order" — the only read there is.
            $t->index(['hireable_type', 'hireable_id', 'sequence'], 'hire_rate_tiers_agreement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hire_rate_tiers');
    }
};
