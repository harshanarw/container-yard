<?php

use App\Services\Finance\PaymentTermsHelper;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `due_date` to the three AR invoice types that lacked it (storage,
 * storage-handling, reefer). Repair invoices already had one.
 *
 * Without a due date these invoices could not honour the customer's AR payment
 * terms and the debtors/aging report had to fall back to ageing off the invoice
 * date. Existing rows are backfilled as invoice_date + the debtor's AR terms so
 * historical receivables age correctly from day one.
 */
return new class extends Migration
{
    /** table => debtor foreign-key column */
    private array $targets = [
        'storage_invoices'          => 'customer_id',
        'storage_handling_invoices' => 'shipping_line_id',
        'reefer_electricity_invoices' => 'customer_id',
    ];

    public function up(): void
    {
        foreach (array_keys($this->targets) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->date('due_date')->nullable()->after('invoice_date');
            });
        }

        // Backfill from each debtor's AR payment terms.
        $terms = DB::table('customers')->pluck('payment_terms', 'id');

        foreach ($this->targets as $table => $custCol) {
            DB::table($table)
                ->select('id', 'invoice_date', $custCol)
                ->whereNull('due_date')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $custCol, $terms) {
                    foreach ($rows as $row) {
                        if (empty($row->invoice_date)) {
                            continue;
                        }
                        $custTerms = $terms[$row->{$custCol}] ?? 'net30';
                        $due = PaymentTermsHelper::dueDate(
                            $custTerms,
                            Carbon::parse($row->invoice_date)
                        )->toDateString();

                        DB::table($table)->where('id', $row->id)->update(['due_date' => $due]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->targets) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('due_date');
            });
        }
    }
};
