<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change gl_entries.account_id FK from CASCADE to RESTRICT.
 *
 * Cascade-delete on account_id is dangerous: deleting an account would
 * silently wipe all its GL entries, destroying the audit trail permanently.
 * The controller-level guard in ChartOfAccountsController::destroy() blocks
 * this at the application layer, but the DB constraint provides the second
 * line of defence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->cascadeOnDelete();
        });
    }
};
