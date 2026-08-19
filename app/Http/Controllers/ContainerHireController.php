<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Services\ContainerHireService;
use Illuminate\Http\Request;

class ContainerHireController extends Controller
{
    public function __construct(private ContainerHireService $service) {}

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('yard.hire.view');

        $query = ContainerHire::with(['container', 'originalCustomer', 'hireCustomer'])
            ->latest('on_hire_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('container_no')) {
            $query->whereHas('container', fn ($q) =>
                $q->where('container_no', 'like', '%' . $request->input('container_no') . '%')
            );
        }

        if ($request->filled('customer_id')) {
            $query->where(fn ($q) =>
                $q->where('original_customer_id', $request->input('customer_id'))
                  ->orWhere('hire_customer_id', $request->input('customer_id'))
            );
        }

        if ($request->filled('from')) {
            $query->whereDate('on_hire_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('on_hire_date', '<=', $request->input('to'));
        }

        $hires     = $query->paginate(25)->withQueryString();
        $customers = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('yard.hires.index', compact('hires', 'customers'));
    }

    // ── On Hire form ──────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $this->authorize('yard.hire.create');

        // Allow pre-filling from container detail page
        $container = null;
        if ($request->filled('container_id')) {
            $container = Container::whereIn('status', ['in_yard', 'available'])
                ->whereDoesntHave('activeHire')
                ->find($request->input('container_id'));
        }

        $inYardContainers = Container::whereIn('status', ['in_yard', 'available'])
            ->whereDoesntHave('activeHire')
            ->with('customer')
            ->orderBy('container_no')
            // Shown, not filtered: a box may legitimately go on hire before it
            // is export ready. The status rides along so the operator picks with
            // their eyes open.
            ->get(['id', 'container_no', 'customer_id', 'size', 'type_code',
                   'mr_status', 'mr_lane', 'export_ready', 'mr_status_expires_at']);

        $customers = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('yard.hires.create', compact('container', 'inYardContainers', 'customers'));
    }

    // ── Store (On Hire) ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('yard.hire.create');

        $validated = $request->validate([
            'container_id'    => ['required', 'exists:containers,id'],
            'on_hire_date'    => ['required', 'date'],
            'hire_customer_id' => ['nullable', 'exists:customers,id'],
            'hire_reference'  => ['nullable', 'string', 'max:100'],
            'on_hire_notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        $container = Container::findOrFail($validated['container_id']);

        try {
            $hire = $this->service->onHire($container, $validated, auth()->id());
            return redirect()
                ->route('yard.hires.show', $hire)
                ->with('success', "Container {$container->container_no} placed on hire successfully.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function show(ContainerHire $hire)
    {
        $this->authorize('yard.hire.view');

        $hire->load([
            'container.customer',
            'originalCustomer',
            'hireCustomer',
            'originalYardStorage',
            'hireYardStorage',
            'resumedYardStorage',
            'createdBy',
            'updatedBy',
        ]);

        // Container hires have no owning job column; resolve the on-hire gate-in
        // visit's job for display (best-effort — labelled as the on-hire job).
        $hireJobId = \App\Services\JobResolver::forContainerVisit(
            $hire->container_id, $hire->original_gate_in_date
        );
        $hireJob = $hireJobId ? \App\Models\YardJob::with('jobType')->find($hireJobId) : null;

        return view('yard.hires.show', compact('hire', 'hireJob'));
    }

    // ── Off Hire form ─────────────────────────────────────────────────────────

    public function offHireForm(ContainerHire $hire)
    {
        $this->authorize('yard.hire.off_hire');

        if (! $hire->isActive()) {
            return redirect()
                ->route('yard.hires.show', $hire)
                ->with('error', 'Only active hires can be off-hired.');
        }

        $hire->load(['container', 'originalCustomer', 'hireCustomer']);

        return view('yard.hires.off-hire', compact('hire'));
    }

    // ── Process Off Hire ──────────────────────────────────────────────────────

    public function processOffHire(Request $request, ContainerHire $hire)
    {
        $this->authorize('yard.hire.off_hire');

        $validated = $request->validate([
            'off_hire_date'  => ['required', 'date', 'after:' . $hire->on_hire_date->toDateString()],
            'off_hire_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->offHire($hire, $validated, auth()->id());
            $hire->loadMissing('container');
            return redirect()
                ->route('yard.hires.show', $hire)
                ->with('success', "Container {$hire->container->container_no} off-hired successfully.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(ContainerHire $hire)
    {
        $this->authorize('yard.hire.cancel');

        try {
            $this->service->cancelHire($hire, auth()->id());
            $hire->loadMissing('container');
            return redirect()
                ->route('yard.hires.show', $hire)
                ->with('success', "Hire for container {$hire->container->container_no} has been cancelled.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
