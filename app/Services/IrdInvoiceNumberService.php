<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\IrdInvoiceSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IrdInvoiceNumberService
{
    const CATEGORY_CODES = [
        'storage'          => 'STG',
        'handling'         => 'HDL',
        'storage_handling' => 'HND',
        'repair'           => 'REP',
        'reefer'           => 'REF',
    ];

    /**
     * Generate a gazette-compliant IRD Tax Invoice serial number.
     *
     * Format: YYMMM_{CompanyPrefix}{CategoryCode}_{NNNNN}
     * e.g.   26JUL_CYSTG_00001
     *
     * All invoice categories share one global sequence, controlled by the
     * sequence reset policy in company settings (continuous / monthly / yearly).
     */
    public function generate(string $type, ?Carbon $invoiceDate = null): string
    {
        $date    = $invoiceDate ?? now();
        $company = CompanySetting::current();
        $reset   = $company->ird_sequence_reset ?? 'continuous';

        $companyPrefix = strtoupper($company->company_prefix ?? '');
        $catCode       = self::CATEGORY_CODES[$type] ?? strtoupper($type);
        $qqqq          = $companyPrefix . $catCode;

        $period = match ($reset) {
            'monthly' => $date->format('Ym'),    // e.g. '202607'
            'yearly'  => $date->format('Y'),     // e.g. '2026'
            default   => '0',                    // single ever-growing sequence
        };

        $seq = DB::transaction(function () use ($period) {
            $row = IrdInvoiceSequence::lockForUpdate()->firstOrCreate(
                ['period' => $period],
                ['last_number' => 0]
            );
            $row->increment('last_number');
            return $row->fresh()->last_number;
        });

        $yy     = $date->format('y');
        $mmm    = strtoupper($date->format('M'));
        $serial = str_pad($seq, 5, '0', STR_PAD_LEFT);

        return "{$yy}{$mmm}_{$qqqq}_{$serial}";
    }
}
