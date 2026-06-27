<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Record the base-currency (LKR) value applied by each AP allocation:
| base_amount = allocated_amount × the voucher's exchange rate.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->decimal('base_amount', 18, 4)->nullable()->after('allocated_amount');
        });

        DB::table('payment_allocations')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                $rate = (float) (DB::table('payment_vouchers')->where('id', $r->payment_voucher_id)->value('exchange_rate') ?? 1);
                DB::table('payment_allocations')->where('id', $r->id)
                    ->update(['base_amount' => round((float) $r->allocated_amount * ($rate ?: 1), 4)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropColumn('base_amount');
        });
    }
};
