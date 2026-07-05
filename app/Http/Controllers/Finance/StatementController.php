<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Finance\StatementService;
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
