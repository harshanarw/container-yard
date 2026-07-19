<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\GateMovement;
use App\Models\GuardCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Driver master admin (Phase 2): list / search, view movement history, edit, and
 * merge duplicate records. Gated to operations-management roles (the granular
 * masters.* permissions aren't seeded for this new master, so we gate by role).
 */
class DriverController extends Controller
{
    private const MANAGE_ROLES = ['yard_supervisor', 'administrator', 'system_administrator'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! in_array(auth()->user()->role ?? null, self::MANAGE_ROLES, true)) {
                abort(403, 'Operations management access required.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $drivers = Driver::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name', 'like', "%{$q}%")
                ->orWhere('nic_number', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('masters.drivers.index', compact('drivers', 'q'));
    }

    public function show(Driver $driver)
    {
        $nic = $driver->nic_number;

        $movements = GateMovement::where('driver_ic', $nic)->orderByDesc('id')->limit(200)->get();
        $captures  = GuardCapture::where('nic_number', $nic)->orderByDesc('id')->limit(200)->get();

        // One combined timeline, newest first.
        $timeline = collect();
        foreach ($movements as $m) {
            $timeline->push([
                'when'   => $m->gate_out_time ?? $m->gate_in_time ?? $m->created_at,
                'label'  => $m->movement_type === 'in' ? 'Gate In' : 'Gate Out',
                'ref'    => $m->container_no,
                'detail' => trim(($m->vehicle_plate ? 'Vehicle ' . $m->vehicle_plate : '') . ($m->driver_phone ? ' · ' . $m->driver_phone : ''), ' ·'),
                'url'    => route('yard.movements.edit', $m),
            ]);
        }
        foreach ($captures as $c) {
            $timeline->push([
                'when'   => $c->captured_at ?? $c->created_at,
                'label'  => 'Guard Post ' . ($c->direction === 'gate_in' ? 'In' : 'Out'),
                'ref'    => $c->reference_no,
                'detail' => trim(($c->vehicle_number ? 'Vehicle ' . $c->vehicle_number : '') . ($c->driver_phone ? ' · ' . $c->driver_phone : ''), ' ·'),
                'url'    => null,
            ]);
        }
        $timeline = $timeline->sortByDesc(fn ($t) => optional($t['when'])->getTimestamp() ?? 0)->values();

        // Likely duplicates: another master row sharing this driver's phone or name.
        $duplicates = Driver::where('id', '!=', $driver->id)
            ->where(function ($w) use ($driver) {
                if (filled($driver->phone)) { $w->orWhere('phone', $driver->phone); }
                if (filled($driver->name))  { $w->orWhere('name', $driver->name); }
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return view('masters.drivers.show', compact('driver', 'timeline', 'duplicates'));
    }

    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'name'           => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'nic_number'     => ['required', 'string', 'max:30', Rule::unique('drivers', 'nic_number')->ignore($driver->id)],
        ]);

        $data['nic_number'] = Driver::normalizeNic($data['nic_number']);
        $data['updated_by'] = auth()->id();
        $driver->update($data);

        return redirect()->route('masters.drivers.show', $driver)->with('success', 'Driver updated.');
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'survivor_id'  => ['required', 'exists:drivers,id'],
            'duplicate_id' => ['required', 'exists:drivers,id', 'different:survivor_id'],
        ]);

        $survivor = Driver::findOrFail($data['survivor_id']);
        $dup      = Driver::findOrFail($data['duplicate_id']);
        $dupNic   = $dup->nic_number;

        DB::transaction(function () use ($survivor, $dup) {
            // Re-point history from the duplicate's NIC to the survivor's so the
            // survivor's timeline becomes complete.
            GateMovement::where('driver_ic', $dup->nic_number)->update(['driver_ic' => $survivor->nic_number]);
            GuardCapture::where('nic_number', $dup->nic_number)->update(['nic_number' => $survivor->nic_number]);

            $survivor->movement_count += $dup->movement_count;
            if (blank($survivor->phone) && filled($dup->phone))                   { $survivor->phone = $dup->phone; }
            if (blank($survivor->name) && filled($dup->name))                     { $survivor->name = $dup->name; }
            if (blank($survivor->license_number) && filled($dup->license_number)) { $survivor->license_number = $dup->license_number; }
            if ($dup->last_seen_at && (! $survivor->last_seen_at || $dup->last_seen_at->gt($survivor->last_seen_at))) {
                $survivor->last_seen_at = $dup->last_seen_at;
            }
            $survivor->updated_by = auth()->id();
            $survivor->save();

            $dup->delete();
        });

        return redirect()->route('masters.drivers.show', $survivor)
            ->with('success', "Merged {$dupNic} into {$survivor->nic_number}.");
    }

    public function destroy(Driver $driver)
    {
        $nic = $driver->nic_number;
        $driver->delete();

        return redirect()->route('masters.drivers.index')->with('success', "Driver {$nic} removed from the master.");
    }
}
