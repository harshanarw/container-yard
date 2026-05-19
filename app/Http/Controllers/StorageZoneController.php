<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\LocationAdjustment;
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

        $recentAdjustments = LocationAdjustment::where('zone', $zone->code)
            ->with('adjustedBy:id,name')
            ->latest()
            ->take(30)
            ->get();

        return view('masters.zones.slots', compact('zone', 'slots', 'stats', 'recentAdjustments'));
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

    public function moveSlot(Request $request, StorageZone $zone)
    {
        $validated = $request->validate([
            'from_row'  => ['required', 'string', 'max:5'],
            'from_bay'  => ['required', 'integer', 'min:1'],
            'from_tier' => ['required', 'integer', 'min:1'],
            'to_row'    => ['required', 'string', 'max:5'],
            'to_bay'    => ['required', 'integer', 'min:1'],
            'to_tier'   => ['required', 'integer', 'min:1'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $fromSlot = YardLocation::where([
            'zone' => $zone->code,
            'row'  => $validated['from_row'],
            'bay'  => $validated['from_bay'],
            'tier' => $validated['from_tier'],
        ])->first();

        if (!$fromSlot || $fromSlot->status !== 'occupied' || !$fromSlot->container_id) {
            return response()->json(['error' => 'Source slot is not occupied or does not exist.'], 422);
        }

        $toSlot = YardLocation::where([
            'zone' => $zone->code,
            'row'  => $validated['to_row'],
            'bay'  => $validated['to_bay'],
            'tier' => $validated['to_tier'],
        ])->first();

        if (!$toSlot) {
            return response()->json(['error' => 'Target slot does not exist.'], 422);
        }
        if ($toSlot->status !== 'empty') {
            return response()->json(['error' => 'Target slot is not empty.'], 422);
        }

        $containerId = $fromSlot->container_id;
        $containerNo = $fromSlot->container?->container_no ?? '';

        // Release old slot
        $fromSlot->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        // Occupy new slot
        $toSlot->update([
            'container_id'    => $containerId,
            'status'          => 'occupied',
            'last_updated_at' => now(),
        ]);

        // Sync Container record
        Container::where('id', $containerId)->update([
            'location_zone' => $zone->code,
            'location_row'  => $validated['to_row'],
            'location_bay'  => $validated['to_bay'],
            'location_tier' => $validated['to_tier'],
        ]);

        // Write audit record
        LocationAdjustment::create([
            'container_id' => $containerId,
            'container_no' => $containerNo,
            'zone'         => $zone->code,
            'from_row'     => $validated['from_row'],
            'from_bay'     => $validated['from_bay'],
            'from_tier'    => $validated['from_tier'],
            'to_row'       => $validated['to_row'],
            'to_bay'       => $validated['to_bay'],
            'to_tier'      => $validated['to_tier'],
            'notes'        => $validated['notes'] ?? null,
            'adjusted_by'  => auth()->id(),
        ]);

        $toCode = "{$zone->code}-{$validated['to_row']}{$validated['to_bay']}-T{$validated['to_tier']}";

        return response()->json([
            'success' => true,
            'message' => "Container {$containerNo} moved to {$toCode}.",
        ]);
    }

    public function destroySlot(StorageZone $zone, YardLocation $slot)
    {
        if ($slot->zone !== $zone->code) {
            abort(404);
        }
        if ($slot->status !== 'empty') {
            return back()->with('error', "Slot {$slot->slot_code} cannot be deleted — it is currently {$slot->status}.");
        }

        $aboveTiers = YardLocation::where('zone', $zone->code)
            ->where('row',  $slot->row)
            ->where('bay',  $slot->bay)
            ->where('tier', '>', $slot->tier)
            ->orderBy('tier')
            ->pluck('tier')
            ->map(fn($t) => "T{$t}")
            ->implode(', ');

        if ($aboveTiers) {
            return back()->with('error',
                "Cannot delete {$slot->slot_code} — slot(s) {$aboveTiers} exist above it in the same stack. Remove top tiers first.");
        }

        $slot->delete();
        return back()->with('success', "Slot {$slot->slot_code} deleted.");
    }

    public function clearSlots(Request $request, StorageZone $zone)
    {
        // Only delete empty slots that have no other empty slots above them in the same stack.
        // We iterate from the highest tier downward so stacks are unwound top-first.
        $deleted = 0;
        $emptySlots = YardLocation::where('zone', $zone->code)
            ->where('status', 'empty')
            ->orderByDesc('tier')
            ->get();

        foreach ($emptySlots as $slot) {
            $blockedByAbove = YardLocation::where('zone', $zone->code)
                ->where('row',  $slot->row)
                ->where('bay',  $slot->bay)
                ->where('tier', '>', $slot->tier)
                ->exists();

            if (!$blockedByAbove) {
                $slot->delete();
                $deleted++;
            }
        }

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
