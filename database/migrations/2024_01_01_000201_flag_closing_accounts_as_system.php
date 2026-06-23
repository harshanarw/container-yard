<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The P&L closing process posts to Retained Earnings (3002) and Current Year
 * P/L (3003). Flag them as system accounts so they can't be deleted or have
 * their codes changed out from under the ClosingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'is_system')) {
            DB::table('accounts')->whereIn('code', ['3002', '3003'])->update(['is_system' => true]);
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: un-flagging system accounts could allow
        // deletion of accounts the closing process depends on.
    }
};
