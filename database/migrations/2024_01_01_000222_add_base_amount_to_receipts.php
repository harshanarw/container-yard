<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Persist the base/reporting-currency (e.g. LKR) value of each receipt as a
| snapshot, instead of only deriving it from amount × exchange_rate at posting
| time. base_amount = amount × exchange_rate at the time of entry.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->decimal('base_amount', 18, 4)->nullable()->after('exchange_rate');
        });

        DB::table('receipts')->update(['base_amount' => DB::raw('ROUND(amount * exchange_rate, 4)')]);
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('base_amount');
        });
    }
};
