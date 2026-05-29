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
                $q->where('wo_no', 'like', "%{$search}%")
                  ->orWhere('container_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $workOrders = $query->orderByDesc('created_at')->paginate(20);

        return view('work-orders.index', [
            'workOrders' => $workOrders,
            'statuses'   => ['pending', 'in_progress', 'on_hold', 'completed', 'closed', 'cancelled'],
        ]);
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load('estimate', 'container', 'customer', 'assignedTo', 'lines.componentCode', 'lines.damageCode', 'lines.repairCode');

        return view('work-orders.show', [
            'workOrder' => $workOrder,
        ]);
    }
}
