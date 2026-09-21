<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\JobPnlService;
use App\Services\NotificationService;
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
        $customers = Customer::selectable()->where('status', 'active')->orderBy('name')->get();

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

        // And the sub-jobs beneath it, where there are any.
        //
        // A job can now hold others — a lease-in under a stay, a re-let under
        // that lease — and the whole reason they are separate jobs is that the
        // directions differ: the lease is what the yard pays the line, each
        // re-let is what a renter pays the yard. A lease showing only its own
        // figures displays the cost with none of the revenue it was incurred to
        // earn, which reads as a pure loss.
        //
        // Passed *beside* `$pnlData` rather than replacing it: the view reads
        // that array key by key in the shape `compute()` returns, and the
        // roll-up is a different shape. Null for a job with no children, so an
        // ordinary job renders exactly as before.
        $rollUp = $yardJob->subJobs()->exists()
            ? $pnl->computeWithSubJobs($yardJob)
            : null;

        return view('yard.jobs.show', compact('yardJob', 'pnlData', 'rollUp'));
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

        if (in_array($data['status'], ['completed', 'cancelled'])) {
            $notifType = $data['status'] === 'completed' ? 'success' : 'warning';
            NotificationService::notifyAll(
                'Yard Job ' . ucfirst($data['status']) . ' - ' . $yardJob->job_no,
                ($yardJob->customer->name ?? 'Unknown') . ' · ' . ($yardJob->jobType->name ?? 'Job'),
                $notifType,
                route('yard.jobs.show', $yardJob)
            );
        }

        return back()->with('success', "Job {$yardJob->job_no} updated to \"" . YardJob::statusLabel($data['status']) . "\".");
    }
}
