<?php

namespace App\Http\Controllers;

use App\Models\YardJobType;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class YardJobTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.job-types.view')->only('index');
        $this->middleware('can:masters.job-types.create')->only('store');
        $this->middleware('can:masters.job-types.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.job-types.delete')->only('destroy');
    }

    public function index(): View
    {
        $jobTypes  = YardJobType::orderBy('sort_order')->orderBy('job_type_code')->get();
        $flags     = YardJobType::workflowFlags();

        return view('masters.job-types.index', compact('jobTypes', 'flags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'job_type_code'             => ['required', 'string', 'max:30', 'unique:yard_job_types,job_type_code', 'regex:/^[A-Z0-9_]+$/'],
            'type_short_code'           => ['required', 'string', 'max:5', 'unique:yard_job_types,type_short_code', 'regex:/^[A-Z]{2,5}$/'],
            'job_type_name'             => ['required', 'string', 'max:100'],
            'movement_direction'        => ['required', 'in:gate_in,gate_out'],
            'description'               => ['nullable', 'string', 'max:500'],
            'handling_applicable'       => ['nullable', 'boolean'],
            'survey_applicable'         => ['nullable', 'boolean'],
            'estimate_applicable'       => ['nullable', 'boolean'],
            'repair_applicable'         => ['nullable', 'boolean'],
            'storage_applicable'        => ['nullable', 'boolean'],
            'wash_applicable'           => ['nullable', 'boolean'],
            'reefer_applicable'         => ['nullable', 'boolean'],
            'customs_applicable'        => ['nullable', 'boolean'],
            'cargo_transfer_applicable' => ['nullable', 'boolean'],
            'approval_required'         => ['nullable', 'boolean'],
            'damage_capture_required'   => ['nullable', 'boolean'],
            'default_next_status'       => ['nullable', 'string', 'max:50'],
            'remarks'                   => ['nullable', 'string', 'max:500'],
        ]);

        // Normalise checkboxes — unchecked boxes are not submitted
        foreach (array_keys(YardJobType::workflowFlags()) as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }
        $data['approval_required']       = (bool) ($data['approval_required'] ?? false);
        $data['damage_capture_required'] = (bool) ($data['damage_capture_required'] ?? false);
        $data['is_active']               = true;
        $data['sort_order']              = YardJobType::max('sort_order') + 1;

        YardJobType::create($data);

        return back()->with('success', "Job type \"{$data['job_type_code']}\" created.");
    }

    public function update(Request $request, YardJobType $jobType): RedirectResponse
    {
        $data = $request->validate([
            'job_type_code'             => ['required', 'string', 'max:30', "unique:yard_job_types,job_type_code,{$jobType->id}", 'regex:/^[A-Z0-9_]+$/'],
            'type_short_code'           => ['required', 'string', 'max:5', "unique:yard_job_types,type_short_code,{$jobType->id}", 'regex:/^[A-Z]{2,5}$/'],
            'job_type_name'             => ['required', 'string', 'max:100'],
            'movement_direction'        => ['required', 'in:gate_in,gate_out'],
            'description'               => ['nullable', 'string', 'max:500'],
            'handling_applicable'       => ['nullable', 'boolean'],
            'survey_applicable'         => ['nullable', 'boolean'],
            'estimate_applicable'       => ['nullable', 'boolean'],
            'repair_applicable'         => ['nullable', 'boolean'],
            'storage_applicable'        => ['nullable', 'boolean'],
            'wash_applicable'           => ['nullable', 'boolean'],
            'reefer_applicable'         => ['nullable', 'boolean'],
            'customs_applicable'        => ['nullable', 'boolean'],
            'cargo_transfer_applicable' => ['nullable', 'boolean'],
            'approval_required'         => ['nullable', 'boolean'],
            'damage_capture_required'   => ['nullable', 'boolean'],
            'default_next_status'       => ['nullable', 'string', 'max:50'],
            'remarks'                   => ['nullable', 'string', 'max:500'],
        ]);

        foreach (array_keys(YardJobType::workflowFlags()) as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }
        $data['approval_required']       = (bool) ($data['approval_required'] ?? false);
        $data['damage_capture_required'] = (bool) ($data['damage_capture_required'] ?? false);

        // System types: allow editing flags and description but not identity fields
        if ($jobType->is_system) {
            unset($data['job_type_code'], $data['type_short_code'], $data['movement_direction']);
        }

        $jobType->update($data);

        return back()->with('success', "Job type \"{$jobType->job_type_code}\" updated.");
    }

    public function toggleActive(YardJobType $jobType): RedirectResponse
    {
        $jobType->update(['is_active' => ! $jobType->is_active]);
        $state = $jobType->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$jobType->job_type_code}\" {$state}.");
    }

    public function destroy(YardJobType $jobType): RedirectResponse
    {
        if ($jobType->is_system) {
            return back()->with('error', "System job types cannot be deleted.");
        }

        // Future-proof: once gate_movements links to this table, check usage here.

        $jobType->delete();

        return back()->with('success', "Job type \"{$jobType->job_type_code}\" deleted.");
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:yard_job_types,id'],
        ]);

        foreach ($request->order as $position => $id) {
            YardJobType::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
