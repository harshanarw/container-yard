<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerGrade;
use App\Models\Customer;
use App\Models\ContainerHold;
use App\Models\EquipmentType;
use App\Models\YardLocation;
use App\Services\ContainerStatusService;
use App\Services\HoldService;
use App\Support\Export\TabularExport;
use App\Support\MrStatusCatalogue;
use Illuminate\Http\Request;

class ContainerController extends Controller
{
    public function __construct()
    {
        // The export shows exactly what the screen shows, so it rides on the
        // same grant — a route that is reachable without one is not protected
        // by the screen's button being hidden.
        $this->middleware('can:containers.view')->only(['index', 'show', 'masterLookup', 'availableStock', 'exportAvailableStock']);
        $this->middleware('can:containers.create')->only(['create', 'store']);
        $this->middleware('can:containers.edit')->only(['edit', 'update', 'markAvailable']);
        $this->middleware('can:containers.hold')->only(['placeHold', 'clearHold']);
        $this->middleware('can:containers.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $containers = Container::with(['customer', 'equipmentType'])
            ->withCount(['holds as active_holds_count' => fn ($q) => $q->whereNull('cleared_at')])
            ->when($request->search, fn ($q, $v) =>
                $q->where('container_no', 'like', "%{$v}%")
                  ->orWhere('owner_name', 'like', "%{$v}%")
                  ->orWhere('manufacturer', 'like', "%{$v}%")
            )
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->status,   fn ($q, $v) => $q->where('status', $v))
            ->when($request->size,     fn ($q, $v) => $q->where('size', $v))
            ->when($request->boolean('held'), fn ($q) => $q->held())
            // M&R status — indexed columns, so these cost no more than the
            // disposition filter beside them.
            ->when($request->mr_status,       fn ($q, $v) => $q->where('mr_status', $v))
            ->when($request->mr_status_group, fn ($q, $v) => $q->where('mr_status_group', $v))
            ->when($request->boolean('export_ready'), fn ($q) => $q->exportReady())
            ->orderBy('container_no')
            ->paginate(25)
            ->withQueryString();

        $mrStatusesByLane = MrStatusCatalogue::codesByLane();
        $mrStatusGroups   = MrStatusCatalogue::groups();

        return view('containers.index', compact('containers', 'mrStatusesByLane', 'mrStatusGroups'));
    }

    public function create()
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->orderBy('sort_order')->get();
        $grades         = ContainerGrade::active()->orderBy('sort_order')->get();

