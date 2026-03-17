<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInquiryRequest;
use App\Http\Requests\UpdateInquiryRequest;
use App\Models\ChecklistMasterItem;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Damage;
use App\Models\Inquiry;
use App\Models\InquiryChecklist;
use App\Models\InquiryPhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InquiryController extends Controller
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

        return view('inquiries.index', compact('inquiries', 'customers'));
    }

    public function create(Request $request)
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $inspectors     = User::where('role', 'inspector')->where('status', 'active')->get();
        $containers     = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with('customer')
            ->orderBy('container_no')
            ->get();
        $checklistItems  = ChecklistMasterItem::active()->get();
        $equipmentTypes  = EquipmentType::active()->get();

        // Pre-select container if passed from yard/container view
        $selectedContainer = $request->container_id
            ? Container::with(['customer', 'equipmentType'])->find($request->container_id)
            : null;

        return view('inquiries.create', compact(
            'customers', 'inspectors', 'containers', 'selectedContainer',
            'checklistItems', 'equipmentTypes'
        ));
    }

    public function store(StoreInquiryRequest $request)
    {
        $container = Container::findOrFail($request->container_id);

        $inquiry = Inquiry::create([
            'inquiry_no'            => $this->generateInquiryNo(),
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
                $path = $this->saveInquiryPhoto($photo, $inquiry->id);
                $inquiry->photos()->create([
                    'photo_path'  => $path,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('inquiries.show', $inquiry)
            ->with('success', "Inquiry {$inquiry->inquiry_no} created successfully.");
    }

    public function show(Inquiry $inquiry)
    {
        $inquiry->load(['container', 'customer', 'inspector', 'damages', 'checklists', 'photos', 'estimate']);

        return view('inquiries.show', compact('inquiry'));
    }

    public function edit(Inquiry $inquiry)
    {
        $inquiry->load(['damages', 'checklists']);
        $inspectors     = User::where('role', 'inspector')->where('status', 'active')->get();
        $checklistItems = ChecklistMasterItem::active()->get();

        return view('inquiries.edit', compact('inquiry', 'inspectors', 'checklistItems'));
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry)
    {
        $inquiry->update($request->only([
            'inspector_id', 'inspection_date', 'priority',
            'overall_condition', 'findings', 'recommended_action',
            'status', 'estimated_repair_cost',
        ]));

        // Replace damages
        if ($request->has('damages')) {
            $inquiry->damages()->delete();
            foreach ($request->damages as $damage) {
                $inquiry->damages()->create($damage);
            }
        }

        // Rebuild checklist from current master items so additions/removals are reflected
        $masterItems = ChecklistMasterItem::active()->get();
        $checked     = $request->checklist ?? [];
        $inquiry->checklists()->delete();
        foreach ($masterItems as $master) {
            $inquiry->checklists()->create([
                'checklist_item' => $master->code,
                'is_checked'     => in_array($master->code, $checked),
            ]);
        }

        // Append new photos
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $this->saveInquiryPhoto($photo, $inquiry->id);
                $inquiry->photos()->create([
                    'photo_path'  => $path,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('inquiries.show', $inquiry)
            ->with('success', 'Inquiry updated successfully.');
    }

    public function destroyPhoto(Inquiry $inquiry, InquiryPhoto $photo)
    {
        @unlink(public_path($photo->photo_path));
        $photo->delete();

        return back()->with('success', 'Photo removed successfully.');
    }

    public function destroy(Inquiry $inquiry)
    {
        if ($inquiry->estimate()->exists()) {
            return back()->with('error', 'Cannot delete inquiry with an associated estimate.');
        }

        // Delete all stored photo files
        foreach ($inquiry->photos as $photo) {
            @unlink(public_path($photo->photo_path));
        }

        $inquiry->delete();

        return redirect()->route('inquiries.index')
            ->with('success', 'Inquiry deleted successfully.');
    }

    private function saveInquiryPhoto($file, int $inquiryId): string
    {
        $dir = public_path("images/inquiries/{$inquiryId}/photos");
        File::ensureDirectoryExists($dir);

        $filename = Str::random(16) . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        return "images/inquiries/{$inquiryId}/photos/{$filename}";
    }

    private function generateInquiryNo(): string
    {
        $last = Inquiry::latest('id')->value('inquiry_no');
        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return 'INQ-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
