<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Models\User;
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
        $workOrder->load('estimate', 'container', 'customer', 'assignedTo', 'lines.componentCode', 'lines.damageCode', 'lines.repairCode', 'createdBy');

        return view('work-orders.show', [
            'workOrder' => $workOrder,
            'canEdit'   => in_array($workOrder->status, ['pending', 'in_progress', 'on_hold']),
            'canDelete' => $workOrder->status === 'pending',
            'canStart'  => $workOrder->status === 'pending',
            'canComplete' => in_array($workOrder->status, ['in_progress', 'on_hold']),
            'canClose'  => $workOrder->status === 'completed',
        ]);
    }

    public function edit(WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, ['pending', 'in_progress', 'on_hold'])) {
            return redirect()->route('work-orders.show', $workOrder)->with('error', 'Cannot edit a ' . $workOrder->status . ' work order.');
        }

        $supervisors = User::where('role', 'yard_supervisor')->orWhere('role', 'admin')->get();

        return view('work-orders.edit', [
            'workOrder'   => $workOrder,
            'supervisors' => $supervisors,
            'statuses'    => ['pending', 'in_progress', 'on_hold', 'completed', 'closed', 'cancelled'],
            'priorities'  => ['normal', 'urgent', 'critical'],
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, ['pending', 'in_progress', 'on_hold'])) {
            return redirect()->route('work-orders.show', $workOrder)->with('error', 'Cannot edit a ' . $workOrder->status . ' work order.');
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'status'      => 'required|in:pending,in_progress,on_hold,completed,closed,cancelled',
            'priority'    => 'required|in:normal,urgent,critical',
            'target_date' => 'nullable|date',
            'instructions' => 'nullable|string|max:500',
            'technician_notes' => 'nullable|string|max:500',
        ]);

        $workOrder->update($validated);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order updated successfully.');
    }

    public function destroy(WorkOrder $workOrder)
    {
        if ($workOrder->status !== 'pending') {
            return redirect()->route('work-orders.show', $workOrder)->with('error', 'Only pending work orders can be deleted.');
        }

        $wo_no = $workOrder->wo_no;
        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('success', "Work order {$wo_no} deleted.");
    }

    public function updateStatus(Request $request, WorkOrder $workOrder)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,on_hold,completed,closed,cancelled',
        ]);

        $oldStatus = $workOrder->status;
        $newStatus = $validated['status'];

        // Validate state transitions
        $validTransitions = [
            'pending'     => ['in_progress', 'cancelled'],
            'in_progress' => ['on_hold', 'completed', 'cancelled'],
            'on_hold'     => ['in_progress', 'completed', 'cancelled'],
            'completed'   => ['closed'],
            'closed'      => [],
            'cancelled'   => [],
        ];

        if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
            return back()->with('error', "Cannot transition from $oldStatus to $newStatus.");
        }

        $updateData = ['status' => $newStatus];

        // Set dates based on transition
        if ($newStatus === 'in_progress' && !$workOrder->started_date) {
            $updateData['started_date'] = now()->toDateString();
        }
        if ($newStatus === 'completed' && !$workOrder->completed_date) {
            $updateData['completed_date'] = now()->toDateString();
        }
        if ($newStatus === 'closed') {
            $updateData['closed_by'] = auth()->id();
        }

        $workOrder->update($updateData);

        $action = match($newStatus) {
            'in_progress' => 'started',
            'completed'   => 'completed',
            'closed'      => 'closed',
            'on_hold'     => 'put on hold',
            'cancelled'   => 'cancelled',
            default       => 'updated',
        };

        return redirect()->route('work-orders.show', $workOrder)->with('success', "Work order {$action}.");
    }
}
