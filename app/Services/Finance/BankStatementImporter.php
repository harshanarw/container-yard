<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use Carbon\Carbon;

/**
 * Parses an uploaded bank statement (CSV) into normalised BankStatementLine
 * rows using a column-mapping preset from config/bank_statement_formats.php.
 *
 * Matching is alias-based so the same importer handles every Sri Lankan bank
 * whose export is comma-separated with a header row; a bank only needs a preset
 * entry. Re-importing the same file is safe — each row carries a hash and
 * duplicates are skipped.
 */
class BankStatementImporter
{
    /**
     * @return array{imported:int,skipped:int,errors:array<int,string>}
     */
    public function import(string $path, string $presetKey, BankAccount $account, ?BankReconciliation $reconciliation, ?int $userId): array
    {
        $config  = config('bank_statement_formats');
        $presets = $config['presets'] ?? [];
        $preset  = $presets[$presetKey] ?? $presets[$config['default'] ?? 'generic'];

        $delimiter = $preset['delimiter'] ?? ',';
        $dateFormats = $preset['date_formats'] ?? ['Y-m-d', 'd/m/Y'];
        $aliases = $this->aliases($config, $preset);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Could not open the uploaded file.']];
        }

        $map = null;
        $rowNo = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNo++;

            // Skip fully blank lines.
            if ($row === [null] || count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            // First non-empty row is the header.
            if ($map === null) {
                $map = $this->resolveColumns($row, $aliases);
                if (!isset($map['date'])) {
                    $errors[] = 'No recognisable "date" column found in the header row.';
                    break;
                }
                continue;
            }

            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';

            $date = $this->parseDate($get('date'), $dateFormats);
            if (!$date) {
                // A trailing totals/footer line without a valid date — skip quietly.
                $skipped++;
                continue;
            }

            [$deposit, $withdrawal] = $this->resolveAmounts($map, $get, $preset);
            if ($deposit == 0.0 && $withdrawal == 0.0) {
                $skipped++;
                continue;
            }

            $description = $get('description');
            $reference   = $get('reference');
            $balance     = $this->number($get('balance'));

            $hash = hash('sha256', implode('|', [
                $account->id, $date->toDateString(), $description, $reference,
                number_format($deposit, 2, '.', ''), number_format($withdrawal, 2, '.', ''),
            ]));

            if (BankStatementLine::where('bank_account_id', $account->id)->where('row_hash', $hash)->exists()) {
                $skipped++;
                continue;
            }

            BankStatementLine::create([
                'bank_account_id'        => $account->id,
                'bank_reconciliation_id' => $reconciliation?->id,
                'txn_date'               => $date->toDateString(),
                'description'            => $description !== '' ? mb_substr($description, 0, 255) : null,
                'reference'              => $reference !== '' ? mb_substr($reference, 0, 100) : null,
                'deposit'                => $deposit,
                'withdrawal'             => $withdrawal,
                'balance'                => $get('balance') !== '' ? $balance : null,
                'status'                 => 'unmatched',
                'source'                 => $presetKey,
                'row_hash'               => $hash,
                'created_by'             => $userId,
            ]);
            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** Merge preset alias overrides over the global alias set. */
    private function aliases(array $config, array $preset): array
    {
        $base = $config['aliases'] ?? [];
        foreach (($preset['aliases'] ?? []) as $field => $list) {
            $base[$field] = $list;
        }

        return $base;
    }

    /** Build canonical-field → column-index map from the header row. */
    private function resolveColumns(array $header, array $aliases): array
    {
        $norm = [];
        foreach ($header as $idx => $label) {
            $norm[$this->key($label)] = $idx;
        }

        $map = [];
        foreach ($aliases as $field => $labels) {
            foreach ($labels as $label) {
                $k = $this->key($label);
                if (isset($norm[$k])) {
                    $map[$field] = $norm[$k];
                    break;
                }
            }
        }

        return $map;
    }

    /** @return array{0:float,1:float} [deposit, withdrawal] */
    private function resolveAmounts(array $map, callable $get, array $preset): array
    {
        $hasSplit = isset($map['withdrawal']) || isset($map['deposit']);

        if ($hasSplit) {
            return [abs($this->number($get('deposit'))), abs($this->number($get('withdrawal')))];
        }

        // Single signed amount column.
        if (isset($map['amount'])) {
            $amt = $this->number($get('amount'));
            $negativeIsWithdrawal = ($preset['amount_sign'] ?? 'withdrawal_negative') === 'withdrawal_negative';
            if ($negativeIsWithdrawal) {
                return $amt < 0 ? [0.0, abs($amt)] : [$amt, 0.0];
            }

            return $amt < 0 ? [abs($amt), 0.0] : [0.0, $amt];
        }

        return [0.0, 0.0];
    }

    private function parseDate(string $value, array $formats): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach ($formats as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $value);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }
        // Last resort: let Carbon guess (ISO-ish strings).
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Parse a money string: strips thousands separators, currency codes and (parentheses) = negative. */
    private function number(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $negative = false;
        if (preg_match('/^\((.*)\)$/', $value, $m)) {
            $negative = true;
            $value = $m[1];
        }
        if (preg_match('/(-|\bDR\b)\s*$/i', $value) || preg_match('/^\s*-/', $value)) {
            $negative = true;
        }

        // Keep digits and decimal point only.
        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || $clean === '.') {
            return 0.0;
        }

        $num = (float) $clean;

        return $negative ? -$num : $num;
    }

    private function key(?string $label): string
    {
        return strtolower(trim((string) $label));
    }
}
