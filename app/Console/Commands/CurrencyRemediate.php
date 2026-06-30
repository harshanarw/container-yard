<?php

namespace App\Console\Commands;

use App\Models\InvoicePosting;
use App\Models\RepairInvoice;
use App\Models\ReeferElectricityInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\Finance\CurrencyAuditService;
use App\Services\Finance\InvoicePostingService;
use App\Services\Finance\SupplierInvoicePostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remediate the base_mismatch findings from CurrencyAuditService by voiding the
 * mis-posted journal and re-posting through the now-corrected posting services.
 *
 * Dry-run by default; pass --apply to make changes. suspect_rate findings are
 * NOT auto-fixed — the document's stored rate is wrong, so a human must set the
 * correct rate first, then re-run.
 */
class CurrencyRemediate extends Command
{
    protected $signature = 'finance:currency-remediate
        {--apply : Actually void and re-post (default is a dry run)}';

    protected $description = 'Void and re-post journals flagged as base-currency mismatches (dry-run by default)';

    private const AR_MODELS = [
        'storage'          => StorageInvoice::class,
        'storage-handling' => StorageHandlingInvoice::class,
        'reefer'           => ReeferElectricityInvoice::class,
        'repair'           => RepairInvoice::class,
    ];

    public function handle(
        CurrencyAuditService $audit,
        InvoicePostingService $invoicePosting,
        SupplierInvoicePostingService $supplierPosting,
    ): int {
        $apply = (bool) $this->option('apply');
        $result = $audit->scan();

        $mismatches = collect($result['findings'])->where('issue', 'base_mismatch')->values();
        $suspects   = collect($result['findings'])->where('issue', 'suspect_rate')->values();

        $this->info(($apply ? 'APPLY' : 'DRY-RUN') . " — base currency {$result['base']}");
        $this->line("{$mismatches->count()} base mismatch(es) to remediate; {$suspects->count()} suspect-rate item(s) need manual rate correction.");
        $this->newLine();

        if ($mismatches->isEmpty()) {
            $this->info('Nothing to remediate.');
        }

        if ($apply && $mismatches->isNotEmpty() &&
            ! $this->confirm("Void and re-post {$mismatches->count()} journal(s)? This changes the ledger.", false)) {
            $this->warn('Aborted. No changes made.');
            return self::SUCCESS;
        }

        $userId = 1; // system actor for batch remediation
        $fixed = 0;
        $failed = 0;

        foreach ($mismatches as $f) {
            $label = "{$f['doc']} {$f['no']} (journal {$f['journal_no']}): {$f['actual']} → {$f['expected']}";

            if (!$apply) {
                $this->line("  would re-post: {$label}");
                continue;
            }

            try {
                DB::transaction(function () use ($f, $invoicePosting, $supplierPosting, $userId) {
                    if ($f['doc'] === 'supplier-invoice') {
                        $inv = SupplierInvoice::findOrFail($f['id']);
                        $supplierPosting->void($inv, $userId, 'Currency remediation');
                        $supplierPosting->post($inv->fresh(), $userId);
                        return;
                    }

                    $class = self::AR_MODELS[$f['doc']] ?? null;
                    if (!$class) {
                        throw new \RuntimeException("Unknown document type {$f['doc']}");
                    }
                    $posting = InvoicePosting::where('invoice_type', $f['doc'])
                        ->where('invoice_id', $f['id'])
                        ->where('status', 'posted')
                        ->firstOrFail();
                    $invoicePosting->void($posting, $userId, 'Currency remediation');
                    $invoicePosting->post($class::findOrFail($f['id']), $f['doc'], $userId);
                });
                $fixed++;
                $this->info("  re-posted: {$label}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  FAILED: {$label} — {$e->getMessage()}");
            }
        }

        if ($suspects->isNotEmpty()) {
            $this->newLine();
            $this->warn('Manual rate correction needed (NOT auto-fixed):');
            foreach ($suspects as $s) {
                $this->line("  {$s['doc']} {$s['no']} — {$s['currency']} at rate {$s['rate']}. Set the correct rate, then re-post.");
            }
        }

        $this->newLine();
        $this->line($apply ? "Done. {$fixed} re-posted, {$failed} failed." : 'Dry run complete — re-run with --apply to make changes.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
