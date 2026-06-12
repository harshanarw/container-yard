<?php

namespace App\Http\Controllers;

use App\Models\MrCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MrCodeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.mr-codes.view')->only(['index']);
        $this->middleware('can:masters.mr-codes.create')->only(['store']);
        $this->middleware('can:masters.mr-codes.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.mr-codes.delete')->only(['destroy']);
    }

    /** Resolve and validate the code type from the route segment. */
    private function resolveType(string $type): string
    {
        abort_unless(in_array($type, MrCode::validTypes(), true), 404);
        return $type;
    }

    public function index(string $mrCodeType)
    {
        $type  = $this->resolveType($mrCodeType);
        $items = MrCode::ofType($type)->orderBy('sort_order')->orderBy('code')->get();

        return view('masters.mr-codes.index', [
            'type'      => $type,
            'typeLabel' => MrCode::TYPES[$type],
            'items'     => $items,
        ]);
    }

    public function store(Request $request, string $mrCodeType)
    {
        $type = $this->resolveType($mrCodeType);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:10', Rule::unique('mr_codes')->where('type', $type)],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $data['type']       = $type;
        $data['code']       = strtoupper(trim($data['code']));
        $data['sort_order'] = MrCode::ofType($type)->max('sort_order') + 1;

        MrCode::create($data);

        return back()->with('success', "{$data['code']} — {$data['name']} added.");
    }

    public function update(Request $request, string $mrCodeType, MrCode $mrCode)
    {
        $type = $this->resolveType($mrCodeType);
        abort_unless($mrCode->type === $type, 404);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:10',
                              Rule::unique('mr_codes')->where('type', $type)->ignore($mrCode->id)],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $mrCode->update($data);

        return back()->with('success', "{$mrCode->code} — {$mrCode->name} updated.");
    }

    public function toggleActive(string $mrCodeType, MrCode $mrCode)
    {
        $type = $this->resolveType($mrCodeType);
        abort_unless($mrCode->type === $type, 404);

        $mrCode->update(['is_active' => !$mrCode->is_active]);
        $state = $mrCode->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$mrCode->code}\" {$state}.");
    }

    public function destroy(string $mrCodeType, MrCode $mrCode)
    {
        $type = $this->resolveType($mrCodeType);
        abort_unless($mrCode->type === $type, 404);

        $mrCode->delete();

        return back()->with('success', "{$mrCode->code} — {$mrCode->name} deleted.");
    }

    public function reorder(Request $request, string $mrCodeType)
    {
        $this->resolveType($mrCodeType);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:mr_codes,id'],
        ]);

        foreach ($request->order as $position => $id) {
            MrCode::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
