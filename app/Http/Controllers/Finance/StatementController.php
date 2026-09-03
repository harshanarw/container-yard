<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Finance\StatementService;
use App\Support\Export\TabularExport;
use Illuminate\Http\Request;

/**
 * Customer & supplier statements of account. Suppliers and customers are the
 * same unified Customer (contact) model.
 */
class StatementController extends Controller
{
    public function __construct(private StatementService $statements) {}

    public function customer(Request $request)
    {
        $this->authorize('finance.ar.view');

        return $this->render($request, 'customer', 'finance.reports.customer-statement');
    }

    public function supplier(Request $request)
    {
        $this->authorize('finance.ap.view');

        return $this->render($request, 'supplier', 'finance.reports.supplier-statement');
    }

    public function exportCustomer(Request $request)
    {
        // Repeated rather than inherited: authorization here is per-action, so
        // an export that omits it is simply open.
        $this->authorize('finance.ar.view');

        return $this->exportStatement($request, 'customer', 'customer-statement');
    }

    public function exportSupplier(Request $request)
    {
        $this->authorize('finance.ap.view');

        return $this->exportStatement($request, 'supplier', 'supplier-statement');
    }

    /**
     * A statement of account, as a file.
     *
     * Straight from the service the screen uses — a statement that disagreed
     * with the one on screen is worse than none, and this is the document a
     * customer reconciles against.
     */
    private function exportStatement(Request $request, string $side, string $basename)
    {
        $request->validate([
            'party_id' => 'required|exists:customers,id',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        $from  = $request->input('from', now()->startOfMonth()->toDateString());
        $to    = $request->input('to', now()->toDateString());
        $party = Customer::findOrFail($request->input('party_id'));
        $data  = $this->statements->{$side}((int) $party->id, $from, $to);

        // Named for the party: several statements in a downloads folder are
        // otherwise indistinguishable.
        $basename .= '-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($party->code ?: $party->name));

        return TabularExport::stream($request->input('format'), $basename, [
            'Date', 'Type', 'Detail', 'Reference', 'IRD No',
            'Currency', 'Doc Amount', 'Debit', 'Credit', 'Balance',
        ], function () use ($data) {
            // Opening and closing are context rather than transactions, but a
            // statement cannot be reconciled without them, so they bracket the
            // rows as labelled lines — which is how a printed statement reads.
            yield ['', 'Opening balance', '', '', '', $data['base'], '', '', '',
                number_format((float) $data['opening'], 2, '.', '')];

            foreach ($data['lines'] as $l) {
                yield [
                    $l['date'],
                    $l['type'],
                    $l['sub'],
                    $l['ref'],
                    $l['ird'] ?? '',
                    $l['currency'],
                    number_format((float) $l['doc_amount'], 2, '.', ''),
                    number_format((float) $l['debit'], 2, '.', ''),
                    number_format((float) $l['credit'], 2, '.', ''),
                    // The service tracks this per line, so the file reports it
                    // rather than recomputing and risking a different answer.
                    number_format((float) $l['balance'], 2, '.', ''),
                ];
            }

            yield ['', 'Totals', '', '', '', $data['base'], '',
                number_format((float) $data['debit_total'], 2, '.', ''),
                number_format((float) $data['credit_total'], 2, '.', ''),
                ''];

            yield ['', 'Closing balance', '', '', '', $data['base'], '', '', '',
                number_format((float) $data['closing'], 2, '.', '')];
        });
    }

    private function render(Request $request, string $side, string $view)
    {
        $request->validate([
            'party_id' => 'nullable|exists:customers,id',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        $parties = Customer::where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $party = null;
        $data  = null;
        if ($request->filled('party_id')) {
            $party = Customer::find($request->input('party_id'));
            if ($party) {
                $data = $this->statements->{$side}((int) $party->id, $from, $to);
            }
        }

        return view($view, compact('parties', 'party', 'data', 'from', 'to'));
    }
}
