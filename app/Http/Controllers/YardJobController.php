<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\JobPnlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class YardJobController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:yard.jobs.view')->only(['index', 'show']);
        $this->middleware('can:yard.jobs.edit')->only(['update']);
    }

    public function index(Request $request): View
    {
        $query = YardJob::with(['jobType', 'customer', 'createdBy'])
            ->withCount('movements');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('job_type_id')) {
            $query->where('job_type_id', $request->job_type_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('search')) {
            $query->where('job_no', 'like', '%' . $request->search . '%');
        }

        $jobs      = $query->latest()->paginate(25)->withQueryString();
        $jobTypes  = YardJobType::active()->forGateIn()->orderBy('sort_order')->get();
        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        $stats = [
            'total'       => YardJob::count(),
            'open'        => YardJob::where('status', 'open')->count(),
            'in_progress' => YardJob::where('status', 'in_progress')->count(),
            'completed'   => YardJob::where('status', 'completed')->count(),
        ];

        return view('yard.jobs.index', compact('jobs', 'jobTypes', 'customers', 'stats'));
    }

    public function show(YardJob $yardJob, JobPnlService $pnl): View
    {
        $yardJob->load([
            'jobType',
            'customer',
            'createdBy',
            'closedBy',
            'movements.container.equipmentType',
            'movements.createdBy',
        ]);

        $pnlData = $pnl->compute($yardJob);

        return view('yard.jobs.show', compact('yardJob', 'pnlData'));
    }

    public function update(Request $request, YardJob $yardJob): RedirectResponse
    {
        $data = $request->validate([
            'status'  => ['required', 'in:open,in_progress,completed,cancelled'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($data['status'], ['completed', 'cancelled']) && ! $yardJob->completed_at) {
            $data['completed_at'] = now();
            $data['closed_by']    = auth()->id();
        }

        // Reopen — clear close fields
        if (in_array($data['status'], ['open', 'in_progress'])) {
            $data['completed_at'] = null;
            $data['closed_by']    = null;
        }

        $yardJob->update($data);

        return back()->with('success', "Job {$yardJob->job_no} updated to \"" . YardJob::statusLabel($data['status']) . "\".");
    }
}
