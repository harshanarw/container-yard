<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkOrder::with('estimate', 'container', 'customer', 'assignedTo');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('work_order_no', 'like', "%{$search}%")
                  ->orWhereHas('container', fn($sq) => $sq->where('container_no', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $workOrders = $query->orderByDesc('created_at')->paginate(20);

        return view('work-orders.index', [
            'workOrders' => $workOrders,
            'statuses' => ['scheduled', 'in_progress', 'completed', 'on_hold', 'cancelled'],
        ]);
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load('estimate.lineItems', 'container', 'customer', 'assignedTo', 'lines');

        return view('work-orders.show', [
            'workOrder' => $workOrder,
        ]);
    }
}
