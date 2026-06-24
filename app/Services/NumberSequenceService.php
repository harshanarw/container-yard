<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Unified number sequence generator.
 *
 * Replaces the 13 scattered generators across controllers and models with a
 * single, consistent service backed by the number_sequences table.
 *
 * Format (all segments joined by the configured separator, default '-'):
 *   [{CompanyPrefix}] - {Prefix} - [{DatePart}] - {PaddedSequence}
 *
 * Company prefix is omitted when CompanySetting::company_prefix is blank,
 * ensuring backward-compatible output on new installations.
 *
 * Each generate() call runs inside DB::transaction with lockForUpdate() so
 * concurrent requests cannot claim the same number.
 */
class NumberSequenceService
{
    /**
     * Generate the next number for the given module and atomically increment
     * the stored counter.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function generate(string $moduleCode): string
    {
        return DB::transaction(function () use ($moduleCode) {
            $seq = NumberSequence::where('module_code', $moduleCode)
                ->lockForUpdate()
                ->firstOrFail();

            $period = $this->computePeriod($seq);

            // Roll the counter when the date period has changed.
            if ($seq->reset_period !== 'never' && $period !== $seq->current_period) {
                $seq->last_number    = 0;
                $seq->current_period = $period;
            }

            $number           = $seq->last_number + 1;
            $seq->last_number = $number;
            $seq->save();

            return $this->format($seq, $period, $number);
        });
    }

    /**
     * Return what the next generated number would look like without
     * incrementing the counter (useful for previews in the settings UI).
     */
    public function preview(string $moduleCode): string
    {
        $seq    = NumberSequence::where('module_code', $moduleCode)->firstOrFail();
        $period = $this->computePeriod($seq);

        $wouldReset = $seq->reset_period !== 'never' && $period !== $seq->current_period;
        $number     = $wouldReset ? 1 : $seq->last_number + 1;

        return $this->format($seq, $period, $number);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Compute the current period string for a sequence.
     * Returns empty string when no date_format is configured.
     * Note: reset_period='never' does NOT suppress the date — a sequence can
     * include a year stamp without auto-resetting its counter.
     */
    private function computePeriod(NumberSequence $seq): string
    {
        if (!$seq->date_format) {
            return '';
        }

        return now()->format($seq->date_format);
    }

    /**
     * Assemble the formatted document number from its constituent parts.
     */
    private function format(NumberSequence $seq, string $period, int $number): string
    {
        $sep   = $seq->separator ?: '-';
        $parts = [];

        $company = strtoupper(trim(CompanySetting::current()->company_prefix ?? ''));
        if ($seq->use_company_prefix && $company !== '') {
            $parts[] = $company;
        }

        $parts[] = $seq->prefix;

        if ($seq->date_format && $period !== '') {
            $parts[] = $period;
        }

        $parts[] = str_pad((string) $number, $seq->seq_padding, '0', STR_PAD_LEFT);

        return implode($sep, $parts);
    }
}
