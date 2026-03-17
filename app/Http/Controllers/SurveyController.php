<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\ChecklistMasterItem;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\Inquiry;
use App\Models\InquiryChecklist;
use App\Models\InquiryPhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $inquiries = Inquiry::with(['container', 'customer', 'inspector', 'estimate'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('inquiry_no', 'like', "%{$s}%")
                  ->orWhere('container_no', 'like', "%{$s}%")
            )
            ->when($request->status,       fn ($q, $v) => $q->where('status', $v))
            ->when($request->priority,     fn ($q, $v) => $q->where('priority', $v))
            ->when($request->customer_id,  fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->inquiry_type, fn ($q, $v) => $q->where('inquiry_type', $v))
            ->when($request->date_from,    fn ($q, $v) => $q->whereDate('inspection_date', '>=', $v))
            ->when($request->date_to,      fn ($q, $v) => $q->whereDate('inspection_date', '<=', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('surveys.index', compact('inquiries', 'customers'));
    }

    public function create(Request $request)
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $inspectors     = User::where('role', 'inspector')->where('status', 'active')->get();
        $checklistItems = ChecklistMasterItem::active()->get();
        $equipmentTypes = EquipmentType::active()->get();

        // Load in-yard containers with their latest gate-in movement for auto-fill
        $containers = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with(['customer', 'equipmentType'])
            ->orderBy('container_no')
            ->get()
            ->map(function ($c) {
                $latestGateIn = GateMovement::where('container_id', $c->id)
                    ->where('movement_type', 'in')
                    ->latest()
                    ->first();
                $c->gate_movement_ref = $latestGateIn
                    ? 'GI-' . str_pad($latestGateIn->id, 5, '0', STR_PAD_LEFT)
                    : null;
                $c->gate_movement_date = $latestGateIn?->gate_in_time?->toDateString()
                    ?? $c->gate_in_date?->toDateString();
                return $c;
            });

        // Pre-select container if passed from yard/container view
        $selectedContainer = $request->container_id
            ? Container::with(['customer', 'equipmentType'])->find($request->container_id)
            : null;

        return view('surveys.create', compact(
            'customers', 'inspectors', 'containers', 'selectedContainer',
            'checklistItems', 'equipmentTypes'
        ));
    }

    public function store(StoreSurveyRequest $request)
    {
        $container = Container::findOrFail($request->container_id);

        $inquiry = Inquiry::create([
            'inquiry_no'            => $this->generateSurveyNo(),
            'container_id'          => $container->id,
            'container_no'          => $container->container_no,
            'equipment_type_id'     => $container->equipment_type_id,
            'size'                  => $container->size,
            'type_code'             => $container->type_code,
            'customer_id'           => $request->customer_id,
            'inquiry_type'          => $request->inquiry_type,
            'inspector_id'          => $request->inspector_id,
            'inspection_date'       => $request->inspection_date,
            'gate_in_ref'           => $request->gate_in_ref,
            'priority'              => $request->priority,
            'overall_condition'     => $request->overall_condition,
            'findings'              => $request->findings,
            'recommended_action'    => $request->recommended_action,
            'estimated_repair_cost' => $request->estimated_repair_cost,
            'status'                => 'open',
        ]);

        // Save damages
        if ($request->damages) {
            foreach ($request->damages as $damage) {
                $inquiry->damages()->create($damage);
            }
        }

        // Save checklist from master items
        $masterItems = ChecklistMasterItem::active()->get();
        $checked     = $request->checklist ?? [];
        foreach ($masterItems as $master) {
            $inquiry->checklists()->create([
                'checklist_item' => $master->code,
                'is_checked'     => in_array($master->code, $checked),
            ]);
        }

        // Save photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store("surveys/{$inquiry->id}/photos", 'public');
                $inquiry->photos()->create([
                    'photo_path'  => $path,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('surveys.show', $inquiry)
            ->with('success', "Survey {$inquiry->inquiry_no} created successfully.");
    }

    public function show(Inquiry $survey)
    {
        $survey->load(['container', 'customer', 'inspector', 'damages', 'checklists', 'photos', 'estimate']);

        return view('surveys.show', ['inquiry' => $survey]);
    }

    public function edit(Inquiry $survey)
    {
        $survey->load(['damages', 'checklists']);
        $inspectors     = User::where('role', 'inspector')->where('status', 'active')->get();
        $checklistItems = ChecklistMasterItem::active()->get();

        return view('surveys.edit', ['inquiry' => $survey, 'inspectors' => $inspectors, 'checklistItems' => $checklistItems]);
    }

    public function update(UpdateSurveyRequest $request, Inquiry $survey)
    {
        $survey->update($request->only([
            'inspector_id', 'inspection_date', 'priority',
            'overall_condition', 'findings', 'recommended_action',
            'status', 'estimated_repair_cost',
        ]));

        // Replace damages
        if ($request->has('damages')) {
            $survey->damages()->delete();
            foreach ($request->damages as $damage) {
                $survey->damages()->create($damage);
            }
        }

        // Rebuild checklist from current master items so additions/removals are reflected
        $masterItems = ChecklistMasterItem::active()->get();
        $checked     = $request->checklist ?? [];
        $survey->checklists()->delete();
        foreach ($masterItems as $master) {
            $survey->checklists()->create([
                'checklist_item' => $master->code,
                'is_checked'     => in_array($master->code, $checked),
            ]);
        }

        // Append new photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store("surveys/{$survey->id}/photos", 'public');
                $survey->photos()->create([
                    'photo_path'  => $path,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('surveys.show', $survey)
            ->with('success', 'Survey updated successfully.');
    }

    public function destroyPhoto(Inquiry $survey, InquiryPhoto $photo)
    {
        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        return back()->with('success', 'Photo removed successfully.');
    }

    public function destroy(Inquiry $survey)
    {
        if ($survey->estimate()->exists()) {
            return back()->with('error', 'Cannot delete survey with an associated estimate.');
        }

        // Delete all stored photo files
        foreach ($survey->photos as $photo) {
            Storage::disk('public')->delete($photo->photo_path);
        }

        $survey->delete();

        return redirect()->route('surveys.index')
            ->with('success', 'Survey deleted successfully.');
    }

    private function generateSurveyNo(): string
    {
        $last = Inquiry::latest('id')->value('inquiry_no');
        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return 'SRV-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
