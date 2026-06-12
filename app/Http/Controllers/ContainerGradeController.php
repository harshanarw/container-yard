<?php

namespace App\Http\Controllers;

use App\Models\ContainerGrade;
use Illuminate\Http\Request;

class ContainerGradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.container-grades.view')->only(['index']);
        $this->middleware('can:masters.container-grades.create')->only(['store']);
        $this->middleware('can:masters.container-grades.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.container-grades.delete')->only(['destroy']);
    }

    public function index()
    {
        $items = ContainerGrade::orderBy('sort_order')->orderBy('code')->get();

        return view('masters.container-grades.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:10|unique:container_grades,code',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'required|in:success,primary,info,secondary,warning,danger',
        ]);

        $data['code']       = strtoupper($data['code']);
        $data['sort_order'] = ContainerGrade::max('sort_order') + 1;

        ContainerGrade::create($data);

        return back()->with('success', "Grade {$data['code']} — {$data['name']} added successfully.");
    }

    public function update(Request $request, ContainerGrade $containerGrade)
    {
        $data = $request->validate([
            'code'        => "required|string|max:10|unique:container_grades,code,{$containerGrade->id}",
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'required|in:success,primary,info,secondary,warning,danger',
        ]);

        $data['code'] = strtoupper($data['code']);

        $containerGrade->update($data);

        return back()->with('success', "Grade {$containerGrade->code} updated.");
    }

    public function toggleActive(ContainerGrade $containerGrade)
    {
        $containerGrade->update(['is_active' => !$containerGrade->is_active]);
        $state = $containerGrade->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Grade {$containerGrade->code} {$state}.");
    }

    public function destroy(ContainerGrade $containerGrade)
    {
        if ($containerGrade->containers()->exists() || $containerGrade->gateMovements()->exists()) {
            return back()->with('error', "Cannot delete grade {$containerGrade->code} — it is in use by containers or gate movements.");
        }

        $containerGrade->delete();

        return back()->with('success', "Grade {$containerGrade->code} deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer|exists:container_grades,id']);

        foreach ($request->order as $position => $id) {
            ContainerGrade::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
