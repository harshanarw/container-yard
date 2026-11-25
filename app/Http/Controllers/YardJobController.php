<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\YardJob;
use App\Exceptions\JobLifecycleException;
use App\Services\JobLifecycleService;
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

        $jobTree = $this->jobTree($yardJob);

        return view('yard.jobs.show', compact('yardJob', 'pnlData', 'rollUp', 'jobTree'));
    }

    /**
     * The whole job tree this job belongs to, main job first, each sub job
     * under its parent: [['job' => YardJob, 'depth' => int], ...]. Empty for a
     * job that stands alone, so an ordinary job renders as before.
     *
     * Depth-first from the root over `descendants()`, which is already bounded
     * and cycle-guarded; the walk here only visits what that returned.
     *
     * @return list<array{job: YardJob, depth: int}>
     */
    private function jobTree(YardJob $job): array
    {
        $root  = $job->rootJob();
        $below = $root->descendants();

        if ($below->isEmpty()) {
            return [];
        }

        $all = $below->push($root);
        $all->load(['jobType', 'customer', 'heldBy']);
        $children = $all->groupBy(fn (YardJob $j) => (int) $j->parent_job_id);

        $rows = [];
        $seen = [];
        $walk = function (YardJob $node, int $depth) use (&$walk, &$rows, &$seen, $children) {
            if (isset($seen[$node->id]) || $depth > 5) {
                return;
            }
            $seen[$node->id] = true;
            $rows[] = ['job' => $node, 'depth' => $depth];

            foreach ($children->get($node->id, collect())->sortBy('id') as $child) {
                $walk($child, $depth + 1);
            }
        };
        $walk($all->firstWhere('id', $root->id), 0);

        return $rows;
    }

    /**
     * Status and remarks, through the job life cycle (Rule 5).
     *
     * Completing or cancelling needs `yard.jobs.complete`; reopening a closed
     * job needs `yard.jobs.reopen`. The rules themselves (no completing over
     * open sub jobs, no reopening once billed) are in JobLifecycleService, so
     * the screen and every other caller obey the same ones.
     */
    public function update(Request $request, YardJob $yardJob, JobLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            // Absent when the job is waiting on its sub jobs: that state is the
            // system's, shown but not offered, so only the remarks change.
            'status'  => ['nullable', 'in:open,in_progress,completed,cancelled'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $to     = $data['status'] ?? $yardJob->status;
        $userId = auth()->id();

        if ($yardJob->remarks !== ($data['remarks'] ?? null)) {
            $yardJob->update(['remarks' => $data['remarks'] ?? null]);
        }

        if ($to === $yardJob->status) {
            return back()->with('success', "Job {$yardJob->job_no} updated.");
        }

        try {
            if (in_array($to, ['completed', 'cancelled'], true)) {
                abort_unless(auth()->user()->can('yard.jobs.complete'), 403,
                    'You do not have permission to complete or cancel jobs.');

                $to === 'completed'
                    ? $lifecycle->complete($yardJob, $userId, 'Completed by hand')
                    : $lifecycle->cancel($yardJob, $userId, 'Cancelled by hand');
            } elseif (! $yardJob->isActive()) {
                abort_unless(auth()->user()->can('yard.jobs.reopen'), 403,
                    'You do not have permission to reopen jobs.');

                $lifecycle->reopen($yardJob, $userId, $to);
            } else {
                $lifecycle->setWorking($yardJob, $to, $userId);
            }
        } catch (JobLifecycleException $e) {
            return back()->withInput()->withErrors(['status' => $e->getMessage()]);
        }

        $yardJob->refresh();

        if (in_array($yardJob->status, ['completed', 'cancelled'], true)) {
            $notifType = $yardJob->status === 'completed' ? 'success' : 'warning';
            NotificationService::notifyAll(
                'Yard Job ' . YardJob::statusLabel($yardJob->status) . ' - ' . $yardJob->job_no,
                ($yardJob->customer->name ?? 'Unknown') . ' · ' . ($yardJob->jobType->job_type_name ?? 'Job'),
                $notifType,
                route('yard.jobs.show', $yardJob)
            );
        }

        return back()->with('success', "Job {$yardJob->job_no} updated to \"" . YardJob::statusLabel($yardJob->status) . "\".");
    }
}
