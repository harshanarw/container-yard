<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Services\LessorOnHireService;
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

        $containers = Container::whereIn('status', ['in_yard', 'available'])
            ->orderBy('container_no')->get(['id', 'container_no', 'size', 'type_code']);
        // Lessors are AP contacts (the yard pays them for the hire).
        $lessors = Customer::apContacts()->get(['id', 'code', 'name']);

        return view('yard.lessor-hires.create', compact('containers', 'lessors'));
    }

    public function store(Request $request)
    {
        $this->authorize('yard.lessor-hire.create');

        $validated = $request->validate([
            'container_id'   => ['required', 'exists:containers,id'],
            'lessor_id'      => ['required', 'exists:customers,id'],
            'on_hire_date'   => ['required', 'date'],
            'hire_reference' => ['nullable', 'string', 'max:100'],
            'per_diem_rate'  => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $hire = $this->service->onHire($validated, auth()->id() ?? 1);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('yard.lessor-hires.show', $hire)
            ->with('success', 'Container placed on hire from the lessor. Job ' . $hire->yardJob?->job_no
                . ' opened — tag the lessor fee (supplier invoice / voucher) to this job to cost it.');
    }

    public function show(LessorOnHire $lessorHire)
    {
        $this->authorize('yard.lessor-hire.view');

        $lessorHire->load(['lessor', 'container', 'yardJob.jobType', 'gateMovement', 'createdBy']);
        $pnl = app(\App\Services\JobPnlService::class)->compute($lessorHire->yardJob);

        return view('yard.lessor-hires.show', ['hire' => $lessorHire, 'pnl' => $pnl]);
    }

    public function offHire(Request $request, LessorOnHire $lessorHire)
    {
        $this->authorize('yard.lessor-hire.off_hire');

        $validated = $request->validate([
            'off_hire_date' => ['required', 'date'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->offHire($lessorHire, $validated, auth()->id() ?? 1);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('yard.lessor-hires.show', $lessorHire)
            ->with('success', 'Off-hired — the container was returned to the lessor and the job closed.');
    }
}
