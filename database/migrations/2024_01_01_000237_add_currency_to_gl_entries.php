<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency general ledger (Phase A).
 *
 * The GL is kept in the functional/base currency (debit/credit, LKR). These
 * additive columns record, per line, the original TRANSACTION currency, the
 * exchange rate frozen at posting, and the transaction-currency amounts — the
 * standard dual-amount ledger design. The base debit/credit remain the
 * authoritative figures, so every existing LKR report is unaffected.
 *
 * group_* columns are reserved (nullable, unpopulated) so a third
 * group/reporting currency can be added later without another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = strtoupper((string) (DB::table('company_settings')->value('default_currency_code') ?: 'LKR'));

        Schema::table('gl_journals', function (Blueprint $table) {
            // Optional document currency for single-currency journals (convenience
            // for filtering); line-level currency is the source of truth.
            $table->string('currency', 10)->nullable()->after('journal_type');
        });

        Schema::table('gl_entries', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('credit');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            $table->decimal('txn_debit', 18, 4)->default(0)->after('exchange_rate');
            $table->decimal('txn_credit', 18, 4)->default(0)->after('txn_debit');

            // Reserved for a future group/reporting currency (left null for now).
            $table->string('group_currency', 10)->nullable()->after('txn_credit');
            $table->decimal('group_debit', 18, 4)->nullable()->after('group_currency');
            $table->decimal('group_credit', 18, 4)->nullable()->after('group_debit');

            $table->index('currency');
        });

        // Backfill existing rows: every historical entry is base currency at rate 1,
        // and its transaction amounts equal its base amounts.
        DB::table('gl_entries')->update([
            'currency'      => $base,
            'exchange_rate' => 1,
        ]);
        DB::statement('UPDATE gl_entries SET txn_debit = debit, txn_credit = credit');
        DB::table('gl_journals')->whereNull('currency')->update(['currency' => $base]);
    }

    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropIndex(['currency']);
            $table->dropColumn([
                'currency', 'exchange_rate', 'txn_debit', 'txn_credit',
                'group_currency', 'group_debit', 'group_credit',
            ]);
        });

        Schema::table('gl_journals', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
