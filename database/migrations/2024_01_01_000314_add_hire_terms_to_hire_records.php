<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agreement terms that sit alongside the tiers.
 *
 * **`partial_tier_rule`** decides what a stretch of days that does not fill a
 * whole monthly block costs. A 20-day hire against "first month monthly, then
 * daily" can legitimately cost three different amounts, and all three appear in
 * real contracts:
 *
 *   daily_fallback — pass them to the next tier. 20 x daily.
 *   whole_block    — the month is a block and it was bought. One month.
 *   pro_rata       — month x 20/30.
 *
 * It lives **on the agreement**, not on the invoice, for three reasons. It is a
 * contract term, so the same 20-day hire must not cost different amounts
 * depending on who opened the billing screen. A dispute needs a stored answer to
 * "why was I charged this". And a long hire with an unknown end date needs
 * *interim* bills that run unattended — if every partial block needed a human
 * decision, periodic billing could not run on a schedule at all.
 *
 * An override at billing time is still wanted and comes with the billing screen
 * (phase 5), recorded: who, when, from what, and why. What it must not be is
 * silent.
 *
 * Per agreement rather than global because the two directions differ in
 * practice: a shipping line may impose `whole_block` on the yard while the yard
 * offers `daily_fallback` to its customer.
 *
 * **`hire_currency`** because rates without one are ambiguous in a system that
 * already issues invoices in several. Null falls back to the company's base
 * currency.
 *
 * Both default so every existing row stays valid and nothing is backfilled.
 */
return new class extends Migration
{
    private const TABLES = ['lessor_on_hires', 'container_hires'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->enum('partial_tier_rule', ['daily_fallback', 'whole_block', 'pro_rata'])
                  ->default('daily_fallback')
                  ->after('status');

                $t->char('hire_currency', 3)->nullable()->after('partial_tier_rule');
            });
        }

        // What "a month" means for a monthly tier. 30 days flat is the common
        // choice and the one the requirement's worked example implies (37 days
        // = one month + seven), but lessors differ -- a calendar month from the
        // 15th runs 31 days, not 30 -- so it is a setting rather than a
        // constant buried in the arithmetic.
        Schema::table('company_settings', function (Blueprint $t) {
            $t->unsignedSmallInteger('hire_month_days')->default(30)->after('enforce_reefer_plug_session');
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['partial_tier_rule', 'hire_currency']);
            });
        }

        Schema::table('company_settings', function (Blueprint $t) {
            $t->dropColumn('hire_month_days');
        });
    }
};
