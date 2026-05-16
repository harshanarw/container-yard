<?php

namespace App\Http\Controllers;

use App\Models\StorageZone;
use App\Models\YardLocation;
use Illuminate\Http\Request;

class StorageZoneController extends Controller
{
    public function index()
    {
        $zones = StorageZone::withCount([
            'yardLocations',
            'yardLocations as empty_count'    => fn($q) => $q->where('status', 'empty'),
            'yardLocations as occupied_count' => fn($q) => $q->where('status', 'occupied'),
        ])->orderBy('sort_order')->get();

        return view('masters.zones.index', compact('zones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:10', 'unique:storage_zones,code'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color'       => ['required', 'string', 'max:20'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        StorageZone::create(array_merge($validated, ['is_active' => true, 'sort_order' => $validated['sort_order'] ?? 0]));

        return back()->with('success', "Zone '{$validated['name']}' created.");
    }

    public function update(Request $request, StorageZone $zone)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:10', 'unique:storage_zones,code,' . $zone->id],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'color'       => ['required', 'string', 'max:20'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $zone->update($validated);

        return back()->with('success', "Zone updated.");
    }

    public function toggleActive(StorageZone $zone)
    {
        $zone->update(['is_active' => !$zone->is_active]);
        return back()->with('success', "Zone " . ($zone->is_active ? 'activated' : 'deactivated') . ".");
    }

    public function destroy(StorageZone $zone)
    {
        if (YardLocation::where('zone', $zone->code)->exists()) {
            return back()->with('error', "Cannot delete zone '{$zone->name}' — it has yard locations assigned.");
        }
        $zone->delete();
        return back()->with('success', "Zone deleted.");
    }
}
