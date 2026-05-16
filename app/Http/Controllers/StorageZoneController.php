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

    // ── Slot Configuration ───────────────────────────────────────────────────

    public function slots(StorageZone $zone)
    {
        $slots = YardLocation::where('zone', $zone->code)
            ->with('container:id,container_no')
            ->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get();

        $stats = [
            'total'    => $slots->count(),
            'empty'    => $slots->where('status', 'empty')->count(),
            'occupied' => $slots->where('status', 'occupied')->count(),
            'reserved' => $slots->where('status', 'reserved')->count(),
            'other'    => $slots->whereNotIn('status', ['empty','occupied','reserved'])->count(),
        ];

        return view('masters.zones.slots', compact('zone', 'slots', 'stats'));
    }

    public function generateSlots(Request $request, StorageZone $zone)
    {
        $request->validate([
            'rows'  => ['required', 'string', 'max:200'],
            'bays'  => ['required', 'string', 'max:200'],
            'tiers' => ['required', 'string', 'max:200'],
        ]);

        $rows  = $this->parseRangeInput($request->rows, 'alpha');
        $bays  = $this->parseRangeInput($request->bays, 'numeric');
        $tiers = $this->parseRangeInput($request->tiers, 'numeric');

        if (empty($rows) || empty($bays) || empty($tiers)) {
            return back()->with('error', 'Invalid range input. Use values like "A,B,C" or "1-5".');
        }

        if (count($rows) * count($bays) * count($tiers) > 500) {
            return back()->with('error', 'Too many slots — maximum 500 per bulk generate (max 500 combinations).');
        }

        $created = 0;
        $skipped = 0;
        $now = now();

        foreach ($rows as $row) {
            foreach ($bays as $bay) {
                foreach ($tiers as $tier) {
                    $exists = YardLocation::where([
                        'zone' => $zone->code,
                        'row'  => $row,
                        'bay'  => $bay,
                        'tier' => $tier,
                    ])->exists();

                    if ($exists) {
                        $skipped++;
                    } else {
                        YardLocation::create([
                            'zone'            => $zone->code,
                            'row'             => $row,
                            'bay'             => (int) $bay,
                            'tier'            => (int) $tier,
                            'status'          => 'empty',
                            'last_updated_at' => $now,
                        ]);
                        $created++;
                    }
                }
            }
        }

        $msg = "{$created} slot(s) created for Zone {$zone->code}.";
        if ($skipped > 0) {
            $msg .= " {$skipped} already existed and were skipped.";
        }

        return back()->with('success', $msg);
    }

    public function destroySlot(StorageZone $zone, YardLocation $slot)
    {
        if ($slot->zone !== $zone->code) {
            abort(404);
        }
        if ($slot->status !== 'empty') {
            return back()->with('error', "Slot {$slot->slot_code} cannot be deleted — it is currently {$slot->status}.");
        }
        $slot->delete();
        return back()->with('success', "Slot {$slot->slot_code} deleted.");
    }

    public function clearSlots(Request $request, StorageZone $zone)
    {
        $deleted = YardLocation::where('zone', $zone->code)
            ->where('status', 'empty')
            ->delete();

        return back()->with('success', "{$deleted} empty slot(s) removed from Zone {$zone->code}.");
    }

    private function parseRangeInput(string $input, string $type): array
    {
        $input = trim($input);
        $values = [];

        // Range syntax: "A-E" or "1-10"
        if (preg_match('/^([A-Za-z0-9]+)\s*-\s*([A-Za-z0-9]+)$/', $input, $m)) {
            $start = strtoupper(trim($m[1]));
            $end   = strtoupper(trim($m[2]));

            if ($type === 'alpha' && ctype_alpha($start) && ctype_alpha($end)) {
                $s = ord($start);
                $e = ord($end);
                if ($s <= $e && ($e - $s) <= 25) {
                    for ($i = $s; $i <= $e; $i++) {
                        $values[] = chr($i);
                    }
                }
            } elseif ($type === 'numeric' && is_numeric($start) && is_numeric($end)) {
                $s = (int) $start;
                $e = (int) $end;
                if ($s <= $e && ($e - $s) <= 99) {
                    for ($i = $s; $i <= $e; $i++) {
                        $values[] = (string) $i;
                    }
                }
            }
        } else {
            // Comma-separated: "A,B,C" or "1,2,3"
            foreach (explode(',', $input) as $item) {
                $item = strtoupper(trim($item));
                if ($item !== '') {
                    $values[] = $item;
                }
            }
        }

        return array_unique($values);
    }
}
