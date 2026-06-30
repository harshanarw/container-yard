<?php

namespace App\Console\Commands;

use App\Models\InvoicePosting;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\CurrencyService;
use App\Services\Finance\InvoicePostingService;
use App\Services\Finance\SupplierInvoicePostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correct the exchange rate on a foreign-currency document that was booked at a
 * wrong rate (e.g. the silent 1.0 fallback flagged by finance:currency-audit),
 * then void and re-post its journal so the base-currency ledger is right.
 *
 * Dry-run by default; pass --apply to make changes.
 */
class FixDocumentRate extends Command
{
    protected $signature = 'finance:fix-document-rate
        {type : supplier-invoice|storage|storage-handling|reefer|repair}
        {ref : invoice number or numeric id}
        {rate : correct foreign->base exchange rate (e.g. 300)}
        {--apply : Actually update the rate and re-post (default is a dry run)}';

    protected $description = 'Set the correct exchange rate on a document and re-post its journal';

    private const AR_MODELS = [
        'storage'          => StorageInvoice::class,
        'storage-handling' => StorageHandlingInvoice::class,
        'reefer'           => ReeferElectricityInvoice::class,
        'repair'           => RepairInvoice::class,
    ];

    public function handle(InvoicePostingService $arPost, SupplierInvoicePostingService $supPost): int
    {
        $type  = $this->argument('type');
        $ref   = $this->argument('ref');
        $rate  = (float) $this->argument('rate');
        $apply = (bool) $this->option('apply');
        $base  = CurrencyService::defaultCurrency();

        if ($rate <= 0) {
            $this->error('Rate must be greater than zero.');
            return self::FAILURE;
        }

        $isSupplier = $type === 'supplier-invoice';
        if (!$isSupplier && !isset(self::AR_MODELS[$type])) {
            $this->error("Unknown type '{$type}'. Use: supplier-invoice, storage, storage-handling, reefer, repair.");
            return self::FAILURE;
        }

        $class = $isSupplier ? SupplierInvoice::class : self::AR_MODELS[$type];
        $doc   = $class::where('invoice_no', $ref)->first() ?? $class::find($ref);
        if (!$doc) {
            $this->error("{$type} '{$ref}' not found.");
            return self::FAILURE;
        }

        $currency = strtoupper((string) ($doc->invoice_currency ?? $doc->currency ?? $base));
        if ($currency === $base) {
            $this->warn("{$doc->invoice_no} is in the base currency ({$base}); no rate correction needed.");
            return self::SUCCESS;
        }

        $docTotal = (float) ($type === 'repair' ? ($doc->grand_total ?? 0) : ($doc->total_amount ?? 0));
        // For storage/handling the persisted total is already base; the document
        // amount is total / oldRate. For the others the persisted total is the
        // document amount.
        $oldRate = (float) ($doc->exchange_rate ?: 1);
        $docAmt  = in_array($type, ['storage', 'storage-handling'], true)
            ? ($oldRate > 0 ? round($docTotal / $oldRate, 2) : $docTotal)
            : $docTotal;
        $newBase = round($docAmt * $rate, 2);

        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . " — {$type} {$doc->invoice_no}");
        $this->table(
            ['Currency', 'Doc amount', 'Old rate', 'New rate', 'New base (' . $base . ')'],
            [[$currency, number_format($docAmt, 2), $oldRate, $rate, number_format($newBase, 2)]]
        );

        if (!$apply) {
            $this->line('Dry run — re-run with --apply to update the rate and re-post.');
            return self::SUCCESS;
        }

        if (!$this->confirm("Update {$doc->invoice_no} to rate {$rate} and re-post its journal?", false)) {
            $this->warn('Aborted. No changes made.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($doc, $type, $rate, $isSupplier, $arPost, $supPost) {
                if ($isSupplier) {
                    if ($doc->isPosted()) {
                        $supPost->void($doc, 1, 'Exchange-rate correction');
                    }
                    $doc->update(['exchange_rate' => $rate]);
                    $supPost->post($doc->fresh(), 1);
                    return;
                }

                $posting = InvoicePosting::where('invoice_type', $type)
                    ->where('invoice_id', $doc->id)
                    ->where('status', 'posted')
                    ->first();
                if ($posting) {
                    $arPost->void($posting, 1, 'Exchange-rate correction');
                }
                $doc->update(['exchange_rate' => $rate]);
                $arPost->post($doc->fresh(), $type, 1);
            });
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Done. {$doc->invoice_no} re-posted at rate {$rate}. Re-run finance:currency-audit to confirm it clears.");

        return self::SUCCESS;
    }
}
