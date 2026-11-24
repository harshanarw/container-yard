<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a laden container left with a different seal from the one it arrived
 * with.
 *
 * The gate-out now pre-fills the seal from the gate-in (the visit), instead of
 * asking the clerk to retype it. On the reference copy of live, 193 of 869
 * laden visits left with a seal that differed from their arrival's, 105 of
 * them only by an `I` / `IN` prefix: retyping noise, not a new seal.
 *
 * Seals do get replaced legitimately (customs examination, a broken seal, a
 * re-stuffed box), so a different seal is allowed. But it must say why
 * (decision D9 in docs/architecture-gap-plan.md), and the reason is kept on
 * the departure, beside the seal it explains, and printed on the gate pass.
 *
 * Null on every existing row and on every departure whose seal matches its
 * arrival, or whose arrival had no seal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('seal_change_reason', 255)->nullable()->after('no_seal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn('seal_change_reason');
        });
    }
};
