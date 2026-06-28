<?php

namespace App\Http\Controllers;

use App\Facades\Documents;
use App\Models\Container;
use App\Models\ContainerGrade;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\GateMovementPhoto;
use App\Models\GuardCapture;
use App\Models\Inquiry;
use App\Models\StorageMasterHeader;
use App\Models\StorageZone;
use App\Models\YardLocation;
use App\Models\ReeferPlugSession;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\NotificationService;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class YardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:yard.view')->only(['index', 'gate', 'storage', 'lookup', 'containerLookup', 'inYardSearch', 'tariffLookup', 'slotsByZone', 'surveyLookup', 'gatePass', 'verifyGatePass']);
        $this->middleware('can:yard.gate-in')->only(['gateIn']);
        $this->middleware('can:yard.gate-out')->only(['gateOut']);
        $this->middleware('can:yard.movement-edit')->only(['editMovement', 'updateMovement', 'destroyMovementPhoto']);
        $this->middleware('can:yard.movement-delete')->only(['destroyMovement', 'deleteCheck']);
    }

    private function saveMovementPhotos(GateMovement $movement, array $photos): void
    {
        foreach ($photos as $photo) {
            Documents::uploadFor(
                $movement,
                $photo,
                "gate-movements/{$movement->movement_type}/{$movement->id}",
                ['document_type' => 'photo']
            );
        }
    }

    private function saveMovementOcrImages(GateMovement $movement, array $validated): void
    {
        $updates = [];

        if (!empty($validated['container_ocr_image'])) {
            $ext  = $validated['container_ocr_image']->getClientOriginalExtension() ?: 'jpg';
            $path = $validated['container_ocr_image']->storeAs(
                "gate-movements/ocr/{$movement->id}",
                "container.{$ext}",
                'public'
            );
            $updates['container_ocr_image_path'] = $path;
        }

        if (!empty($validated['plate_ocr_image'])) {
            $ext  = $validated['plate_ocr_image']->getClientOriginalExtension() ?: 'jpg';
            $path = $validated['plate_ocr_image']->storeAs(
                "gate-movements/ocr/{$movement->id}",
                "plate.{$ext}",
                'public'
            );
            $updates['plate_ocr_image_path'] = $path;
        }

        if (!empty($updates)) {
            $movement->update($updates);
        }
    }

    // -------------------------------------------------------------------------
    // Yard Overview (visual grid map)
    // -------------------------------------------------------------------------
    public function index()
    {
        $zones = StorageZone::active()
            ->withCount([
                'yardLocations as total_count',
                'yardLocations as occupied_count' => fn($q) => $q->where('status', 'occupied'),
                'yardLocations as empty_count'    => fn($q) => $q->where('status', 'empty'),
                'yardLocations as reserved_count' => fn($q) => $q->where('status', 'reserved'),
                'yardLocations as damaged_count'  => fn($q) => $q->whereIn('status', ['damaged', 'in_repair']),
            ])
            ->get();

        // All yard locations, grouped by zone then row
        $allLocations = YardLocation::with('container.customer')
            ->orderBy('zone')->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get()
            ->groupBy('zone');

        // Containers currently in yard
        $inYardContainers = Container::with(['customer', 'equipmentType', 'activeHire'])
            ->where('status', 'in_yard')
            ->orderBy('location_zone')->orderBy('location_row')->orderBy('location_bay')
            ->get();

        $summary = [
            'total'     => YardLocation::count(),
            'occupied'  => YardLocation::where('status', 'occupied')->count(),
            'empty'     => YardLocation::where('status', 'empty')->count(),
            'in_repair' => YardLocation::whereIn('status', ['damaged', 'in_repair'])->count(),
            'reserved'  => YardLocation::where('status', 'reserved')->count(),
        ];

        return view('yard.index', compact('zones', 'allLocations', 'inYardContainers', 'summary'));
    }

    // -------------------------------------------------------------------------
    // Gate Operations
    // -------------------------------------------------------------------------
    public function gate(Request $request)
    {
        $search = strtoupper(trim($request->get('search', '')));
        $recentMovements = GateMovement::with(['container', 'customer', 'createdBy'])
            ->when($search, fn($q) => $q->where('container_no', 'like', '%' . $search . '%'))
            ->latest()
            ->take($search ? 100 : 20)
            ->get();

        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $transporters   = Customer::whereHas('types', fn($q) => $q->where('name', 'Transporter'))
                            ->where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->get();
        $grades         = ContainerGrade::active()->orderBy('sort_order')->get();
        $zones          = StorageZone::active()->withCount([
            'yardLocations',
            'yardLocations as empty_count'    => fn($q) => $q->where('status', 'empty'),
            'yardLocations as occupied_count' => fn($q) => $q->where('status', 'occupied'),
        ])->get();

        // Pre-fill from Guard Post capture when coming via the queue
        $guardCapture = null;
        $prefill      = null;
        if ($request->filled('capture_id')) {
            $guardCapture = GuardCapture::with(['capturedBy', 'clearedBy'])->find($request->capture_id);
            if ($guardCapture && $guardCapture->isCleared() && !$guardCapture->linked_gate_movement_id) {
                $prefill = [
                    'capture_id'      => $guardCapture->id,
                    'container_no'    => $guardCapture->container_number,
                    'iso_code'        => $guardCapture->iso_code,
                    'vehicle_plate'   => $guardCapture->vehicle_number,
                    'driver_name'     => $guardCapture->driver_name,
                    'driver_ic'       => $guardCapture->nic_number,
                    'driver_phone'    => $guardCapture->driver_phone,
                    'reference_no'    => $guardCapture->reference_no,
                    'container_image' => $guardCapture->container_image_url,
                ];
            }
        }

        $jobTypes = YardJobType::active()->forGateIn()->orderBy('sort_order')->get();

        $emptySlots = YardLocation::where('status', 'empty')
            ->orderBy('zone')->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get();

        return view('yard.gate', compact('recentMovements', 'search', 'customers', 'transporters', 'equipmentTypes', 'grades', 'zones', 'prefill', 'guardCapture', 'jobTypes', 'emptySlots'));
    }

    public function gateIn(Request $request)
    {
        // ── Deep upload diagnostics ──────────────────────────────────────────
        \Log::debug('[GateIn-PRE] PHP upload_max_filesize=' . ini_get('upload_max_filesize')
                    . ' post_max_size=' . ini_get('post_max_size')
                    . ' file_uploads=' . ini_get('file_uploads'));
        \Log::debug('[GateIn-PRE] $_FILES keys: ' . json_encode(array_keys($_FILES)));
        \Log::debug('[GateIn-PRE] request->allFiles(): ' . json_encode(
            collect($request->allFiles())->map(fn($v) => is_array($v)
                ? array_map(fn($f) => ['name'=>$f->getClientOriginalName(),'size'=>$f->getSize(),'err'=>$f->getError()], $v)
                : ['name'=>$v->getClientOriginalName(),'size'=>$v->getSize(),'err'=>$v->getError()]
            )->toArray()
        ));

        $validator = \Illuminate\Support\Facades\Validator::make($request->all() + ['_files' => $request->allFiles()], [
            'job_type_id'       => ['required', 'exists:yard_job_types,id'],
            'container_no'      => ['required', 'string', 'max:12', 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'customer_id'       => ['required', 'exists:customers,id'],
            'condition'         => ['required', 'in:sound,damaged,require_repair'],
            'grade_id'          => ['nullable', 'exists:container_grades,id'],
            'cargo_status'      => ['required', 'in:empty,laden'],
            'reefer_service_type' => ['nullable', 'in:pti,long_term'],
            'location_zone'     => ['nullable', 'string', 'max:10', 'exists:storage_zones,code'],
            'location_row'      => ['nullable', 'string', 'max:5'],
            'location_bay'      => ['nullable', 'integer', 'min:1', 'max:99'],
            'location_tier'     => ['nullable', 'integer', 'min:1', 'max:10'],
            'seal_no'           => ['nullable', 'string', 'max:20'],
            'vehicle_plate'     => ['nullable', 'string', 'max:20'],
            'transporter_id'    => ['nullable', 'exists:customers,id'],
            'driver_name'       => ['nullable', 'string', 'max:255'],
            'driver_ic'         => ['nullable', 'string', 'max:30'],
            'driver_phone'      => ['nullable', 'string', 'max:20'],
            // Import shipment information
            'vessel_name'       => ['nullable', 'string', 'max:100'],
            'voyage_no'         => ['nullable', 'string', 'max:50'],
            'berthing_date'     => ['nullable', 'date'],
            'bl_number'         => ['nullable', 'string', 'max:50'],
            'do_expiry_date'    => ['nullable', 'date'],
            'fcl_expiry_date'   => ['nullable', 'date'],
            'consignee'         => ['nullable', 'string', 'max:150'],
            'remarks'           => ['nullable', 'string'],
            'return_reason'     => ['nullable', 'in:import_consignee,agent_return,shipper_return'],
            'gate_in_time'      => ['nullable', 'string', 'max:20'],
            'photos'            => ['nullable', 'array', 'max:5'],
            'photos.*'          => ['image', 'max:20480'],
            'container_ocr_image' => ['nullable', 'image', 'max:20480'],
            'plate_ocr_image'     => ['nullable', 'image', 'max:20480'],
            // Additional container details (master profile enrichment)
            'tare_weight_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'gross_weight_kg'   => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_payload_kg'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1970', 'max:' . (date('Y') + 1)],
            'manufacturer'      => ['nullable', 'string', 'max:100'],
            'owner_code'        => ['nullable', 'string', 'max:20'],
            'owner_name'        => ['nullable', 'string', 'max:100'],
            'csc_plate_no'      => ['nullable', 'string', 'max:50'],
            'csc_expiry_date'   => ['nullable', 'date'],
            // Ventilation
            'ventilation_type'  => ['nullable', 'in:none,passive,cross,mechanical,reefer,controlled_atm'],
            'vent_count'        => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        if ($validator->fails()) {
            \Log::debug('[GateIn-VALIDATION] failed: ' . json_encode($validator->errors()->toArray()));
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $jobType = YardJobType::findOrFail($validated['job_type_id']);

        // Return reason is required for Empty Return job type
        if ($jobType->job_type_code === 'EMPTY_RETURN' && empty($validated['return_reason'])) {
            return redirect()->back()
                ->withErrors(['return_reason' => 'Please select a return reason for Empty Return.'])
                ->withInput();
        }

        // ── Duplicate Gate-In guard ──────────────────────────────────────────
        $existingContainer = Container::where('container_no', $validated['container_no'])->first();

        // Check 1: container is currently in the yard
        if ($existingContainer && $existingContainer->status === 'in_yard') {
            $since = $existingContainer->gate_in_date
                ? ' (since ' . $existingContainer->gate_in_date->format('d M Y') . ')'
                : '';
            return redirect()->back()
                ->withErrors(['container_no' =>
                    "{$validated['container_no']} is already in the yard{$since}. "
                    . "Complete the Gate-Out before recording a new Gate-In."
                ])
                ->withInput();
        }

        // Check 2: backdated Gate-In overlaps an existing stay (open or closed)
        if ($existingContainer) {
            $proposedDate = (auth()->user()->can('yard.backdate') && !empty($validated['gate_in_time']))
                ? \Carbon\Carbon::parse($validated['gate_in_time'])->toDateString()
                : today()->toDateString();

            $conflict = YardStorage::where('container_id', $existingContainer->id)
                ->where('gate_in_date', '<=', $proposedDate)
                ->where(function ($q) use ($proposedDate) {
                    $q->whereNull('gate_out_date')
                      ->orWhere('gate_out_date', '>', $proposedDate); // '>' not '>=' — same-day re-entry is valid
                })
                ->first();

            if ($conflict) {
                $from = $conflict->gate_in_date->format('d M Y');
                $to   = $conflict->gate_out_date
                    ? $conflict->gate_out_date->format('d M Y')
                    : 'present';
                return redirect()->back()
                    ->withErrors(['gate_in_time' =>
                        "The Gate-In date conflicts with an existing stay for this container "
                        . "({$from} → {$to}). Adjust the date or gate out the existing record first."
                    ])
                    ->withInput();
            }
        }
        // ── End duplicate guard ──────────────────────────────────────────────

        $eqt = EquipmentType::findOrFail($validated['equipment_type_id']);

        // A reefer plug session is created below for laden reefer containers. The
        // operator must choose its billing service type up front — never defaulted.
        $willPlugReefer = $validated['cargo_status'] === 'laden' && $eqt->isReefer();
        if ($willPlugReefer && empty($validated['reefer_service_type'])) {
            return redirect()->back()
                ->withErrors(['reefer_service_type' =>
                    'Please choose the reefer service type (Short-Term PTI or Long-Term Electricity) for this reefer container.'])
                ->withInput();
        }

        // Resolve actual gate-in datetime (admin can override; everyone else uses now())
        $gateInTime = (auth()->user()->can('yard.backdate') && !empty($validated['gate_in_time']))
            ? \Carbon\Carbon::parse($validated['gate_in_time'])
            : now();
        $gateInDate = $gateInTime->toDateString(); // date portion used for storage billing

        // Create or update container record — preserve existing master profile fields on update
        $existing = Container::where('container_no', $validated['container_no'])->first();
        $containerData = [
            'equipment_type_id' => $eqt->id,
            'size'              => $eqt->size,
            'type_code'         => $eqt->type_code,
            'customer_id'       => $validated['customer_id'],
            'condition'         => $validated['condition'],
            'grade_id'          => $validated['grade_id'] ?? null,
            'cargo_status'      => $validated['cargo_status'],
            'status'            => 'in_yard',
            'location_zone'     => $validated['location_zone'],
            'location_row'      => $validated['location_row'],
            'location_bay'      => $validated['location_bay'],
            'location_tier'     => $validated['location_tier'],
            'seal_no'           => $validated['seal_no'],
            'gate_in_date'      => $gateInDate,
            'gate_out_date'     => null,
        ];
        // Only set category on first creation (new container gets consignee default)
        if (!$existing) {
            $containerData['category'] = 'consignee';
        }
        // Master profile enrichment — only write fields that were actually submitted
        // (never overwrite existing master data with a blank value from the form)
        foreach (['tare_weight_kg', 'gross_weight_kg', 'max_payload_kg',
                  'manufacture_year', 'manufacturer', 'owner_code', 'owner_name',
                  'csc_plate_no', 'csc_expiry_date',
                  'ventilation_type', 'vent_count'] as $profileField) {
            if (isset($validated[$profileField]) && $validated[$profileField] !== null && $validated[$profileField] !== '') {
                $containerData[$profileField] = $validated[$profileField];
            }
        }
        $container = Container::updateOrCreate(
            ['container_no' => $validated['container_no']],
            $containerData
        );

        // Record gate movement
        $movement = DB::transaction(function () use ($container, $jobType, $eqt, $validated, $gateInTime) {
            return GateMovement::create([
                'container_id'     => $container->id,
                'container_no'     => $container->container_no,
                'job_type_id'      => $jobType->id,
                'job_type_code'    => $jobType->job_type_code,
                'customer_id'      => $validated['customer_id'],
                'transporter_id'   => $validated['transporter_id'] ?? null,
                'movement_type'    => 'in',
                'eir_no'           => app(NumberSequenceService::class)->generate('gate_in'),
                'size'             => $eqt->size,
                'container_type'   => $eqt->type_code,
                'ventilation_type' => $validated['ventilation_type'] ?? $container->effective_ventilation_type,
                'vent_count'       => $validated['vent_count'] ?? $container->effective_vent_count,
                'location_zone'   => $validated['location_zone'],
                'location_row'    => $validated['location_row'],
                'location_bay'    => $validated['location_bay'],
                'location_tier'   => $validated['location_tier'],
                'condition'       => $validated['condition'],
                'grade_id'        => $validated['grade_id'] ?? null,
                'cargo_status'    => $validated['cargo_status'],
                'seal_no'         => $validated['seal_no'],
                'vehicle_plate'   => $validated['vehicle_plate'],
                'driver_name'     => $validated['driver_name'] ?? null,
                'driver_ic'       => $validated['driver_ic'] ?? null,
                'driver_phone'    => $validated['driver_phone'] ?? null,
                'gate_in_time'    => $gateInTime,
                'movement_status' => 'done',
                'remarks'         => $validated['remarks'],
                'created_by'      => auth()->id(),
                // Import shipment information
                'vessel_name'     => $validated['vessel_name'] ?? null,
                'voyage_no'       => $validated['voyage_no'] ?? null,
                'berthing_date'   => $validated['berthing_date'] ?? null,
                'bl_number'       => $validated['bl_number'] ?? null,
                'do_expiry_date'  => $validated['do_expiry_date'] ?? null,
                'fcl_expiry_date' => $validated['fcl_expiry_date'] ?? null,
                'consignee'       => $validated['consignee'] ?? null,
            ]);
        });

        // Save OCR-captured images
        $this->saveMovementOcrImages($movement, $validated);

        // Save gate-in photos via DocumentManager
        $photoError = null;
        if (!empty($validated['photos'])) {
            try {
                $this->saveMovementPhotos($movement, $validated['photos']);
            } catch (\Throwable $e) {
                $photoError = 'Movement saved, but photo upload failed: ' . $e->getMessage();
            }
        }

        // Update yard slot only when a slot was selected
        if (!empty($validated['location_zone'])) {
            YardLocation::where([
                'zone' => $validated['location_zone'],
                'row'  => $validated['location_row'],
                'bay'  => $validated['location_bay'],
                'tier' => $validated['location_tier'],
            ])->update([
                'container_id'    => $container->id,
                'status'          => 'occupied',
                'last_updated_at' => now(),
            ]);
        }

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

        // Create storage record — use actual gate-in date so billing calculations are correct
        YardStorage::create([
            'container_id' => $container->id,
            'customer_id'  => $validated['customer_id'],
            'gate_in_date' => $gateInDate,
            'free_days'    => $freeDays,
            'daily_rate'   => $dailyRate,
        ]);

        // Auto-create a Yard Job and link this movement to it
        $yardJobNo = null;
        try {
            ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);

            $yardJob = YardJob::create([
                'job_no'          => $jobNo,
                'job_seq'         => $jobSeq,
                'job_type_id'     => $jobType->id,
                'job_type_code'   => $jobType->job_type_code,
                'type_short_code' => $jobType->type_short_code,
                'customer_id'     => $validated['customer_id'],
                'status'          => 'open',
                'started_at'      => $gateInTime,
                'return_reason'   => $jobType->job_type_code === 'EMPTY_RETURN'
                                        ? ($validated['return_reason'] ?? null)
                                        : null,
                'created_by'      => auth()->id(),
            ]);

            $movement->update(['yard_job_id' => $yardJob->id]);
            $yardJobNo = $yardJob->job_no;
        } catch (\Throwable $e) {
            \Log::error('[GateIn] Yard job creation failed: ' . $e->getMessage());
        }

        // Auto-create a pending reefer plug session for laden reefer containers
        // Non-blocking: any failure here must not abort a successful gate-in
        try {
            if ($validated['cargo_status'] === 'laden' && $eqt->isReefer()) {
                \App\Models\ReeferPlugSession::create([
                    'container_id'    => $container->id,
                    'gate_movement_id'=> $movement->id,
                    'customer_id'     => $validated['customer_id'],
                    'service_type'    => $validated['reefer_service_type'],
                    'status'          => 'pending',
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[GateIn] Reefer plug session creation failed: ' . $e->getMessage());
        }

        // Link guard capture if this gate-in originated from the Guard Post queue
        if ($request->filled('guard_capture_id')) {
            GuardCapture::where('id', $request->guard_capture_id)
                ->where('status', 'cleared')
                ->whereNull('linked_gate_movement_id')
                ->update(['linked_gate_movement_id' => $movement->id]);
        }

        NotificationService::notifyAll(
            'Gate IN — ' . $container->container_no,
            ($container->customer->name ?? 'Unknown') . ' · ' . ($container->condition ?? ''),
            'info',
            route('yard.movements.edit', $movement)
        );

        $gpNote = "Gate IN recorded for {$container->container_no}.";
        if ($yardJobNo) {
            $gpNote .= "  Job No: <strong>{$yardJobNo}</strong>";
            if (isset($yardJob)) {
                $gpNote .= '  &mdash;  <a href="' . route('yard.jobs.show', $yardJob) . '" style="color:#93c5fd;">View Job &rarr;</a>';
            }
        }

        $redirect = redirect()->route('yard.movements.gate-pass', $movement)
            ->with('gp_note', $gpNote);

        if ($photoError) {
            $redirect->with('warning', $photoError);
        }

        return $redirect;
    }

    public function gateOut(Request $request)
    {
        $validated = $request->validate([
            'container_no'  => [
                'required', 'string',
                'exists:containers,container_no',
                function ($attr, $val, $fail) {
                    $c = Container::where('container_no', $val)->first();
                    if ($c && $c->status !== 'in_yard') {
                        $fail("Container {$val} is not currently in the yard (status: {$c->status}).");
                    }
                },
            ],
            'vehicle_plate'  => ['required', 'string', 'max:20'],
            'transporter_id' => ['nullable', 'exists:customers,id'],
            'driver_name'    => ['required', 'string', 'max:255'],
            'driver_ic'      => ['required', 'string', 'max:30'],
            'driver_phone'   => ['nullable', 'string', 'max:20'],
            'release_order'  => ['nullable', 'string', 'max:50'],
            'seal_no'        => ['nullable', 'string', 'max:20'],
            'grade_id'       => ['nullable', 'exists:container_grades,id'],
            // Export information
            'loading_vessel' => ['nullable', 'string', 'max:100'],
            'loading_voyage' => ['nullable', 'string', 'max:50'],
            'sailing_date'   => ['nullable', 'date'],
            'shipper'        => ['nullable', 'string', 'max:150'],
            'remarks'        => ['nullable', 'string'],
            'gate_out_time'  => ['nullable', 'string', 'max:20'],
            'photos'              => ['nullable', 'array', 'max:5'],
            'photos.*'            => ['image', 'max:5120'],
            'container_ocr_image' => ['nullable', 'image', 'max:20480'],
            'plate_ocr_image'     => ['nullable', 'image', 'max:20480'],
        ]);

        $container = Container::where('container_no', $validated['container_no'])->firstOrFail();

        // Block gate-out while container is on active hire — the hire must be completed first
        if ($container->activeHire()->exists()) {
            return redirect()->back()
                ->withErrors(['container_no' =>
                    "Container {$container->container_no} is currently on hire. "
                    . 'Complete or cancel the hire before gating it out.'
                ])
                ->withInput();
        }

        // Resolve actual gate-out datetime — admin can override, others use now()
        $gateOutTime = (auth()->user()->can('yard.backdate') && !empty($validated['gate_out_time']))
            ? \Carbon\Carbon::parse($validated['gate_out_time'])
            : now();
        $gateOutDate = $gateOutTime->toDateString();

        // Record gate movement
        $movement = DB::transaction(function () use ($container, $validated, $gateOutTime) {
            return GateMovement::create([
                'container_id'     => $container->id,
                'container_no'     => $container->container_no,
                'customer_id'      => $container->customer_id,
                'transporter_id'   => $validated['transporter_id'] ?? null,
                'movement_type'    => 'out',
                'eir_no'           => app(NumberSequenceService::class)->generate('gate_out'),
                'size'             => $container->size,
                'container_type'   => $container->type_code,
                'ventilation_type' => $container->effective_ventilation_type,
                'vent_count'       => $container->effective_vent_count,
                'location_zone'   => $container->location_zone,
                'location_row'    => $container->location_row,
                'location_bay'    => $container->location_bay,
                'location_tier'   => $container->location_tier,
                'condition'       => $container->condition,
                'grade_id'        => $validated['grade_id'] ?? $container->grade_id,
                'cargo_status'    => $container->cargo_status,
                'vehicle_plate'   => $validated['vehicle_plate'],
                'driver_name'     => $validated['driver_name'],
                'driver_ic'       => $validated['driver_ic'],
                'driver_phone'    => $validated['driver_phone'] ?? null,
                'release_order'   => $validated['release_order'],
                'seal_no'         => $validated['seal_no'] ?? null,
                'gate_out_time'   => $gateOutTime,
                'movement_status' => 'done',
                'remarks'         => $validated['remarks'],
                'created_by'      => auth()->id(),
                // Export information
                'loading_vessel'  => $validated['loading_vessel'] ?? null,
                'loading_voyage'  => $validated['loading_voyage'] ?? null,
                'sailing_date'    => $validated['sailing_date'] ?? null,
                'shipper'         => $validated['shipper'] ?? null,
            ]);
        });

        // Save OCR-captured images
        $this->saveMovementOcrImages($movement, $validated);

        // Link guard capture if this gate-out originated from the Guard Post queue
        if ($request->filled('guard_capture_id')) {
            GuardCapture::where('id', $request->guard_capture_id)
                ->where('status', 'cleared')
                ->whereNull('linked_gate_movement_id')
                ->update(['linked_gate_movement_id' => $movement->id]);
        }

        // Save gate-out photos via DocumentManager
        $photoError = null;
        if (!empty($validated['photos'])) {
            try {
                $this->saveMovementPhotos($movement, $validated['photos']);
            } catch (\Throwable $e) {
                $photoError = 'Movement saved, but photo upload failed: ' . $e->getMessage();
            }
        }

        // Finalise open storage record — use actual gate-out date
        $storage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->latest('gate_in_date')
            ->first();

        if ($storage) {
            $gateOutCarbon = \Carbon\Carbon::parse($gateOutDate);
            // For resumed storage after off-hire, billing_gate_in_date is the original
            // physical gate-in date; free-days already consumed before hire must be
            // subtracted from the allowance so the customer isn't double-credited.
            $billingGateIn       = $storage->billing_gate_in_date;
            $daysConsumedBefore  = max(0, (int) $billingGateIn->diffInDays($storage->gate_in_date));
            $freeDaysRemaining   = max(0, $storage->free_days - $daysConsumedBefore);
            $totalDays           = max(1, (int) $storage->gate_in_date->diffInDays($gateOutCarbon));
            $chargeableDays      = max(0, $totalDays - $freeDaysRemaining);
            $subtotal       = $chargeableDays * $storage->daily_rate;

            $storage->update([
                'gate_out_date'   => $gateOutDate,
                'total_days'      => $totalDays,
                'chargeable_days' => $chargeableDays,
                'subtotal'        => $subtotal,
                'total_charge'    => $subtotal,
            ]);
        }

        // Auto-close any active reefer plug sessions for this container (non-blocking)
        try {
            \App\Models\ReeferPlugSession::where('container_id', $container->id)
                ->whereIn('status', ['pending', 'active'])
                ->each(function ($session) use ($movement, $gateOutTime) {
                    $updates = [
                        'gate_out_movement_id' => $movement->id,
                        'updated_by'           => auth()->id(),
                    ];
                    if ($session->isActive()) {
                        $updates['plug_out_at'] = $gateOutTime;
                        $updates['status']      = 'completed';
                    } else {
                        // Still pending (plug-in never recorded) — mark completed without billing
                        $updates['status'] = 'completed';
                    }
                    $session->update($updates);
                });
        } catch (\Throwable $e) {
            \Log::warning('[GateOut] Reefer plug session auto-close failed: ' . $e->getMessage());
        }

        // Release yard slot
        YardLocation::where('container_id', $container->id)->update([
            'container_id'    => null,
            'status'          => 'empty',
            'last_updated_at' => now(),
        ]);

        // Update container status
        $container->update([
            'status'        => 'released',
            'location_zone' => null,
            'gate_out_date' => $gateOutDate,
            'location_row'  => null,
            'location_bay'  => null,
            'location_tier' => null,
        ]);

        NotificationService::notifyAll(
            'Gate OUT — ' . $container->container_no,
            ($container->customer->name ?? 'Unknown') . ' · Container released',
            'info',
            route('yard.movements.edit', $movement)
        );

        $redirect = redirect()->route('yard.movements.gate-pass', $movement)
            ->with('gp_note', "Gate OUT recorded for {$container->container_no}.");

        if ($photoError) {
            $redirect->with('warning', $photoError);
        }

        return $redirect;
    }

    // -------------------------------------------------------------------------
    // Gate Movement Delete — pre-check + delete
    // -------------------------------------------------------------------------

    /** AJAX pre-check: returns blocks (hard stops) and warnings before deletion. */
    public function deleteCheck(GateMovement $movement): \Illuminate\Http\JsonResponse
    {
        [$blocks, $warnings] = $this->buildDeleteBlocks($movement);

        return response()->json([
            'safe'          => empty($blocks),
            'container_no'  => $movement->container_no,
            'movement_type' => $movement->movement_type,
            'movement_time' => ($movement->gate_in_time ?? $movement->gate_out_time)?->format('d M Y, H:i'),
            'blocks'        => $blocks,
            'warnings'      => $warnings,
        ]);
    }

    /** Validate dependencies then delete (server-side guard in addition to AJAX pre-check). */
    public function destroyMovement(GateMovement $movement)
    {
        [$blocks] = $this->buildDeleteBlocks($movement);

        if (!empty($blocks)) {
            return back()->with('error',
                'Cannot delete this movement: ' . collect($blocks)->pluck('message')->implode(' · '));
        }

        $ref = $movement->container_no . ' (' . strtoupper($movement->movement_type) . ')';
        $tab = $movement->movement_type === 'in' ? 'in' : 'out';

        // Cascade-delete auto-created pending reefer stub sessions that had no
        // plug details recorded (these downgraded from block → warning above).
        ReeferPlugSession::where('gate_movement_id', $movement->id)
            ->where('status', 'pending')
            ->whereNull('plug_in_at')
            ->whereDoesntHave('tempLogs')
            ->delete();

        // Cascade-delete raw storage billing record when no invoice has been issued
        // (downgraded from block → warning above; if issued invoice exists it would
        // have remained a hard block and we would not reach this point).
        if ($movement->movement_type === 'in' && $movement->container_id && $movement->gate_in_time) {
            YardStorage::where('container_id', $movement->container_id)
                ->whereDate('gate_in_date', $movement->gate_in_time->toDateString())
                ->delete();
        }

        $movement->delete();

        $fallback  = route('yard.gate') . '?tab=' . $tab;
        $redirectTo = request('_redirect', $fallback);

        return redirect()
            ->to($redirectTo)
            ->with('success', "Gate movement for {$ref} deleted. Verify the container status is correct.");
    }

    /** Build the blocks/warnings arrays used by both deleteCheck and destroyMovement. */
    private function buildDeleteBlocks(GateMovement $movement): array
    {
        $blocks   = [];
        $warnings = [];
        $isIn     = $movement->movement_type === 'in';

        // ── Hard blocks ───────────────────────────────────────────────────────

        // Gate-In: cannot delete while a paired Gate-Out exists
        if ($isIn && $movement->container_id) {
            $pairedOut = GateMovement::where('movement_type', 'out')
                ->where('container_id', $movement->container_id)
                ->where('gate_out_time', '>', $movement->gate_in_time)
                ->orderBy('gate_out_time')
                ->first();
            if ($pairedOut) {
                $blocks[] = [
                    'icon'    => 'bi-arrow-up-circle-fill',
                    'message' => 'A paired Gate-Out exists for this container on '
                        . $pairedOut->gate_out_time->format('d M Y, H:i')
                        . '. Delete the Gate-Out movement first.',
                ];
            }
        }

        // Gate-In: cannot delete while an active hire is in effect — the hire split
        // references the original storage record via original_yard_storage_id; deleting
        // the gate-in would cascade-delete that storage record and break the hire trail.
        if ($isIn && $movement->container_id) {
            $activeHireExists = \App\Models\Container::where('id', $movement->container_id)
                ->whereHas('activeHire')
                ->exists();
            if ($activeHireExists) {
                $blocks[] = [
                    'icon'    => 'bi-arrow-left-right',
                    'message' => 'This container has an active hire. Complete or cancel the hire before deleting the gate-in movement.',
                ];
            }
        }

        // Reefer plug sessions (gate-in: gate_movement_id; gate-out: gate_out_movement_id)
        $reeferField    = $isIn ? 'gate_movement_id' : 'gate_out_movement_id';
        $reeferSessions = ReeferPlugSession::with('tempLogs')->where($reeferField, $movement->id)->get();
        if ($reeferSessions->isNotEmpty()) {
            // Separate auto-created stubs (pending, no plug_in_at, no temp logs) from
            // sessions where reefer work has actually been recorded.
            $stubSessions    = $reeferSessions->filter(
                fn($s) => $s->status === 'pending' && is_null($s->plug_in_at) && $s->tempLogs->isEmpty()
            );
            $workedSessions  = $reeferSessions->diff($stubSessions);

            if ($workedSessions->isNotEmpty()) {
                $blocks[] = [
                    'icon'    => 'bi-thermometer-half',
                    'message' => $workedSessions->count() . ' reefer plug session(s) with recorded plug-in or temperature data are linked to this movement and must be removed first.',
                ];
            }

            if ($stubSessions->isNotEmpty()) {
                $warnings[] = [
                    'icon'    => 'bi-thermometer-half',
                    'message' => $stubSessions->count() . ' auto-created reefer plug session(s) with no plug details recorded will be deleted along with this movement.',
                ];
            }
        }

        // Pending / approved approval request
        $approval = $movement->approvalRequest;
        if ($approval && in_array($approval->status, ['pending', 'approved'])) {
            $blocks[] = [
                'icon'    => 'bi-shield-check',
                'message' => 'This movement has a ' . ucfirst($approval->status) . ' approval request (#' . $approval->id . ') that must be cancelled first.',
            ];
        }

        // Survey / Inquiry linked directly to this gate-in movement
        if ($isIn && $movement->survey_id) {
            $survey = $movement->survey;
            $blocks[] = [
                'icon'    => 'bi-clipboard-check',
                'message' => 'Survey #' . ($survey?->inquiry_no ?? $movement->survey_id)
                    . ' is linked to this movement.',
            ];

            // Estimates under that survey
            $estCount = \App\Models\Estimate::where('inquiry_id', $movement->survey_id)->count();
            if ($estCount > 0) {
                $blocks[] = [
                    'icon'    => 'bi-file-earmark-text',
                    'message' => $estCount . ' estimate(s) exist under the linked survey.',
                ];
            }

            // Work orders under those estimates
            $woCount = \App\Models\WorkOrder::whereIn(
                'estimate_id',
                \App\Models\Estimate::where('inquiry_id', $movement->survey_id)->pluck('id')
            )->count();
            if ($woCount > 0) {
                $blocks[] = [
                    'icon'    => 'bi-wrench-adjustable',
                    'message' => $woCount . ' work order(s) exist under the linked survey estimates.',
                ];
            }
        }

        // ── Warnings (non-blocking but important) ─────────────────────────────

        // Documents attached (photos, PDFs via HasDocuments trait)
        $docCount = $movement->documents()->count();
        if ($docCount > 0) {
            $warnings[] = [
                'icon'    => 'bi-paperclip',
                'message' => $docCount . ' attached document(s) / photo(s) will also be permanently deleted.',
            ];
        }

        // Guard post capture linked
        $guardCount = GuardCapture::where('linked_gate_movement_id', $movement->id)->count();
        if ($guardCount > 0) {
            $warnings[] = [
                'icon'    => 'bi-camera',
                'message' => $guardCount . ' guard post capture(s) are linked — the link will be cleared (captures kept).',
            ];
        }

        // Invoices — block if a draft, issued, or paid invoice exists for this cycle.
        // Only cancelled invoices are non-blocking.
        if ($movement->container_id) {
            $cycleDate = $isIn
                ? $movement->gate_in_time?->toDateString()
                : $movement->gate_out_time?->toDateString();
            $dateField = $isIn ? 'gate_in_date' : 'gate_out_date';

            $invoiceTypes = [];

            // Storage & Handling invoice lines — block unless invoice is cancelled
            if ($cycleDate && \App\Models\StorageHandlingInvoiceLine::where('container_id', $movement->container_id)
                    ->whereDate($dateField, $cycleDate)
                    ->whereHas('invoice', fn($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                    ->exists()) {
                $invoiceTypes[] = 'Storage & Handling';
            }

            // Storage invoice details (gate_in_date only — gate-out uses paired gate-in date).
            // Block unless parent invoice is cancelled.
            if ($isIn && $cycleDate) {
                if (\App\Models\StorageInvoiceDetail::where('container_id', $movement->container_id)
                        ->whereDate('gate_in_date', $cycleDate)
                        ->whereHas('invoice', fn($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                        ->exists()) {
                    $invoiceTypes[] = 'Storage';
                }
            } elseif (!$isIn) {
                $pairedIn = GateMovement::where('container_id', $movement->container_id)
                    ->where('movement_type', 'in')
                    ->where('gate_in_time', '<=', $movement->gate_out_time)
                    ->latest('gate_in_time')
                    ->first(['gate_in_time']);
                if ($pairedIn?->gate_in_time &&
                    \App\Models\StorageInvoiceDetail::where('container_id', $movement->container_id)
                        ->whereDate('gate_in_date', $pairedIn->gate_in_time->toDateString())
                        ->whereHas('invoice', fn($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                        ->exists()) {
                    $invoiceTypes[] = 'Storage';
                }
            }

            // Reefer electricity invoice lines — block unless invoice is cancelled
            $cycleStart = $isIn ? $movement->gate_in_time : null;
            $cycleEnd   = !$isIn ? $movement->gate_out_time : now();
            if ($cycleStart && \App\Models\ReeferElectricityInvoiceLine::where('container_id', $movement->container_id)
                    ->where('plug_in_at', '>=', $cycleStart)
                    ->when($cycleEnd, fn($q) => $q->where('plug_in_at', '<=', $cycleEnd))
                    ->whereHas('invoice', fn($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                    ->exists()) {
                $invoiceTypes[] = 'Reefer Electricity';
            }

            // Repair invoices — block unless cancelled
            if ($movement->survey_id &&
                \App\Models\RepairInvoice::where('container_id', $movement->container_id)
                    ->whereIn('estimate_id', \App\Models\Estimate::where('inquiry_id', $movement->survey_id)->pluck('id'))
                    ->whereIn('status', ['draft', 'issued', 'paid'])
                    ->exists()) {
                $invoiceTypes[] = 'Repair';
            }

            if (!empty($invoiceTypes)) {
                $blocks[] = [
                    'icon'    => 'bi-receipt-cutoff',
                    'message' => implode(', ', $invoiceTypes) . ' invoice(s) exist for this container cycle (draft, issued, or paid). Cancel the invoice(s) before removing the movement.',
                ];
            }
        }

        // Raw storage billing record (yard_storage) — block if any non-cancelled invoice
        // references this cycle. If no such invoice exists, warn and auto-delete.
        if ($isIn && $movement->container_id && $movement->gate_in_time) {
            $storage = YardStorage::where('container_id', $movement->container_id)
                ->whereDate('gate_in_date', $movement->gate_in_time->toDateString())
                ->first();
            if ($storage) {
                $hasIssuedInvoice = \App\Models\StorageInvoiceDetail::where('container_id', $movement->container_id)
                    ->whereDate('gate_in_date', $movement->gate_in_time->toDateString())
                    ->whereHas('invoice', fn($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                    ->exists();
                if ($hasIssuedInvoice) {
                    $blocks[] = [
                        'icon'    => 'bi-receipt',
                        'message' => 'A storage invoice (gate-in: ' . $storage->gate_in_date->format('d M Y')
                            . ') exists for this movement. Cancel the invoice before deleting.',
                    ];
                } else {
                    $warnings[] = [
                        'icon'    => 'bi-receipt',
                        'message' => 'A storage billing record (gate-in: ' . $storage->gate_in_date->format('d M Y')
                            . ') exists with no active invoice — it will be removed along with this movement.',
                    ];
                }
            }
        }

        return [$blocks, $warnings];
    }

    // Gate Movement Edit
    // -------------------------------------------------------------------------
    public function editMovement(GateMovement $movement)
    {
        $movement->load(['container', 'customer', 'transporter', 'createdBy', 'approvalRequest.actions.actionedBy', 'approvalRequest.initiatedBy', 'approvalRequest.cancelledBy']);
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $transporters   = Customer::whereHas('types', fn($q) => $q->where('name', 'Transporter'))
                            ->where('status', 'active')->orderBy('name')->get();
        $equipmentTypes = EquipmentType::active()->get();
        $zones = StorageZone::active()->withCount([
            'yardLocations',
            'yardLocations as empty_count'    => fn($q) => $q->where('status', 'empty'),
            'yardLocations as occupied_count' => fn($q) => $q->where('status', 'occupied'),
        ])->get();

        return view('yard.movement-edit', compact('movement', 'customers', 'transporters', 'equipmentTypes', 'zones'));
    }

    public function updateMovement(Request $request, GateMovement $movement)
    {
        $isAdmin = auth()->user()->can('yard.backdate');

        $rules = [
            'vehicle_plate'    => ['nullable', 'string', 'max:20'],
            'remarks'          => ['nullable', 'string'],
            'condition'        => ['nullable', 'in:sound,damaged,require_repair'],
            'cargo_status'     => ['nullable', 'in:empty,laden'],
            'seal_no'          => ['nullable', 'string', 'max:20'],
            'ventilation_type' => ['nullable', 'in:none,passive,cross,mechanical,reefer,controlled_atm'],
            'vent_count'       => ['nullable', 'integer', 'min:0', 'max:99'],
        ];

        if ($movement->movement_type === 'in') {
            $rules['equipment_type_id']  = ['nullable', 'exists:equipment_types,id'];
            $rules['customer_id']        = ['nullable', 'exists:customers,id'];
            $rules['transporter_id']     = ['nullable', 'exists:customers,id'];
            $rules['driver_name']        = ['nullable', 'string', 'max:255'];
            $rules['driver_ic']          = ['nullable', 'string', 'max:30'];
            $rules['driver_phone']       = ['nullable', 'string', 'max:20'];
            $rules['location_zone']      = ['nullable', 'string', 'max:10', 'exists:storage_zones,code'];
            $rules['location_row']       = ['nullable', 'string', 'max:5'];
            $rules['location_bay']       = ['nullable', 'integer', 'min:1', 'max:99'];
            $rules['location_tier']      = ['nullable', 'integer', 'min:1', 'max:10'];
            // Import shipment fields
            $rules['vessel_name']        = ['nullable', 'string', 'max:100'];
            $rules['voyage_no']          = ['nullable', 'string', 'max:50'];
            $rules['berthing_date']      = ['nullable', 'date'];
            $rules['bl_number']          = ['nullable', 'string', 'max:50'];
            $rules['do_expiry_date']     = ['nullable', 'date'];
            $rules['fcl_expiry_date']    = ['nullable', 'date'];
            $rules['consignee']          = ['nullable', 'string', 'max:150'];
            if ($isAdmin) {
                $rules['gate_in_time']  = ['nullable', 'string', 'max:20'];
            }
        } else {
            $rules['transporter_id']  = ['nullable', 'exists:customers,id'];
            $rules['driver_name']     = ['nullable', 'string', 'max:255'];
            $rules['driver_ic']       = ['nullable', 'string', 'max:30'];
            $rules['driver_phone']    = ['nullable', 'string', 'max:20'];
            $rules['release_order']   = ['nullable', 'string', 'max:50'];
            // Export information fields
            $rules['loading_vessel']  = ['nullable', 'string', 'max:100'];
            $rules['loading_voyage']  = ['nullable', 'string', 'max:50'];
            $rules['sailing_date']    = ['nullable', 'date'];
            $rules['shipper']         = ['nullable', 'string', 'max:150'];
            if ($isAdmin) {
                $rules['gate_out_time'] = ['nullable', 'string', 'max:20'];
            }
        }

        $validated = $request->validate($rules);

        $updateData = array_filter([
            'vehicle_plate'    => $validated['vehicle_plate'] ?? null,
            'remarks'          => $validated['remarks'] ?? null,
            'condition'        => $validated['condition'] ?? null,
            'cargo_status'     => $validated['cargo_status'] ?? null,
            'seal_no'          => $validated['seal_no'] ?? null,
            'ventilation_type' => $validated['ventilation_type'] ?? null,
            'vent_count'       => isset($validated['vent_count']) ? (int) $validated['vent_count'] : null,
        ], fn ($v) => $v !== null);

        if ($movement->movement_type === 'in') {
            if (!empty($validated['equipment_type_id'])) {
                $eqt = EquipmentType::find($validated['equipment_type_id']);
                $updateData['size']            = $eqt->size;
                $updateData['container_type']  = $eqt->type_code;
            }
            foreach (['customer_id'] as $field) {
                if (!empty($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }
            // transporter and driver fields (allow clearing via empty string → null)
            foreach (['transporter_id', 'driver_name', 'driver_ic', 'driver_phone'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field] ?: null;
                }
            }

            // Import shipment fields
            foreach (['vessel_name', 'voyage_no', 'berthing_date', 'bl_number', 'do_expiry_date', 'fcl_expiry_date', 'consignee'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field] ?: null;
                }
            }

            // Handle location change — release old slot, occupy new slot
            $newZone = $validated['location_zone'] ?? null;
            $newRow  = $validated['location_row']  ?? null;
            $newBay  = $validated['location_bay']  ?? null;
            $newTier = $validated['location_tier'] ?? null;

            if ($newZone && $newRow && $newBay && $newTier) {
                $oldZone = $movement->location_zone;
                $oldRow  = $movement->location_row;
                $oldBay  = $movement->location_bay;
                $oldTier = $movement->location_tier;

                $locationChanged = ($oldZone !== $newZone || $oldRow !== $newRow
                    || (int)$oldBay !== (int)$newBay || (int)$oldTier !== (int)$newTier);

                if ($locationChanged) {
                    // Release the old slot
                    if ($oldRow) {
                        YardLocation::where([
                            'zone' => $oldZone,
                            'row'  => $oldRow,
                            'bay'  => $oldBay,
                            'tier' => $oldTier,
                        ])->update([
                            'container_id'    => null,
                            'status'          => 'empty',
                            'last_updated_at' => now(),
                        ]);
                    }
                    // Occupy the new slot
                    YardLocation::where([
                        'zone' => $newZone,
                        'row'  => $newRow,
                        'bay'  => $newBay,
                        'tier' => $newTier,
                    ])->update([
                        'container_id'    => $movement->container_id,
                        'status'          => 'occupied',
                        'last_updated_at' => now(),
                    ]);
                    // Sync Container location
                    Container::where('id', $movement->container_id)->update([
                        'location_zone' => $newZone,
                        'location_row'  => $newRow,
                        'location_bay'  => $newBay,
                        'location_tier' => $newTier,
                    ]);
                }

                $updateData['location_zone'] = $newZone;
                $updateData['location_row']  = $newRow;
                $updateData['location_bay']  = $newBay;
                $updateData['location_tier'] = $newTier;
            }

            if ($isAdmin && !empty($validated['gate_in_time'])) {
                $newGateInTime = \Carbon\Carbon::parse($validated['gate_in_time']);
                $updateData['gate_in_time'] = $newGateInTime;

                // Sync the date-only fields on Container and YardStorage so billing stays accurate
                $newGateInDate = $newGateInTime->toDateString();
                Container::where('id', $movement->container_id)
                    ->update(['gate_in_date' => $newGateInDate]);
                YardStorage::where('container_id', $movement->container_id)
                    ->whereNull('gate_out_date')
                    ->whereIn('hire_type', ['normal', 'resumed'])
                    ->update(['gate_in_date' => $newGateInDate]);
            }
        } else {
            foreach (['driver_name', 'driver_ic', 'release_order'] as $field) {
                if (!empty($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }
            foreach (['transporter_id', 'driver_phone'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field] ?: null;
                }
            }

            // Export information fields
            foreach (['loading_vessel', 'loading_voyage', 'sailing_date', 'shipper'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field] ?: null;
                }
            }

            if ($isAdmin && !empty($validated['gate_out_time'])) {
                $updateData['gate_out_time'] = \Carbon\Carbon::parse($validated['gate_out_time']);
            }
        }

        $movement->update($updateData);

        return redirect()->route('yard.movements.edit', $movement)
            ->with('success', "Gate movement #{$movement->id} updated successfully.");
    }

    public function gatePass(Request $request, GateMovement $movement)
    {
        $companySetting = \App\Models\CompanySetting::current();
        $defaultFormat  = $movement->movement_type === 'in'
            ? ($companySetting->default_gate_in_format  ?: 'full')
            : ($companySetting->default_gate_out_format ?: 'full');

        $format = in_array($request->query('format'), ['full', 'half', 'half-custom'])
            ? $request->query('format')
            : $defaultFormat;

        $movement->load(['container', 'customer', 'transporter', 'createdBy', 'approvalRequest.actions.actionedBy']);

        if ($movement->movement_type === 'in') {
            return view('yard.gate-pass-inward', compact('movement', 'format'));
        }

        // Pull the most recent Gate-In for this container to get import vessel / voyage
        $gateIn = GateMovement::where('container_id', $movement->container_id)
            ->where('movement_type', 'in')
            ->latest('gate_in_time')
            ->first();

        return view('yard.gate-pass', compact('movement', 'gateIn', 'format'));
    }

    public function verifyGatePass(Request $request, GateMovement $movement)
    {
        $movement->load(['customer', 'transporter']);
        $passType = $movement->movement_type;

        $gateIn = null;
        if ($passType === 'out') {
            $gateIn = GateMovement::where('container_id', $movement->container_id)
                ->where('movement_type', 'in')
                ->latest('gate_in_time')
                ->first();
        }

        // Cross-check URL params against the DB record to detect tampering
        $checks = [];

        if ($request->has('cn')) {
            $urlCn = strtoupper($request->query('cn'));
            $checks['Container No.'] = [
                'url'   => $urlCn,
                'db'    => $movement->container_no,
                'match' => $urlCn === $movement->container_no,
            ];
        }

        if ($request->has('sz')) {
            $urlSz = strtoupper($request->query('sz'));
            $dbSz  = $movement->size . $movement->container_type;
            $checks['Size / Type'] = [
                'url'   => $urlSz,
                'db'    => $dbSz,
                'match' => $urlSz === $dbSz,
            ];
        }

        if ($request->has('st')) {
            $urlSt  = strtoupper($request->query('st'));
            $dbSt   = strtolower($movement->cargo_status ?? '') === 'laden' ? 'L' : 'E';
            $checks['Status'] = [
                'url'   => $urlSt === 'L' ? 'LADEN' : 'EMPTY',
                'db'    => ucfirst(strtolower($movement->cargo_status ?? '—')),
                'match' => $urlSt === $dbSt,
            ];
        }

        if ($request->has('dt')) {
            $urlDt = $request->query('dt');
            $timeField = $passType === 'in' ? 'gate_in_time' : 'gate_out_time';
            $dbDt  = $movement->$timeField?->format('YmdHi');
            $label = $passType === 'in' ? 'Gate-In Time' : 'Gate-Out Time';
            $checks[$label] = [
                'url'   => $movement->$timeField
                    ? \Carbon\Carbon::createFromFormat('YmdHi', $urlDt)?->format('d M Y H:i')
                    : $urlDt,
                'db'    => $movement->$timeField?->format('d M Y H:i') ?? '—',
                'match' => $urlDt === $dbDt,
            ];
        }

        if ($request->has('vh')) {
            $normalize = fn($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper($v ?? ''));
            $urlVh = $request->query('vh');
            $checks['Vehicle Plate'] = [
                'url'   => strtoupper($urlVh),
                'db'    => $movement->vehicle_plate ?? '—',
                'match' => $normalize($urlVh) === $normalize($movement->vehicle_plate),
            ];
        }

        if ($request->has('co') && $passType === 'in') {
            $urlCo  = strtoupper($request->query('co'));
            $dbCond = strtolower($movement->condition ?? 'sound');
            $dbCo   = match($dbCond) { 'damaged' => 'DAM', 'require_repair' => 'REQ', default => 'SOU' };
            $checks['Condition'] = [
                'url'   => match($urlCo) { 'DAM' => 'DAMAGED', 'REQ' => 'REQ. REPAIR', default => 'GOOD' },
                'db'    => match($dbCond) { 'damaged' => 'DAMAGED', 'require_repair' => 'REQ. REPAIR', default => 'GOOD' },
                'match' => $urlCo === $dbCo,
            ];
        }

        $allMatch = !empty($checks) && collect($checks)->every(fn($c) => $c['match']);
        $hasParams = !empty($checks);

        return view('yard.gate-pass-verify', compact('movement', 'gateIn', 'passType', 'checks', 'allMatch', 'hasParams'));
    }

    public function destroyMovementPhoto(GateMovement $movement, GateMovementPhoto $photo)
    {
        if ($photo->gate_movement_id !== $movement->id) {
            abort(403);
        }
        // Legacy local photos still use public_path — remove if file exists
        if ($photo->photo_path && file_exists(public_path($photo->photo_path))) {
            @unlink(public_path($photo->photo_path));
        }
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }

    // -------------------------------------------------------------------------
    // Storage Calculator
    // -------------------------------------------------------------------------
    public function storage(Request $request)
    {
        $storageRecords = YardStorage::with(['container', 'customer'])
            ->nonHire()  // exclude on_hire records; those belong to the hire customer's ledger
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
    // Slot Grid for Zone (AJAX) — returns all slots in a zone for the picker
    // -------------------------------------------------------------------------
    public function slotsByZone(string $zoneCode)
    {
        $zone  = StorageZone::where('code', $zoneCode)->firstOrFail();
        $slots = YardLocation::where('zone', $zoneCode)
            ->with('container:id,container_no')
            ->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'zone'         => $s->zone,
                'row'          => $s->row,
                'bay'          => $s->bay,
                'tier'         => $s->tier,
                'status'       => $s->status,
                'slot_code'    => "{$s->row}{$s->bay}-T{$s->tier}",
                'full_code'    => "{$zoneCode}-{$s->row}{$s->bay}-T{$s->tier}",
                'container_no' => $s->container?->container_no,
            ]);

        return response()->json([
            'zone'  => ['code' => $zone->code, 'name' => $zone->name, 'color' => $zone->color],
            'slots' => $slots,
        ]);
    }

    // -------------------------------------------------------------------------
    // Container Lookup for Gate Out (AJAX)
    // Returns in-yard container details including Gate In info and days in yard
    // -------------------------------------------------------------------------
    public function containerLookup(Request $request)
    {
        $no = strtoupper(trim($request->query('container_no', '')));

        if (! $no) {
            return response()->json(['found' => false, 'message' => 'Container number is required.']);
        }

        $container = Container::with(['customer', 'equipmentType', 'grade', 'activeHire.hireCustomer'])
            ->where('container_no', $no)
            ->first();

        if (! $container) {
            return response()->json(['found' => false, 'message' => "Container {$no} not found."]);
        }

        if ($container->status !== 'in_yard') {
            return response()->json([
                'found'   => false,
                'message' => "Container {$no} is not in the yard (status: {$container->status}).",
            ]);
        }

        // Get the open normal/resumed storage record for days-in-yard calculation.
        // Exclude on_hire records so an on-hire container shows days from original gate-in.
        $storage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->latest('gate_in_date')
            ->first();

        // Get the linked Gate In movement (with job details)
        $gateInMovement = GateMovement::with('yardJob')
            ->where('container_id', $container->id)
            ->where('movement_type', 'in')
            ->latest('gate_in_time')
            ->first();

        $daysInYard = $storage
            ? max(0, $storage->billing_gate_in_date->diffInDays(today()))
            : ($container->gate_in_date ? $container->gate_in_date->diffInDays(today()) : null);

        return response()->json([
            'found'            => true,
            'container_no'     => $container->container_no,
            'size'             => $container->size,
            'type_code'        => $container->type_code,
            'equipment_label'  => $container->equipmentType
                ? $container->equipmentType->eqt_code . ' — ' . $container->equipmentType->description
                : ($container->size . "' " . $container->type_code),
            'customer'         => $container->customer?->name ?? '—',
            'condition'        => $container->condition,
            'cargo_status'     => $container->cargo_status,
            'location'         => implode(' ', array_filter([
                $container->location_zone ? 'Zone ' . $container->location_zone : null,
                $container->location_row,
                $container->location_bay  ? 'Bay ' . $container->location_bay  : null,
                $container->location_tier ? 'T'    . $container->location_tier : null,
            ])),
            'gate_in_date'     => $container->gate_in_date?->format('d M Y'),
            'gate_in_time'     => $gateInMovement?->gate_in_time?->format('d M Y, H:i'),
            'days_in_yard'     => $daysInYard,
            'gate_in_movement_id' => $gateInMovement?->id,
            'job_no'           => $gateInMovement?->yardJob?->job_no,
            'job_type'         => $gateInMovement?->yardJob?->job_type_code
                                    ?? $gateInMovement?->job_type_code,
            'job_status'       => $gateInMovement?->yardJob?->status,
            'grade_id'         => $container->grade_id,
            'grade_name'       => $container->grade?->name,
            'grade_code'       => $container->grade?->code,
            'ventilation_type' => $container->effective_ventilation_type,
            'vent_count'       => $container->effective_vent_count,
            'ventilation_label'=> $container->effective_ventilation_type
                ? ((\App\Models\EquipmentType::VENTILATION_TYPES[$container->effective_ventilation_type] ?? $container->effective_ventilation_type)
                    . ($container->effective_vent_count > 0 ? ' · ' . $container->effective_vent_count . ' vents' : ''))
                : null,
            'on_hire'          => $container->activeHire ? [
                'hire_party'   => $container->activeHire->hire_party_name,
                'on_hire_date' => $container->activeHire->on_hire_date->format('d M Y'),
                'hire_url'     => route('yard.hires.show', $container->activeHire),
            ] : null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Gate-Out autocomplete: returns containers currently in yard matching query
    // Used by Select2 on the Gate-Out container number field.
    // -------------------------------------------------------------------------
    public function inYardSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = strtoupper(trim($request->query('q', '')));

        $containers = Container::with(['customer', 'equipmentType'])
            ->where('status', 'in_yard')
            ->when($q, fn ($query) => $query->where('container_no', 'like', '%' . $q . '%'))
            ->orderBy('container_no')
            ->limit(25)
            ->get();

        return response()->json([
            'results' => $containers->map(fn ($c) => [
                'id'       => $c->container_no,
                'text'     => $c->container_no,
                'customer' => $c->customer->name ?? 'Unknown',
                'eqt_code' => $c->equipmentType?->eqt_code,
                'days'     => $c->gate_in_date ? (int) $c->gate_in_date->diffInDays(today()) : null,
            ]),
        ]);
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
            ->whereIn('hire_type', ['normal', 'resumed'])
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
            'location'           => "{$container->location_zone}-{$container->location_row}{$container->location_bay}-T{$container->location_tier}",
            'gate_in_date'       => $container->gate_in_date?->toDateString(),
        ]);
    }
}
