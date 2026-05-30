<?php

namespace App\Http\Controllers;

use App\Models\RepairCategory;
use Illuminate\Http\Request;

class RepairCategoryController extends Controller
{
    public function index()
    {
        $categories = RepairCategory::orderBy('sort_order')->get();

        return view('masters.repair-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:10|unique:repair_categories,code',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'required|string|max:30',
        ]);

        $validated['sort_order'] = RepairCategory::max('sort_order') + 1;
        RepairCategory::create($validated);

        return back()->with('success', "Repair category '{$validated['name']}' created.");
    }

    public function update(Request $request, RepairCategory $repairCategory)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:10|unique:repair_categories,code,' . $repairCategory->id,
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'required|string|max:30',
        ]);

        $repairCategory->update($validated);

        return back()->with('success', "Category '{$repairCategory->name}' updated.");
    }

    public function toggleActive(RepairCategory $repairCategory)
    {
        $repairCategory->update(['is_active' => !$repairCategory->is_active]);

        return back()->with('success', "Category '{$repairCategory->name}' " . ($repairCategory->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(RepairCategory $repairCategory)
    {
        if ($repairCategory->workOrders()->exists()) {
            return back()->with('error', "Cannot delete '{$repairCategory->name}': it is used by work orders.");
        }

        $name = $repairCategory->name;
        $repairCategory->delete();

        return back()->with('success', "Category '{$name}' deleted.");
    }

    public function reorder(Request $request)
    {
        foreach ($request->input('order', []) as $i => $id) {
            RepairCategory::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
