<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\VatSsclReturnService;
use App\Services\Finance\WhtReportService;
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
}
