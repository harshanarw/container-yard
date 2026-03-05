<?php

namespace App\Http\Controllers;

use App\Models\EquipmentType;
use Illuminate\Http\Request;

class EquipmentTypeController extends Controller
{
    public function index()
    {
        $items = EquipmentType::orderBy('sort_order')->orderBy('eqt_code')->get();

        return view('masters.equipment-types.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'eqt_code'    => 'required|string|max:10|unique:equipment_types,eqt_code',
            'iso_code'    => 'nullable|string|max:10|unique:equipment_types,iso_code',
            'size'        => 'required|string|max:5',
            'type_code'   => 'required|string|max:5',
            'height'      => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
        ]);

        $data['eqt_code']  = strtoupper($data['eqt_code']);
        $data['type_code'] = strtoupper($data['type_code']);
        $data['sort_order'] = EquipmentType::max('sort_order') + 1;

        EquipmentType::create($data);

        return back()->with('success', "Equipment type {$data['eqt_code']} added successfully.");
    }

    public function update(Request $request, EquipmentType $equipmentType)
    {
        $data = $request->validate([
            'eqt_code'    => "required|string|max:10|unique:equipment_types,eqt_code,{$equipmentType->id}",
            'iso_code'    => "nullable|string|max:10|unique:equipment_types,iso_code,{$equipmentType->id}",
            'size'        => 'required|string|max:5',
            'type_code'   => 'required|string|max:5',
            'height'      => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
        ]);

        $data['eqt_code']  = strtoupper($data['eqt_code']);
        $data['type_code'] = strtoupper($data['type_code']);

        $equipmentType->update($data);

        return back()->with('success', "Equipment type {$equipmentType->eqt_code} updated.");
    }

    public function toggleActive(EquipmentType $equipmentType)
    {
        $equipmentType->update(['is_active' => !$equipmentType->is_active]);
        $state = $equipmentType->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$equipmentType->eqt_code} {$state}.");
    }

    public function destroy(EquipmentType $equipmentType)
    {
        $equipmentType->delete();

        return back()->with('success', "Equipment type {$equipmentType->eqt_code} deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer|exists:equipment_types,id']);

        foreach ($request->order as $position => $id) {
            EquipmentType::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
