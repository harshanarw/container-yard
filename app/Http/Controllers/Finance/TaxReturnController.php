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

    public function exportVatSscl(Request $request)
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

        // Straight from the service the screen uses. A return that a file and a
        // screen could disagree about is a return nobody can file.
        $data = $this->service->build($from, $to);
        $base = $data['base'];

        return TabularExport::stream($request->input('format'), 'vat-sscl-return', [
            'Section', 'Row Type', 'Source', 'Documents',
            "Taxable Value ({$base})", "SSCL ({$base})", "VAT ({$base})",
        ], function () use ($data) {
            // Two tables and two settlement panels on screen; one sheet here,
            // with Section carrying what the layout used to.
            foreach (['Output' => 'output', 'Input' => 'input'] as $label => $key) {
                foreach ($data[$key]['rows'] as $row) {
                    yield [$label, 'Line', $row['label'], $row['count'],
                        $this->money($row['taxable']),
                        $this->money($row['sscl']),
                        $this->money($row['vat']),
                    ];
                }

                yield [$label, 'Section total', 'Total '.strtolower($label), '',
                    $this->money($data[$key]['taxable']),
                    $this->money($data[$key]['sscl']),
                    $this->money($data[$key]['vat']),
                ];
            }

            $s = $data['summary'];

            // Each settlement figure stays in its own column, so a reader can
            // sum a column and still be reading the same quantity throughout.
            yield ['Summary', 'Settlement', 'Output VAT (sales)', '', '', '', $this->money($s['output_vat'])];
            yield ['Summary', 'Settlement', 'Less: Input VAT (purchases)', '', '', '', $this->money($s['input_vat'])];
            yield ['Summary', 'Total',
                'Net VAT '.($s['net_vat_payable'] >= 0 ? 'Payable' : 'Refundable'),
                '', '', '', $this->money($s['net_vat_payable']),
            ];

            yield ['Summary', 'Settlement', 'Output SSCL (turnover)', '', '', $this->money($s['output_sscl']), ''];
            // Carried because the screen carries it, and labelled because a
            // column of SSCL figures with one uncreditable amount in it would
            // otherwise read as an offset. It is not one.
            yield ['Summary', 'Settlement', 'Input SSCL (paid, not creditable)', '', '', $this->money($s['input_sscl']), ''];
            yield ['Summary', 'Total', 'SSCL Payable', '', '', $this->money($s['sscl_payable']), ''];
        });
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
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
