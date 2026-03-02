<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardStorage;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function inventory(Request $request)
    {
        $containers = Container::with('customer')
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->size,        fn ($q, $v) => $q->where('size', $v))
            ->when($request->condition,   fn ($q, $v) => $q->where('condition', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_in_date', '<=', $v))
            ->orderBy('gate_in_date', 'desc')
            ->get();

        $summary = [
            'total'        => $containers->count(),
            'in_yard'      => $containers->where('status', 'in_yard')->count(),
            'in_repair'    => $containers->where('status', 'in_repair')->count(),
            'released'     => $containers->where('status', 'released')->count(),
            'by_size_20'   => $containers->where('size', '20')->count(),
            'by_size_40'   => $containers->where('size', '40')->count(),
            'by_size_45'   => $containers->where('size', '45')->count(),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.inventory', compact('containers', 'summary', 'customers'));
    }

    public function billing(Request $request)
    {
        $storageRecords = YardStorage::with(['container', 'customer'])
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('gate_in_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('gate_out_date', '<=', $v))
            ->whereNotNull('gate_out_date')
            ->orderBy('gate_out_date', 'desc')
            ->get();

        $summary = [
            'total_records'    => $storageRecords->count(),
            'total_revenue'    => $storageRecords->sum('total_charge'),
            'total_days'       => $storageRecords->sum('total_days'),
            'avg_stay'         => $storageRecords->avg('total_days'),
        ];

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('reports.billing', compact('storageRecords', 'summary', 'customers'));
    }
}
