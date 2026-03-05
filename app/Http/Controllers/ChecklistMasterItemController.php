<?php

namespace App\Http\Controllers;

use App\Models\ChecklistMasterItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChecklistMasterItemController extends Controller
{
    public function index()
    {
        $items = ChecklistMasterItem::orderBy('sort_order')->orderBy('id')->get();

        return view('masters.checklist.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
        ]);

        $data['code']       = Str::slug($data['label'], '_');
        $data['sort_order'] = ChecklistMasterItem::max('sort_order') + 1;

        // Ensure code uniqueness by appending an incrementing suffix if needed
        $baseCode = $data['code'];
        $i = 1;
        while (ChecklistMasterItem::where('code', $data['code'])->exists()) {
            $data['code'] = $baseCode . '_' . $i++;
        }

        ChecklistMasterItem::create($data);

        return back()->with('success', 'Checklist item added successfully.');
    }

    public function update(Request $request, ChecklistMasterItem $checklistMasterItem)
    {
        $data = $request->validate([
            'label'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        // Only toggle active via dedicated action; label/description update keeps is_active intact
        if (!$request->has('is_active')) {
            unset($data['is_active']);
        }

        $checklistMasterItem->update($data);

        return back()->with('success', 'Checklist item updated.');
    }

    public function toggleActive(ChecklistMasterItem $checklistMasterItem)
    {
        $checklistMasterItem->update(['is_active' => !$checklistMasterItem->is_active]);

        $state = $checklistMasterItem->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Item \"{$checklistMasterItem->label}\" {$state}.");
    }

    public function destroy(ChecklistMasterItem $checklistMasterItem)
    {
        $checklistMasterItem->delete();

        return back()->with('success', "Checklist item \"{$checklistMasterItem->label}\" deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer|exists:checklist_master_items,id']);

        foreach ($request->order as $position => $id) {
            ChecklistMasterItem::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
