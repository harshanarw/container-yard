<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\VatSsclReturnService;
use App\Services\Finance\WhtReportService;
use App\Support\Export\TabularExport;
use Illuminate\Http\Request;

/**
 * VAT / SSCL return report — output tax collected on sales vs input VAT paid on
 * purchases, netted to the VAT payable and the (non-creditable) SSCL liability
 * for a filing period.
 */
class TaxReturnController extends Controller
{
    public function __construct(private VatSsclReturnService $service) {}

    public function vatSscl(Request $request)
    {
        $this->authorize('finance.gl.view');

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());

        $data = $this->service->build($from, $to);

        return view('finance.reports.vat-sscl-return', compact('data', 'from', 'to'));
    }

    public function wht(Request $request, WhtReportService $wht)
    {
        $this->authorize('finance.gl.view');

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());

        $data = $wht->build($from, $to);

        return view('finance.reports.wht-report', compact('data', 'from', 'to'));
    }

    public function exportWht(Request $request, WhtReportService $wht)
    {
        // Repeated rather than inherited: authorization in this controller is
        // per-action, so an export that omits it is simply open.
        $this->authorize('finance.gl.view');

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());

        // Straight from the service the screen uses — the file never re-derives
        // a tax figure.
        $data = $wht->build($from, $to);

        return TabularExport::stream($request->input('format'), 'wht-report', [
            'Section', 'Row Type', 'Date', 'Reference', 'Party', 'Nature',
            'Rate %', 'Gross', 'WHT', 'Net',
        ], function () use ($data) {
            // Grouped by party under two sections, so the structure rides in
            // columns rather than in separate sheets a CSV could not hold.
            foreach (['Payable' => 'payable', 'Receivable' => 'receivable'] as $label => $key) {
                $section = $data[$key];

                foreach ($section['parties'] as $party) {
                    foreach ($party['rows'] as $r) {
                        yield [
                            $label, 'Transaction',
                            $r['date'],
                            $r['no'],
                            $r['party'],
                            $r['nature'],
                            number_format((float) $r['rate'], 2, '.', ''),
                            number_format((float) $r['gross'], 2, '.', ''),
                            number_format((float) $r['wht'], 2, '.', ''),
                            number_format((float) $r['net'], 2, '.', ''),
                        ];
                    }

                    yield [
                        $label, 'Party total', '', '', $party['party'], '', '',
                        number_format((float) $party['gross'], 2, '.', ''),
                        number_format((float) $party['wht'], 2, '.', ''),
                        number_format((float) $party['net'], 2, '.', ''),
                    ];
                }

                yield [
                    $label, 'Section total', '', '', '', '', '',
                    number_format((float) $section['gross'], 2, '.', ''),
                    number_format((float) $section['wht'], 2, '.', ''),
                    number_format((float) $section['net'], 2, '.', ''),
                ];
            }
        });
    }
}
