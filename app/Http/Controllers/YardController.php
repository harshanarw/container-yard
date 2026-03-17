<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\GateMovementPhoto;
use App\Models\Inquiry;
use App\Models\StorageMasterHeader;
use App\Models\YardLocation;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class YardController extends Controller
{
    // -------------------------------------------------------------------------
    // Internal helper — save an uploaded photo to public/images/gate-movements/
    // Returns the path relative to public/ (used as photo_path in DB).
    // -------------------------------------------------------------------------
    private function saveMovementPhoto($file, string $type, int $movementId): string
    {
        $dir = public_path("images/gate-movements/{$type}/{$movementId}");
        File::ensureDirectoryExists($dir);

        $filename = Str::random(16) . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        return "images/gate-movements/{$type}/{$movementId}/{$filename}";
    }

    // -------------------------------------------------------------------------
    // Yard Overview (visual grid map)
    // -------------------------------------------------------------------------
    public function index()
    {
        $yardGrid = YardLocation::with('container.customer')
            ->orderBy('row')
            ->orderBy('bay')
            ->orderBy('tier')
            ->get()
            ->groupBy('row');

        $summary = [
            'total'     => YardLocation::count(),
            'occupied'  => YardLocation::where('status', 'occupied')->count(),
            'empty'     => YardLocation::where('status', 'empty')->count(),
            'in_repair' => YardLocation::where('status', 'in_repair')->count(),
            'reserved'  => YardLocation::where('status', 'reserved')->count(),
        ];

        return view('yard.index', compact('yardGrid', 'summary'));
    }

    // -------------------------------------------------------------------------
    // Gate Operations
    // -------------------------------------------------------------------------
    public function gate()
    {
        $recentMovements = GateMovement::with(['container', 'customer', 'createdBy'])
            ->latest()
            ->take(20)
            ->get();

        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->get();
        $emptySlots     = YardLocation::where('status', 'empty')
            ->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get();

        return view('yard.gate', compact('recentMovements', 'customers', 'emptySlots', 'equipmentTypes'));
    }

    public function gateIn(Request $request)
    {
        $validated = $request->validate([
            'container_no'      => ['required', 'string', 'max:12', 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'customer_id'       => ['required', 'exists:customers,id'],
            'condition'         => ['required', 'in:sound,damaged,require_repair'],
            'cargo_status'      => ['required', 'in:empty,full'],
            'location_row'      => ['required', 'string', 'max:5'],
            'location_bay'      => ['required', 'integer', 'min:1', 'max:8'],
            'location_tier'     => ['required', 'integer', 'min:1', 'max:5'],
            'seal_no'           => ['nullable', 'string', 'max:20'],
            'vehicle_plate'     => ['nullable', 'string', 'max:20'],
            'remarks'           => ['nullable', 'string'],
            'gate_in_time'      => ['nullable', 'date'],
            'photos'            => ['nullable', 'array', 'max:5'],
            'photos.*'          => ['image', 'max:5120'],
        ]);

        $eqt = EquipmentType::findOrFail($validated['equipment_type_id']);

        // Create or update container record
        $container = Container::updateOrCreate(
            ['container_no' => $validated['container_no']],
            [
                'equipment_type_id' => $eqt->id,
                'size'              => $eqt->size,
                'type_code'         => $eqt->type_code,
                'customer_id'       => $validated['customer_id'],
                'condition'         => $validated['condition'],
                'cargo_status'      => $validated['cargo_status'],
                'status'            => 'in_yard',
                'location_row'      => $validated['location_row'],
                'location_bay'      => $validated['location_bay'],
                'location_tier'     => $validated['location_tier'],
                'seal_no'           => $validated['seal_no'],
                'gate_in_date'      => today(),
                'gate_out_date'     => null,
            ]
        );

        // Record gate movement
        $movement = GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $validated['customer_id'],
            'movement_type'   => 'in',
            'size'            => $eqt->size,
            'container_type'  => $eqt->type_code,
            'location_row'    => $validated['location_row'],
            'location_bay'    => $validated['location_bay'],
            'location_tier'   => $validated['location_tier'],
            'condition'       => $validated['condition'],
            'cargo_status'    => $validated['cargo_status'],
            'seal_no'         => $validated['seal_no'],
            'vehicle_plate'   => $validated['vehicle_plate'],
            'gate_in_time'    => (auth()->user()->isAdmin() && !empty($validated['gate_in_time']))
                                      ? now()->parse($validated['gate_in_time'])
                                      : now(),
            'movement_status' => 'done',
            'remarks'         => $validated['remarks'],
            'created_by'      => auth()->id(),
        ]);

        // Save gate-in photos
        if (!empty($validated['photos'])) {
            foreach ($validated['photos'] as $photo) {
                $path = $this->saveMovementPhoto($photo, 'in', $movement->id);
                GateMovementPhoto::create([
                    'gate_movement_id' => $movement->id,
                    'photo_path'       => $path,
                    'movement_type'    => 'in',
                    'uploaded_by'      => auth()->id(),
                ]);
            }
        }

        // Update yard slot
        YardLocation::where([
            'row'  => $validated['location_row'],
            'bay'  => $validated['location_bay'],
            'tier' => $validated['location_tier'],
        ])->update([
            'container_id'    => $container->id,
            'status'          => 'occupied',
            'last_updated_at' => now(),
        ]);

        // Resolve storage tariff for this customer + equipment type
        $tariffHeader = StorageMasterHeader::where('customer_id', $validated['customer_id'])
            ->where('is_active', true)
            ->where('valid_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', today());
            })
            ->latest('valid_from')
            ->first();

        $freeDays  = $tariffHeader?->default_free_days ?? 0;
        $dailyRate = $tariffHeader
            ? ($tariffHeader->details()->where('equipment_type_id', $validated['equipment_type_id'])->value('storage_rate') ?? 0)
            : 0;

        // Create storage record
        YardStorage::create([
            'container_id' => $container->id,
            'customer_id'  => $validated['customer_id'],
            'gate_in_date' => today(),
            'free_days'    => $freeDays,
            'daily_rate'   => $dailyRate,
        ]);

        return redirect()->route('yard.gate')
            ->with('success', "Gate IN recorded for {$container->container_no}.");
    }

    public function gateOut(Request $request)
    {
        $validated = $request->validate([
            'container_no'  => ['required', 'string', 'exists:containers,container_no'],
            'vehicle_plate'  => ['required', 'string', 'max:20'],
            'driver_name'    => ['required', 'string', 'max:255'],
            'driver_ic'      => ['required', 'string', 'max:30'],
            'release_order'  => ['required', 'string', 'max:50'],
            'remarks'        => ['nullable', 'string'],
            'gate_out_time'  => ['nullable', 'date'],
            'photos'         => ['nullable', 'array', 'max:5'],
            'photos.*'       => ['image', 'max:5120'],
        ]);

        $container = Container::where('container_no', $validated['container_no'])->firstOrFail();

        // Record gate movement
        $movement = GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $container->customer_id,
            'movement_type'   => 'out',
            'size'            => $container->size,
            'container_type'  => $container->type_code,
            'location_row'    => $container->location_row,
            'location_bay'    => $container->location_bay,
            'location_tier'   => $container->location_tier,
            'condition'       => $container->condition,
            'cargo_status'    => $container->cargo_status,
            'vehicle_plate'   => $validated['vehicle_plate'],
            'driver_name'     => $validated['driver_name'],
            'driver_ic'       => $validated['driver_ic'],
            'release_order'   => $validated['release_order'],
            'gate_out_time'   => (auth()->user()->isAdmin() && !empty($validated['gate_out_time']))
                                       ? now()->parse($validated['gate_out_time'])
                                       : now(),
            'movement_status' => 'done',
            'remarks'         => $validated['remarks'],
            'created_by'      => auth()->id(),
        ]);

        // Save gate-out photos
        if (!empty($validated['photos'])) {
            foreach ($validated['photos'] as $photo) {
                $path = $this->saveMovementPhoto($photo, 'out', $movement->id);
                GateMovementPhoto::create([
                    'gate_movement_id' => $movement->id,
                    'photo_path'       => $path,
                    'movement_type'    => 'out',
                    'uploaded_by'      => auth()->id(),
                ]);
            }
        }

        // Finalise storage record
        $storage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->latest()
            ->first();

        if ($storage) {
            $totalDays      = max(1, $storage->gate_in_date->diffInDays(today()));
            $chargeableDays = max(0, $totalDays - $storage->free_days);
            $subtotal       = $chargeableDays * $storage->daily_rate;

            $storage->update([
                'gate_out_date'   => today(),
                'total_days'      => $totalDays,
                'chargeable_days' => $chargeableDays,
                'subtotal'        => $subtotal,
                'total_charge'    => $subtotal,
            ]);
        }

        // Release yard slot
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        // Update container
        $container->update([
            'status'        => 'released',
            'gate_out_date' => today(),
            'location_row'  => null,
            'location_bay'  => null,
            'location_tier' => null,
        ]);

        return redirect()->route('yard.gate')
            ->with('success', "Gate OUT recorded for {$container->container_no}.");
    }

    // -------------------------------------------------------------------------
    // Gate Movement Edit
    // -------------------------------------------------------------------------
    public function editMovement(GateMovement $movement)
    {
        $movement->load(['container', 'customer', 'photos']);
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->get();

        return view('yard.movement-edit', compact('movement', 'customers', 'equipmentTypes'));
    }

    public function updateMovement(Request $request, GateMovement $movement)
    {
        $isAdmin = auth()->user()->isAdmin();

        $rules = [
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'remarks'       => ['nullable', 'string'],
            'condition'     => ['nullable', 'in:sound,damaged,require_repair'],
            'cargo_status'  => ['nullable', 'in:empty,full'],
            'seal_no'       => ['nullable', 'string', 'max:20'],
            'photos'        => ['nullable', 'array', 'max:5'],
            'photos.*'      => ['image', 'max:5120'],
        ];

        if ($movement->movement_type === 'in') {
            $rules['equipment_type_id'] = ['nullable', 'exists:equipment_types,id'];
            $rules['customer_id']       = ['nullable', 'exists:customers,id'];
            $rules['location_row']      = ['nullable', 'string', 'max:5'];
            $rules['location_bay']      = ['nullable', 'integer', 'min:1', 'max:8'];
            $rules['location_tier']     = ['nullable', 'integer', 'min:1', 'max:5'];
            if ($isAdmin) {
                $rules['gate_in_time']  = ['nullable', 'date'];
            }
        } else {
            $rules['driver_name']    = ['nullable', 'string', 'max:255'];
            $rules['driver_ic']      = ['nullable', 'string', 'max:30'];
            $rules['release_order']  = ['nullable', 'string', 'max:50'];
            if ($isAdmin) {
                $rules['gate_out_time'] = ['nullable', 'date'];
            }
        }

        $validated = $request->validate($rules);

        $updateData = array_filter([
            'vehicle_plate' => $validated['vehicle_plate'] ?? null,
            'remarks'       => $validated['remarks'] ?? null,
            'condition'     => $validated['condition'] ?? null,
            'cargo_status'  => $validated['cargo_status'] ?? null,
            'seal_no'       => $validated['seal_no'] ?? null,
        ], fn ($v) => $v !== null);

        if ($movement->movement_type === 'in') {
            if (!empty($validated['equipment_type_id'])) {
                $eqt = EquipmentType::find($validated['equipment_type_id']);
                $updateData['size']            = $eqt->size;
                $updateData['container_type']  = $eqt->type_code;
            }
            foreach (['customer_id', 'location_row', 'location_bay', 'location_tier'] as $field) {
                if (!empty($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }
            if ($isAdmin && !empty($validated['gate_in_time'])) {
                $updateData['gate_in_time'] = now()->parse($validated['gate_in_time']);
            }
        } else {
            foreach (['driver_name', 'driver_ic', 'release_order'] as $field) {
                if (!empty($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }
            if ($isAdmin && !empty($validated['gate_out_time'])) {
                $updateData['gate_out_time'] = now()->parse($validated['gate_out_time']);
            }
        }

        $movement->update($updateData);

        // Save additional photos
        if (!empty($validated['photos'])) {
            foreach ($validated['photos'] as $photo) {
                $path = $this->saveMovementPhoto($photo, $movement->movement_type, $movement->id);
                GateMovementPhoto::create([
                    'gate_movement_id' => $movement->id,
                    'photo_path'       => $path,
                    'movement_type'    => $movement->movement_type,
                    'uploaded_by'      => auth()->id(),
                ]);
            }
        }

        return redirect()->route('yard.gate')
            ->with('success', "Gate movement #{$movement->id} updated successfully.");
    }

    public function destroyMovementPhoto(GateMovement $movement, GateMovementPhoto $photo)
    {
        if ($photo->gate_movement_id !== $movement->id) {
            abort(403);
        }
        @unlink(public_path($photo->photo_path));
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }

    // -------------------------------------------------------------------------
    // Storage Calculator
    // -------------------------------------------------------------------------
    public function storage(Request $request)
    {
        $storageRecords = YardStorage::with(['container', 'customer'])
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status === 'active',   fn ($q) => $q->whereNull('gate_out_date'))
            ->when($request->status === 'completed', fn ($q) => $q->whereNotNull('gate_out_date'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->orderBy('sort_order')->get();

        return view('yard.storage', compact('storageRecords', 'customers', 'equipmentTypes'));
    }

    // -------------------------------------------------------------------------
    // Tariff Lookup (AJAX) — returns active tariff details for a customer
    // -------------------------------------------------------------------------
    public function tariffLookup(int $customerId)
    {
        $header = StorageMasterHeader::with('details.equipmentType')
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('valid_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', today());
            })
            ->latest('valid_from')
            ->first();

        if (! $header) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'         => true,
            'free_days'     => $header->default_free_days,
            'valid_from'    => $header->valid_from->toDateString(),
            'valid_to'      => $header->valid_to?->toDateString(),
            'rates'         => $header->details->map(fn ($d) => [
                'equipment_type_id' => $d->equipment_type_id,
                'eqt_code'          => $d->equipmentType?->eqt_code,
                'description'       => $d->equipmentType?->description,
                'storage_rate'      => (float) $d->storage_rate,
                'currency'          => $d->currency,
            ])->values(),
        ]);
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'container_no' => ['required', 'string', 'exists:containers,container_no'],
            'to_date'      => ['nullable', 'date'],
        ]);

        $container = Container::with('customer')
            ->where('container_no', $validated['container_no'])
            ->firstOrFail();

        $storage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->latest()
            ->first();

        if (!$storage) {
            return response()->json(['error' => 'No active storage record found.'], 404);
        }

        $toDate         = $validated['to_date'] ? now()->parse($validated['to_date']) : today();
        $totalDays      = max(1, $storage->gate_in_date->diffInDays($toDate));
        $chargeableDays = max(0, $totalDays - $storage->free_days);
        $subtotal       = $chargeableDays * $storage->daily_rate;

        return response()->json([
            'container_no'    => $container->container_no,
            'customer'        => $container->customer->name,
            'gate_in_date'    => $storage->gate_in_date->toDateString(),
            'to_date'         => $toDate->toDateString(),
            'total_days'      => $totalDays,
            'free_days'       => $storage->free_days,
            'chargeable_days' => $chargeableDays,
            'daily_rate'      => $storage->daily_rate,
            'subtotal'        => round($subtotal, 2),
        ]);
    }

    // -------------------------------------------------------------------------
    // Survey Lookup (AJAX) — returns survey data for gate-in auto-fill
    // -------------------------------------------------------------------------
    public function surveyLookup(Inquiry $survey)
    {
        $survey->load(['container', 'equipmentType', 'customer']);

        return response()->json([
            'id'                => $survey->id,
            'survey_no'         => $survey->inquiry_no,
            'container_no'      => $survey->container_no,
            'equipment_type_id' => $survey->equipment_type_id,
            'eqt_code'          => $survey->equipmentType?->eqt_code,
            'size'              => $survey->size,
            'type_code'         => $survey->type_code,
            'customer_id'       => $survey->customer_id,
            'customer_name'     => $survey->customer?->name,
            'condition'         => $survey->overall_condition,
        ]);
    }

    // -------------------------------------------------------------------------
    // Container Lookup (AJAX)
    // -------------------------------------------------------------------------
    public function lookup(string $containerNo)
    {
        $container = Container::with('customer')
            ->where('container_no', strtoupper($containerNo))
            ->first();

        if (!$container) {
            return response()->json(['found' => false]);
        }

        $container->load('equipmentType');

        return response()->json([
            'found'              => true,
            'id'                 => $container->id,
            'container_no'       => $container->container_no,
            'equipment_type_id'  => $container->equipment_type_id,
            'eqt_code'           => $container->equipmentType?->eqt_code,
            'eqt_description'    => $container->equipmentType?->description,
            'size'               => $container->size,
            'type_code'          => $container->type_code,
            'condition'          => $container->condition,
            'cargo_status'       => $container->cargo_status,
            'status'             => $container->status,
            'customer_id'        => $container->customer_id,
            'customer_name'      => $container->customer->name,
            'location'           => "{$container->location_row}{$container->location_bay}-T{$container->location_tier}",
            'gate_in_date'       => $container->gate_in_date?->toDateString(),
        ]);
    }
}
