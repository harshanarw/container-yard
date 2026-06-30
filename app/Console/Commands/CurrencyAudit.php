<?php

namespace App\Console\Commands;

use App\Services\Finance\CurrencyAuditService;
use Illuminate\Console\Command;

/**
 * Read-only audit that surfaces foreign-currency journals which may have been
 * posted before the multi-currency fixes (storage/handling double-conversion,
 * or a foreign document booked at the silent 1.0 fallback rate).
 *
 * Makes NO changes — it only reports. The remediation step consumes the same
 * CurrencyAuditService::scan().
 */
class CurrencyAudit extends Command
{
    protected $signature = 'finance:currency-audit
        {--issue= : Filter to one issue type: base_mismatch | suspect_rate}
        {--limit=0 : Max rows to print per issue (0 = all)}';

    protected $description = 'Audit posted journals for foreign-currency conversion problems (read-only)';

    public function handle(CurrencyAuditService $audit): int
    {
        $result   = $audit->scan();
        $base     = $result['base'];
        $findings = collect($result['findings']);
        $counts   = $result['counts'];

        $this->info("Currency audit — base currency: {$base}");
        $this->line(sprintf(
            'Found %d issue(s): %d base mismatch, %d suspect rate.',
            $counts['total'], $counts['base_mismatch'], $counts['suspect_rate']
        ));
        $this->newLine();

        if ($findings->isEmpty()) {
            $this->info('No currency issues found. The ledger looks consistent.');
            return self::SUCCESS;
        }

        $issueFilter = $this->option('issue');
        $limit       = (int) $this->option('limit');

        foreach (['base_mismatch', 'suspect_rate'] as $issue) {
            if ($issueFilter && $issueFilter !== $issue) {
                continue;
            }

            $rows = $findings->where('issue', $issue)->values();
            if ($rows->isEmpty()) {
                continue;
            }

            $heading = $issue === 'base_mismatch'
                ? 'BASE MISMATCH — posted base ≠ expected (storage/handling double-conversion shows ratio ≈ rate)'
                : 'SUSPECT RATE — foreign-currency document booked at rate ≤ 1 (likely silent 1.0 fallback)';
            $this->warn($heading);

            $shown = $limit > 0 ? $rows->take($limit) : $rows;

            $this->table(
                ['Document', 'No', 'Journal', 'Ccy', 'Rate', 'Expected', 'Posted', 'Note'],
                $shown->map(fn ($f) => [
                    $f['doc'],
                    $f['no'],
                    $f['journal_no'] ?? '—',
                    $f['currency'],
                    rtrim(rtrim(number_format((float) $f['rate'], 6, '.', ''), '0'), '.'),
                    $f['expected'] !== null ? number_format((float) $f['expected'], 2) : '—',
                    $f['actual'] !== null ? number_format((float) $f['actual'], 2) : '—',
                    $f['note'] ?? '',
                ])->all()
            );

            if ($limit > 0 && $rows->count() > $limit) {
                $this->line('  … ' . ($rows->count() - $limit) . ' more (raise --limit to see all).');
            }
            $this->newLine();
        }

        $this->line('This audit made no changes. Review the rows above before any remediation.');

        return self::SUCCESS;
    }
}
