<?php

namespace App\Http\Controllers;

use App\Models\RepairCategory;
use App\Models\WorkOrder;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RepairCategoryResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:work-orders.view')->only(['index', 'show', 'availableCategories', 'previewLines']);
        $this->middleware('can:work-orders.create')->only(['create', 'store']);
        $this->middleware('can:work-orders.edit')->only(['edit', 'update', 'updateStatus', 'submitQc']);
        $this->middleware('can:work-orders.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = WorkOrder::with('estimate', 'container', 'customer', 'assignedTo', 'repairCategory');

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
            'statuses'   => ['pending', 'in_progress', 'on_hold', 'completed', 'rejected', 'closed', 'cancelled'],
        ]);
    }

    public function create(Request $request)
    {
        // Estimates eligible: approved + still have unassigned line items
        $approvedEstimates = \App\Models\Estimate::with('customer')
            ->where('status', 'approved')
            ->whereHas('lineItems', function ($q) {
                $q->whereDoesntHave('workOrderLine');
            })
            ->orderByDesc('created_at')
            ->get();

        $supervisors = User::whereIn('role', ['yard_supervisor', 'administrator', 'system_administrator'])->get();
        $categories  = RepairCategory::active()->get();

        // Pre-select estimate when coming from the estimate show page
        $preselectedEstimateId = $request->query('estimate_id');

        return view('work-orders.create', [
            'approvedEstimates'     => $approvedEstimates,
            'supervisors'           => $supervisors,
            'priorities'            => ['normal', 'urgent', 'critical'],
            'categories'            => $categories,
            'preselectedEstimateId' => $preselectedEstimateId,
        ]);
    }

    /**
     * AJAX: return available repair categories for an estimate (those with unassigned lines).
     */
    public function availableCategories(\App\Models\Estimate $estimate)
    {
        // Auto-resolve category for any unassigned lines that still have null repair_category_id
        $uncategorised = $estimate->lineItems()
            ->whereDoesntHave('workOrderLine')
            ->whereNull('repair_category_id')
            ->get();

        if ($uncategorised->isNotEmpty()) {
            $resolver = new RepairCategoryResolver();
            foreach ($uncategorised as $line) {
                $category = $resolver->resolve(
                    $line->component_code_id ? (int) $line->component_code_id : null,
                    $line->repair_type
                );
                if ($category) {
                    $line->update(['repair_category_id' => $category->id]);
                }
            }
        }

        $lines = $estimate->lineItems()
            ->whereDoesntHave('workOrderLine')
            ->whereNotNull('repair_category_id')
            ->with('repairCategory')
            ->get();

        $categories = $lines->groupBy('repair_category_id')->map(function ($group) {
            $cat = $group->first()->repairCategory;
            return [
                'id'         => $cat->id,
                'code'       => $cat->code,
                'name'       => $cat->name,
                'color'      => $cat->color,
                'line_count' => $group->count(),
            ];
        })->values();

        $uncatCount = $estimate->lineItems()
            ->whereDoesntHave('workOrderLine')
            ->whereNull('repair_category_id')
            ->count();

        return response()->json([
            'categories'          => $categories,
            'uncategorised_count' => $uncatCount,
        ]);
    }

    /**
     * AJAX: preview line items for a given estimate + category.
     */
    public function previewLines(\App\Models\Estimate $estimate, RepairCategory $repairCategory)
    {
        $lines = $estimate->lineItems()
            ->where('repair_category_id', $repairCategory->id)
            ->whereDoesntHave('workOrderLine')
            ->with('componentCode', 'damageCode', 'repairCode', 'locationCode')
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'component'   => $l->component ?? $l->componentCode?->name ?? '—',
                'damage'      => $l->damageCode?->name ?? '—',
                'repair'      => $l->repairCode?->name ?? $l->repair_type ?? '—',
                'location'    => $l->locationCode?->name ?? '—',
                'qty'         => $l->qty,
                'line_amount' => $l->line_amount,
            ]);

        return response()->json(['lines' => $lines]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'estimate_id'        => 'required|exists:estimates,id',
            'repair_category_id' => 'required|exists:repair_categories,id',
            'assigned_to'        => 'nullable|exists:users,id',
            'priority'           => 'required|in:normal,urgent,critical',
            'target_date'        => 'nullable|date',
            'instructions'       => 'nullable|string|max:500',
        ]);

        $estimate = \App\Models\Estimate::with('customer', 'lineItems')->findOrFail($validated['estimate_id']);

        if ($estimate->status !== 'approved') {
            return back()->withErrors(['estimate_id' => 'Only approved estimates can have work orders.'])->withInput();
        }

        if (WorkOrder::where('estimate_id', $estimate->id)
                     ->where('repair_category_id', $validated['repair_category_id'])
                     ->whereNotIn('status', ['cancelled'])
                     ->exists()) {
            return back()->withErrors(['repair_category_id' => 'A work order for this category already exists on this estimate.'])->withInput();
        }

        $lines = $estimate->lineItems()
            ->where('repair_category_id', $validated['repair_category_id'])
            ->whereDoesntHave('workOrderLine')
            ->get();

        if ($lines->isEmpty()) {
            return back()->withErrors(['repair_category_id' => 'No unassigned line items found for this category.'])->withInput();
        }

        $workOrder = DB::transaction(function () use ($estimate, $lines, $validated) {
            $woNo = app(\App\Services\NumberSequenceService::class)->generate('work_order');

            $workOrder = WorkOrder::create([
                'wo_no'              => $woNo,
                'estimate_id'        => $estimate->id,
                'yard_job_id'        => $estimate->yard_job_id ?: \App\Services\JobResolver::forEstimate($estimate->id),
                'container_id'       => $estimate->container_id,
                'container_no'       => $estimate->container_no,
                'customer_id'        => $estimate->customer_id,
                'repair_category_id' => $validated['repair_category_id'],
                'assigned_to'        => $validated['assigned_to'],
                'status'             => 'pending',
                'priority'           => $validated['priority'],
                'target_date'        => $validated['target_date'],
                'instructions'       => $validated['instructions'],
                'created_by'         => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $workOrder->lines()->create([
                    'estimate_line_item_id' => $line->id,
                    'location_code_id'      => $line->location_code_id,
                    'component_code_id'     => $line->component_code_id,
                    'damage_code_id'        => $line->damage_code_id,
                    'repair_code_id'        => $line->repair_code_id,
                    'cedex_code'            => $line->cedex_code,
                    'qty'                   => $line->qty ?? 1,
                    'status'                => 'pending',
                ]);
            }

            // Repair work is starting → move the container into 'in_repair'.
            if ($estimate->container) {
                app(\App\Services\ContainerStatusService::class)->markInRepair($estimate->container);
            }

            return $workOrder;
        });

        NotificationService::notifyAll(
            'Work Order Created — ' . $workOrder->wo_no,
            ($estimate->customer->name ?? 'Unknown') . ' · ' . $estimate->container_no . ' · ' . $workOrder->repairCategory->name,
            'info',
            route('work-orders.show', $workOrder)
        );

        return redirect()->route('work-orders.show', $workOrder)
                         ->with('success', "Work order {$workOrder->wo_no} created for: {$workOrder->repairCategory->name}.");
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(
            'estimate', 'container', 'customer', 'assignedTo', 'repairCategory',
            'lines.componentCode', 'lines.damageCode', 'lines.repairCode', 'lines.locationCode', 'lines.qcBy',
            'createdBy', 'qcBy'
        );

        $isQcRole = in_array(auth()->user()->role ?? '', ['yard_supervisor', 'administrator', 'system_administrator']);

        return view('work-orders.show', [
            'workOrder'      => $workOrder,
            'canEdit'        => in_array($workOrder->status, ['pending', 'in_progress', 'on_hold', 'rejected']),
            'canDelete'      => $workOrder->status === 'pending',
            'canStart'       => $workOrder->status === 'pending',
            'canComplete'    => in_array($workOrder->status, ['in_progress', 'on_hold']),
            'canQc'          => $workOrder->status === 'completed' && $isQcRole,
            'canStartRework' => $workOrder->status === 'rejected',
        ]);
    }

    public function edit(WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, ['pending', 'in_progress', 'on_hold', 'rejected'])) {
            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', 'Cannot edit a ' . $workOrder->status . ' work order.');
        }

        $supervisors = User::whereIn('role', ['yard_supervisor', 'administrator', 'system_administrator'])->get();

        return view('work-orders.edit', [
            'workOrder'   => $workOrder,
            'supervisors' => $supervisors,
            'statuses'    => ['pending', 'in_progress', 'on_hold', 'completed', 'rejected', 'closed', 'cancelled'],
            'priorities'  => ['normal', 'urgent', 'critical'],
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder)
    {
        if (!in_array($workOrder->status, ['pending', 'in_progress', 'on_hold', 'rejected'])) {
            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', 'Cannot edit a ' . $workOrder->status . ' work order.');
        }

        $validated = $request->validate([
            'assigned_to'      => 'nullable|exists:users,id',
            'status'           => 'required|in:pending,in_progress,on_hold,completed,rejected,closed,cancelled',
            'priority'         => 'required|in:normal,urgent,critical',
            'target_date'      => 'nullable|date',
            'instructions'     => 'nullable|string|max:500',
            'technician_notes' => 'nullable|string|max:500',
        ]);

        $workOrder->update($validated);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order updated.');
    }

    public function destroy(WorkOrder $workOrder)
    {
        if ($workOrder->status !== 'pending') {
            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', 'Only pending work orders can be deleted.');
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

        $validTransitions = [
            'pending'     => ['in_progress', 'cancelled'],
            'in_progress' => ['on_hold', 'completed', 'cancelled'],
            'on_hold'     => ['in_progress', 'completed', 'cancelled'],
            'completed'   => [],        // closing only via submitQc()
            'rejected'    => ['in_progress'],  // start rework
            'closed'      => [],
            'cancelled'   => [],
        ];

        if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
            return back()->with('error', "Cannot transition from $oldStatus to $newStatus.");
        }

        $updateData = ['status' => $newStatus];

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
            'in_progress' => $oldStatus === 'rejected' ? 'sent back for rework' : 'started',
            'completed'   => 'marked as complete — awaiting QC',
            'on_hold'     => 'put on hold',
            'cancelled'   => 'cancelled',
            default       => 'updated',
        };

        $notifType = match($newStatus) {
            'completed'   => 'success',
            'cancelled'   => 'warning',
            'on_hold'     => 'warning',
            default       => 'info',
        };

        NotificationService::notifyAll(
            'Work Order ' . ucfirst(str_replace('_', ' ', $newStatus)) . ' — ' . $workOrder->wo_no,
            ($workOrder->customer->name ?? 'Unknown') . ' · ' . $workOrder->container_no . ' · ' . ucfirst($action),
            $notifType,
            route('work-orders.show', $workOrder)
        );

        return redirect()->route('work-orders.show', $workOrder)->with('success', "Work order {$action}.");
    }

    public function submitQc(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status !== 'completed') {
            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', 'QC can only be submitted for completed work orders.');
        }

        if (!in_array(auth()->user()->role ?? '', ['yard_supervisor', 'administrator', 'system_administrator'])) {
            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', 'Only supervisors and administrators can perform QC reviews.');
        }

        $validated = $request->validate([
            'line_results'    => 'required|array',
            'line_results.*'  => 'required|in:passed,failed',
            'line_qc_notes'   => 'nullable|array',
            'line_qc_notes.*' => 'nullable|string|max:500',
            'qc_notes'        => 'nullable|string|max:1000',
        ]);

        // Ensure every line has a result
        $lineIds      = $workOrder->lines->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $submittedIds = array_keys($validated['line_results']);
        if (array_diff($lineIds, $submittedIds)) {
            return back()->withErrors(['line_results' => 'Every line must have a QC result.'])->withInput();
        }

        $now      = now();
        $qcBy     = auth()->id();
        $anyFailed = false;

        foreach ($workOrder->lines as $line) {
            $result = $validated['line_results'][(string) $line->id] ?? null;
            $notes  = $validated['line_qc_notes'][(string) $line->id]  ?? null;

            if ($result === 'failed') {
                $anyFailed = true;
            }

            $line->update([
                'qc_status' => $result,
                'qc_notes'  => $notes,
                'qc_by'     => $qcBy,
                'qc_at'     => $now,
            ]);
        }

        $workOrder->update([
            'status'    => $anyFailed ? 'rejected' : 'closed',
            'qc_by'     => $qcBy,
            'qc_at'     => $now,
            'qc_notes'  => $validated['qc_notes'] ?? null,
            'closed_by' => $anyFailed ? null : $qcBy,
        ]);

        // QC passed → this repair is complete. Only return the container to the
        // available pool once NO other work order for it is still open (a container
        // can carry several work orders across repair categories). This WO is now
        // 'closed', so it is excluded from the open check.
        if (!$anyFailed && $workOrder->container) {
            $svc = app(\App\Services\ContainerStatusService::class);
            if (!$svc->hasOpenWorkOrder($workOrder->container)) {
                $svc->markAvailable($workOrder->container);
            }
        }

        if ($anyFailed) {
            $failCount = collect($validated['line_results'])->filter(fn($r) => $r === 'failed')->count();

            NotificationService::notifyAll(
                'QC Failed — ' . $workOrder->wo_no,
                ($workOrder->customer->name ?? 'Unknown') . ' · ' . $workOrder->container_no . ' · ' . $failCount . ' line(s) failed — returned for rework',
                'danger',
                route('work-orders.show', $workOrder)
            );

            return redirect()->route('work-orders.show', $workOrder)
                             ->with('error', "QC rejected: {$failCount} line(s) failed inspection. Work order returned for rework.");
        }

        NotificationService::notifyAll(
            'QC Passed — ' . $workOrder->wo_no,
            ($workOrder->customer->name ?? 'Unknown') . ' · ' . $workOrder->container_no . ' · Work order closed',
            'success',
            route('work-orders.show', $workOrder)
        );

        return redirect()->route('work-orders.show', $workOrder)
                         ->with('success', 'QC passed — work order closed.');
    }
}