        return view('containers.create', compact('customers', 'equipmentTypes', 'grades'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated = $this->deriveEquipmentFields($validated);

        // A manually-registered container is stock, not a physical arrival, so it
        // starts 'available' (the DB default 'in_yard' is for gate-in only).
        $validated['status']          = $validated['status'] ?? 'available';
        $validated['available_since'] = $validated['status'] === 'available' ? now() : null;

        $container = Container::create($validated);

        return redirect()->route('containers.show', $container)
            ->with('success', "Container {$container->container_no} created successfully.");
    }

    /**
     * Available empties stock — sound/repaired containers ready for allocation,
     * grouped by size / type / grade, with dwell-aging buckets from available_since.
     */
    /**
     * The available-stock roll-up, defined once so the screen and the export
     * cannot come apart.
     *
     * @return \Illuminate\Support\Collection
     */
    private function availableStockRows()
    {
        $now = now();

        return Container::available()
            ->with(['grade', 'equipmentType'])
            ->withCount(['holds as active_holds_count' => fn ($q) => $q->whereNull('cleared_at')])
            ->get()
            ->groupBy(fn ($c) => $c->size . ' ' . $c->type_code . ' · ' . ($c->grade->code ?? 'Ungraded'))
            ->map(function ($items, $label) use ($now) {
                $days = $items->map(fn ($c) => $c->available_since ? (int) $c->available_since->diffInDays($now) : 0);

                // 'available' is a disposition; export-ready is a verdict about
                // whether the box may actually leave. They come apart — a held
                // container, or a reefer whose PTI lapsed, sits in available
                // stock and cannot be shipped. Operators reconcile that gap by
                // eye today; this counts it.
                $ready = $items->filter(fn ($c) => $c->export_ready && ! $c->mrStatusHasExpired());

                return [
                    'label'      => $label,
                    'count'      => $items->count(),
                    'ready'      => $ready->count(),
                    'not_ready'  => $items->count() - $ready->count(),
                    'held'       => $items->filter(fn ($c) => ($c->active_holds_count ?? 0) > 0)->count(),
                    'pti_lapsed' => $items->filter(fn ($c) => $c->mrStatusHasExpired())->count(),
                    'fresh'      => $days->filter(fn ($d) => $d <= 7)->count(),
                    'aging'      => $days->filter(fn ($d) => $d > 7 && $d <= 30)->count(),
                    'stale'      => $days->filter(fn ($d) => $d > 30)->count(),
                    'avg_days'   => (int) round($days->avg() ?? 0),
                    'max_days'   => (int) ($days->max() ?? 0),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    public function availableStock()
    {
        $rows = $this->availableStockRows();

        $total      = (int) $rows->sum('count');
        $totalReady = (int) $rows->sum('ready');

        // The containers behind the gap, so it can be acted on rather than just
        // counted. Capped — this is a prompt to go and look, not a work queue.
        $notReady = Container::available()
            ->where(fn ($q) => $q->where('export_ready', false)->orWhere(fn ($s) => $s->statusExpired()))
            ->with('grade')
            ->withCount(['holds as active_holds_count' => fn ($q) => $q->whereNull('cleared_at')])
            ->orderBy('container_no')
            ->limit(50)
            ->get();

        return view('containers.available-stock', compact('rows', 'total', 'totalReady', 'notReady'));
    }

    /** Place a hold on a container (blocks allocation and gate-out until cleared). */
    public function placeHold(Request $request, Container $container, HoldService $holds)
    {
        $validated = $request->validate([
            'hold_type' => ['required', 'string', 'in:' . implode(',', array_keys(ContainerHold::TYPES))],
            'reason'    => ['nullable', 'string', 'max:255'],
        ]);

        $holds->place($container, $validated['hold_type'], $validated['reason'] ?? null, auth()->id());

        return back()->with('success', "Hold placed on {$container->container_no}.");
    }

    /** Clear a specific hold. */
    public function clearHold(Request $request, Container $container, ContainerHold $hold, HoldService $holds)
    {
        abort_unless($hold->container_id === $container->id, 404);

        $request->validate(['clear_notes' => ['nullable', 'string', 'max:255']]);
        $holds->clear($hold, $request->input('clear_notes'), auth()->id());

        return back()->with('success', "Hold cleared on {$container->container_no}.");
    }

    /**
     * Manually move a container into the available pool (e.g. sound on inspection,
     * or repaired outside the work-order flow). Only meaningful while the container
     * is physically in the yard.
     */
    public function markAvailable(Container $container, ContainerStatusService $status)
    {
        if ($container->status === 'released') {
            return back()->with('error', 'A released container is not in the yard — gate it in before marking available.');
        }
        if ($container->status === 'available') {
            return back()->with('info', 'Container is already available.');
        }

        $status->markAvailable($container);

        return back()->with('success', "Container {$container->container_no} marked available.");
    }

    public function show(Container $container)
    {
        $container->load([
            'customer', 'equipmentType',
            'gateMovements' => fn ($q) => $q->latest()->take(10),
            'yardLocation',
            'activeHire.originalCustomer',
            'activeHire.hireCustomer',
            'hires' => fn ($q) => $q->with(['originalCustomer', 'hireCustomer'])->latest('on_hire_date')->take(10),
            'ptiInspections' => fn ($q) => $q->with('inspectedBy')->latest('inspected_at')->take(10),
        ]);

        return view('containers.show', compact('container'));
    }

    public function edit(Container $container)
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->orderBy('sort_order')->get();
        $grades         = ContainerGrade::active()->orderBy('sort_order')->get();

        return view('containers.edit', compact('container', 'customers', 'equipmentTypes', 'grades'));
    }

    public function update(Request $request, Container $container)
    {
        $validated = $request->validate($this->rules($container->id));
        $validated = $this->deriveEquipmentFields($validated);

        $container->update($validated);

        return redirect()->route('containers.show', $container)
            ->with('success', 'Container master record updated successfully.');
    }

    public function destroy(Container $container)
    {
        if ($container->gateMovements()->exists()) {
            return back()->with('error', 'Cannot delete container with gate movements on record.');
        }

        // Release yard slot if still occupied
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        $container->delete();

        return redirect()->route('containers.index')
            ->with('success', 'Container master record deleted.');
    }

    // AJAX: look up a container number and return master fields (used by Gate-In form)
    public function masterLookup(Request $request)
    {
        $no = strtoupper(trim($request->query('container_no', '')));

        if (!$no) {
            return response()->json(['found' => false]);
        }

        $container = Container::with('equipmentType')
            ->where('container_no', $no)
            ->first();

        if (!$container) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'                   => true,
            'category'                => $container->category,
            'equipment_type_id'       => $container->equipment_type_id,
            'grade_id'                => $container->grade_id,
            'size'                    => $container->size,
            'type_code'               => $container->type_code,
            'manufacture_year'        => $container->manufacture_year,
            'manufacturer'            => $container->manufacturer,
            'owner_code'              => $container->owner_code,
            'owner_name'              => $container->owner_name,
            'gross_weight_kg'         => $container->gross_weight_kg,
            'tare_weight_kg'          => $container->tare_weight_kg,
            'max_payload_kg'          => $container->max_payload_kg,
            'csc_plate_no'            => $container->csc_plate_no,
            'csc_expiry_date'         => $container->csc_expiry_date?->format('Y-m-d'),
            'status'                  => $container->status,
            'gate_in_date'            => $container->gate_in_date?->format('d M Y'),
            'customer_id'             => $container->customer_id,
            // Effective ventilation: container's own value, or EQT default
            'ventilation_type'        => $container->effective_ventilation_type,
            'vent_count'              => $container->effective_vent_count,
            'ventilation_type_source' => $container->ventilation_type ? 'container' : 'eqt',
        ]);
    }

    private function rules(?int $exceptId = null): array
    {
        $uniqueRule = 'unique:containers,container_no' . ($exceptId ? ",{$exceptId}" : '');

        $rules = [
            // ── Identity ─────────────────────────────────────────────────
            'container_no'      => ['required', 'string', 'max:12', $uniqueRule, 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'category'          => ['required', 'in:consignee,owned,leased'],
            'equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'grade_id'          => ['nullable', 'exists:container_grades,id'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1970', 'max:' . (date('Y') + 1)],
            'manufacturer'      => ['nullable', 'string', 'max:100'],
            // ── Ownership ────────────────────────────────────────────────
            'owner_code'        => ['nullable', 'string', 'max:20'],
            'owner_name'        => ['nullable', 'string', 'max:100'],
            'owner_customer_id' => ['nullable', 'exists:customers,id'],
            // customer_id is accepted on create only — see below.
            // ── Leasing (only relevant when category = 'leased') ─────────
            'lessor_name'       => ['nullable', 'string', 'max:150'],
            'lessor_code'       => ['nullable', 'string', 'max:30'],
            'lease_reference'   => ['nullable', 'string', 'max:100'],
            'lease_start_date'  => ['nullable', 'date'],
            'lease_end_date'    => ['nullable', 'date', 'after_or_equal:lease_start_date'],
            // ── Weight specs ─────────────────────────────────────────────
            'gross_weight_kg'   => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'tare_weight_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_payload_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            // ── CSC ──────────────────────────────────────────────────────
            'csc_plate_no'      => ['nullable', 'string', 'max:50'],
            'csc_expiry_date'   => ['nullable', 'date'],
            // ── Notes ────────────────────────────────────────────────────
            'notes'             => ['nullable', 'string'],
            // ── Ventilation ──────────────────────────────────────────────
            'ventilation_type'  => ['nullable', 'in:none,passive,cross,mechanical,reefer,controlled_atm'],
            'vent_count'        => ['nullable', 'integer', 'min:0', 'max:99'],
        ];

        // The customer belongs to the *visit*, not to the box: gate-in sets it
        // and gate-out reads it from the visit's job. Editing it here used to
        // silently re-point the current visit, which is how the gate-in and
        // gate-out parties drifted apart.
        //
        // It is still accepted on create, because containers.customer_id is
        // NOT NULL and a hand-registered container has no visit to inherit
        // from. On edit it is omitted, so the master screen can no longer
        // change it — that screen edits the owner.
        if ($exceptId === null) {
            $rules['customer_id'] = ['required', 'exists:customers,id'];
        }

        return $rules;
    }

    private function deriveEquipmentFields(array $data): array
    {
        if (!empty($data['equipment_type_id'])) {
            $eqt = EquipmentType::find($data['equipment_type_id']);
            if ($eqt) {
                $data['size']      = $eqt->size;
                $data['type_code'] = $eqt->type_code;
            }
        }
        return $data;
    }

    /**
     * Available stock, as a file.
     *
     * The screen is a roll-up rather than a list — one row per size · type ·
     * grade — so that is what the file carries. Held and PTI-lapsed counts ride
     * along: they are computed for the screen already but only surface there in
     * a tooltip, and they are exactly what somebody planning allocations needs
     * beside the not-ready total.
     */
    public function exportAvailableStock(Request $request)
    {
        $rows = $this->availableStockRows();

        return TabularExport::stream($request->input('format'), 'available-stock', [
            'Size · Type · Grade', 'Available', 'Ready', 'Not Ready',
            'On Hold', 'PTI Lapsed',
            'Fresh (≤7d)', 'Aging (8–30d)', 'Stale (>30d)',
            'Avg Days', 'Oldest (days)',
        ], function () use ($rows) {
            foreach ($rows as $r) {
                yield [
                    $r['label'],
                    $r['count'],
                    $r['ready'],
                    $r['not_ready'],
                    $r['held'],
                    $r['pti_lapsed'],
                    $r['fresh'],
                    $r['aging'],
                    $r['stale'],
                    $r['avg_days'],
                    $r['max_days'],
                ];
            }
        });
    }
}
