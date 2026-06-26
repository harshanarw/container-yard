<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Generalise the SL-specific `bank_code` (CBSL/SLIPS) into a country-neutral
| `local_code` (national clearing/routing code: CBSL, IFSC, Sort Code, ABA…).
|
| Done as add → copy → drop rather than renameColumn() so it needs no
| doctrine/dbal, and runs cleanly whether or not the banks table already
| holds the original column.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('banks', 'local_code')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->string('local_code', 20)->nullable()->after('swift_code');
            });
        }

        if (Schema::hasColumn('banks', 'bank_code')) {
            DB::table('banks')->update(['local_code' => DB::raw('bank_code')]);
            Schema::table('banks', function (Blueprint $table) {
                $table->dropColumn('bank_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('banks', 'bank_code')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->string('bank_code', 20)->nullable()->after('swift_code');
            });
        }

        if (Schema::hasColumn('banks', 'local_code')) {
            DB::table('banks')->update(['bank_code' => DB::raw('local_code')]);
            Schema::table('banks', function (Blueprint $table) {
                $table->dropColumn('local_code');
            });
        }
    }
};
