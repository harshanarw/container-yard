<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Services\LessorOnHireService;
use App\Services\Reporting\ContainerVisitDates;
use App\Support\DaysInYard;
use Illuminate\Http\Request;

class LessorOnHireController extends Controller
{
    public function __construct(private LessorOnHireService $service) {}

    public function index()
    {
        $this->authorize('yard.lessor-hire.view');

        $hires = LessorOnHire::with(['lessor', 'container', 'yardJob'])
            ->latest('id')->paginate(20);

        return view('yard.lessor-hires.index', compact('hires'));
    }

    public function create()
    {
        $this->authorize('yard.lessor-hire.create');

        // Every status the service accepts as "in the yard"; listing fewer hid
        // boxes in repair or reserved that could be leased.
        $containers = Container::with('equipmentType:id,eqt_code')
            ->whereIn('status', Container::IN_YARD_STATUSES)
            ->orderBy('container_no')
            // Shown, not filtered — see ContainerHireController::create.
            ->get(['id', 'container_no', 'size', 'type_code', 'equipment_type_id', 'customer_id',
                   'mr_status', 'mr_lane', 'export_ready', 'mr_status_expires_at']);

        // Option badges as on the Gate Out search: visit customer and days in yard.
        $visitCustomers = app(\App\Services\ContainerCustodyService::class)->visitCustomerIdsFor($containers);
        $customerNames  = Customer::whereIn('id', array_filter(array_merge(array_values($visitCustomers), $containers->pluck('customer_id')->all())))
            ->pluck('name', 'id');
        $visits = ContainerVisitDates::forCollection($containers);
        foreach ($containers as $c) {
            $c->option_customer = $customerNames[$visitCustomers[$c->id] ?? $c->customer_id] ?? null;
            $c->option_days     = DaysInYard::between($visits[$c->id]['gate_in'] ?? null, $visits[$c->id]['gate_out'] ?? null);
        }
        // Lessors are AP contacts (the yard pays them for the hire).
        $lessors = Customer::apContacts()->get(['id', 'code', 'name']);

        return view('yard.lessor-hires.create', compact('containers', 'lessors'));
    }

    /**
     * The visit, its main job and what would stop a lease, for the container
     * just picked (JSON for the create form). The save repeats every check.
     */
    public function lookup(Request $request)
    {
        $this->authorize('yard.lessor-hire.create');

        $container = Container::findOrFail($request->integer('container_id'));

        return response()->json($this->service->inspect($container));
    }

    public function store(Request $request)
    {
        $this->authorize('yard.lessor-hire.create');

        $validated = $request->validate([
            'container_id'   => ['required', 'exists:containers,id'],
            'lessor_id'      => ['required', 'exists:customers,id'],
            // Backdating is allowed; the future is not (record it on the day).
            'on_hire_date'   => ['required', 'date', 'before_or_equal:today'],
            'hire_reference' => ['nullable', 'string', 'max:100'],
            'per_diem_rate'  => ['nullable', 'numeric', 'min:0'],
            'expected_off_hire_date' => ['nullable', 'date', 'after_or_equal:on_hire_date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // The in-yard shape, always. This screen only offers containers
            // that are already on the ground — see create() — so taking one on
            // hire moves nothing: the box stays where it is and only who
            // commercially holds it changes.
            //
            // It used to call onHire(), which *fabricates a gate-in* because it
            // models a box arriving on hire. Against a container already here
            // that put a second arrival in the ledger for a movement that never
            // happened, which Container Inquiry then showed as two movements
            // and the stock reports read as a new stay. The in-yard method was
            // written for this in 2c and the screen was never pointed at it.
            $hire = $this->service->onHireInYard(
                Container::findOrFail($validated['container_id']),
                $validated,
                auth()->id() ?? 1,
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('yard.lessor-hires.show', $hire)
            ->with('success', 'Container placed on hire from the lessor. Job ' . $hire->yardJob?->job_no
                . ' opened - tag the lessor fee (supplier invoice / voucher) to this job to cost it.');
    }

    public function show(LessorOnHire $lessorHire)
    {
        $this->authorize('yard.lessor-hire.view');

        $lessorHire->load(['lessor', 'container', 'yardJob.jobType', 'gateMovement', 'createdBy']);

        $service = app(\App\Services\JobPnlService::class);
        $pnl     = $service->compute($lessorHire->yardJob);

        // The lettings made during this lease, netted against its cost.
        //
        // This is the one screen the whole sub-job structure exists for: the
        // lease is what the yard pays the line, each re-let beneath it is what
        // a renter pays the yard, and the margin is one minus the other. On its
        // own figures alone a lease can only ever read as a loss, because the
        // income it was incurred to earn sits on its children.
        $rollUp = $lessorHire->yardJob && $lessorHire->yardJob->subJobs()->exists()
            ? $service->computeWithSubJobs($lessorHire->yardJob)
            : null;

        return view('yard.lessor-hires.show', [
            'hire'   => $lessorHire,
            'pnl'    => $pnl,
            'rollUp' => $rollUp,
            // Already gone? The off-hire then defaults to, and cannot pass, the departure.
            'departure' => $lessorHire->isActive() && $lessorHire->on_hire_mode === 'in_yard'
                ? app(\App\Services\LessorOnHireService::class)->departureSinceOnHire($lessorHire)
                : null,
        ]);
    }

    public function offHire(Request $request, LessorOnHire $lessorHire)
    {
        $this->authorize('yard.lessor-hire.off_hire');

        $validated = $request->validate([
            'off_hire_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // Unwound the way it was wound. A lease recorded in the old
            // `arrival` shape owns a fabricated gate-in, and only offHire()
            // closes that pairing with its matching gate-out; an `in_yard`
            // lease has no movements at all and must not acquire a departure
            // for a box that never left.
            //
            // This is what `on_hire_mode` was added for (migration 000316):
            // which shape a lease is, recorded rather than inferred from a null
            // `gate_movement_id`.
            $lessorHire->isInYardLease()
                ? $this->service->offHireInYard($lessorHire, $validated, auth()->id() ?? 1)
                : $this->service->offHire($lessorHire, $validated, auth()->id() ?? 1);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('yard.lessor-hires.show', $lessorHire)
            ->with('success', $lessorHire->fresh()->isInYardLease()
                ? 'Off-hired - the container was returned to the shipping line and the lease closed. '
                    . 'It stays in the yard, and its storage resumes from today.'
                : 'Off-hired - the container was returned to the lessor and the job closed.');
    }
}
