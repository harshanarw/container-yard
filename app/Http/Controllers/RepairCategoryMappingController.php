<?php

namespace App\Http\Controllers;

use App\Models\MrCode;
use App\Models\RepairCategory;
use App\Models\RepairCategoryMapping;
use Illuminate\Http\Request;

class RepairCategoryMappingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.repair-categories.view')->only(['index']);
        $this->middleware('can:masters.repair-categories.create')->only(['store']);
        $this->middleware('can:masters.repair-categories.edit')->only(['update', 'toggleActive']);
        $this->middleware('can:masters.repair-categories.delete')->only(['destroy']);
    }

    public function index()
    {
        $mappings        = RepairCategoryMapping::with('repairCategory', 'componentCode')
                            ->orderBy('priority')
                            ->get();
        $categories      = RepairCategory::active()->get();
        $componentCodes  = MrCode::where('type', 'component')->where('is_active', true)->orderBy('sort_order')->get();
        $repairTypes     = ['replace', 'repair', 'weld', 'straighten', 'clean_and_treat', 'paint'];

        return view('masters.repair-category-mappings.index', compact(
            'mappings', 'categories', 'componentCodes', 'repairTypes'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'repair_category_id' => 'required|exists:repair_categories,id',
            'component_code_id'  => 'nullable|exists:mr_codes,id',
            'repair_type'        => 'nullable|in:replace,repair,weld,straighten,clean_and_treat,paint',
            'priority'           => 'required|integer|min:1|max:999',
        ]);

        if (empty($validated['component_code_id']) && empty($validated['repair_type'])) {
            return back()->withErrors(['component_code_id' => 'At least one matching criterion (Component Code or Repair Type) is required.'])->withInput();
        }

        RepairCategoryMapping::create($validated);

        return back()->with('success', 'Mapping rule created.');
    }

    public function update(Request $request, RepairCategoryMapping $repairCategoryMapping)
    {
        $validated = $request->validate([
            'repair_category_id' => 'required|exists:repair_categories,id',
            'component_code_id'  => 'nullable|exists:mr_codes,id',
            'repair_type'        => 'nullable|in:replace,repair,weld,straighten,clean_and_treat,paint',
            'priority'           => 'required|integer|min:1|max:999',
        ]);

        if (empty($validated['component_code_id']) && empty($validated['repair_type'])) {
            return back()->withErrors(['component_code_id' => 'At least one matching criterion is required.'])->withInput();
        }

        $repairCategoryMapping->update($validated);

        return back()->with('success', 'Mapping rule updated.');
    }

    public function toggleActive(RepairCategoryMapping $repairCategoryMapping)
    {
        $repairCategoryMapping->update(['is_active' => !$repairCategoryMapping->is_active]);

        return back()->with('success', 'Mapping rule ' . ($repairCategoryMapping->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(RepairCategoryMapping $repairCategoryMapping)
    {
        $repairCategoryMapping->delete();

        return back()->with('success', 'Mapping rule deleted.');
    }
}
