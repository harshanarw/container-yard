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

        // Containers currently in yard (any non-released disposition — includes
        // available/in-repair, which still physically occupy their slots)
        $inYardContainers = Container::with(['customer', 'equipmentType', 'activeHire'])
            ->whereIn('status', Container::IN_YARD_STATUSES)
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
            $guardCapture = GuardCapture::with(['capturedBy', 'clearedBy', 'equipmentType'])->find($request->capture_id);
            if ($guardCapture && $guardCapture->isCleared() && !$guardCapture->linked_gate_movement_id) {
                $prefill = [
                    'capture_id'        => $guardCapture->id,
                    'container_no'      => $guardCapture->container_number,
                    'iso_code'          => $guardCapture->iso_code,
                    // Exact equipment type resolved at capture (Phase 2) — the gate
                    // form preselects it directly instead of re-matching the ISO.
                    'equipment_type_id' => $guardCapture->equipment_type_id,
                    'vehicle_plate'     => $guardCapture->vehicle_number,
                    'driver_name'       => $guardCapture->driver_name,
                    'driver_ic'         => $guardCapture->nic_number,
                    'driver_phone'      => $guardCapture->driver_phone,
                    'reference_no'      => $guardCapture->reference_no,
                    'container_image'   => $guardCapture->container_image_url,
                ];

                // Gate-out of a container the yard doesn't have on record — flag it
                // so the operator is warned before trying to release it.
                if ($guardCapture->direction === 'gate_out' && $guardCapture->container_number) {
                    $prefill['container_missing'] = ! Container::where('container_no', $guardCapture->container_number)->exists();
                }
            }
        }

        $jobTypes = YardJobType::active()->forGateIn()->orderBy('sort_order')->get();
        $gateOutPurposes = YardJobType::active()->forGateOut()->orderBy('sort_order')->get();
        $openBookings = \App\Models\ContainerBooking::whereIn('status', ['open', 'partial'])
            ->orderByDesc('id')->get(['id', 'booking_no', 'customer_id']);

        $emptySlots = YardLocation::where('status', 'empty')
            ->orderBy('zone')->orderBy('row')->orderBy('bay')->orderBy('tier')
            ->get();

        return view('yard.gate', compact('recentMovements', 'search', 'customers', 'transporters', 'equipmentTypes', 'grades', 'zones', 'prefill', 'guardCapture', 'jobTypes', 'gateOutPurposes', 'openBookings', 'emptySlots'));
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
            'no_seal_reason'    => ['nullable', 'string', 'in:' . implode(',', self::NO_SEAL_REASONS)],
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

        // These nullable fields are read directly (without a ?? guard) when the
        // container / movement rows are built below. The browser form always posts
        // them (empty), but a partial API or mobile payload may omit them entirely
        // — and a key absent from the validated set would fatal on "Undefined array
        // key". Backfill nulls for any that are missing (union keeps submitted values).
        $validated += [
            'location_zone' => null, 'location_row'  => null, 'location_bay' => null,
            'location_tier' => null, 'seal_no'       => null, 'vehicle_plate' => null,
            'remarks'       => null,
        ];

        $jobType = YardJobType::findOrFail($validated['job_type_id']);

        // Return reason is required for Empty Return job type
        if ($jobType->job_type_code === 'EMPTY_RETURN' && empty($validated['return_reason'])) {
            return redirect()->back()
                ->withErrors(['return_reason' => 'Please select a return reason for Empty Return.'])
                ->withInput();
        }

        // ── Duplicate Gate-In guard ──────────────────────────────────────────
        $existingContainer = Container::where('container_no', $validated['container_no'])->first();

        // Check 1: container is currently in the yard (any present disposition)
        if ($existingContainer && in_array($existingContainer->status, Container::IN_YARD_STATUSES, true)) {
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

        // Seal policy: a laden gate-in must carry a seal, or a documented no-seal
        // reason, when the company enables the requirement (off by default).
        if ($err = $this->sealRequirementError(
            $validated['cargo_status'] === 'laden',
            $validated['seal_no'],
            $validated['no_seal_reason'] ?? null
        )) {
            return redirect()->back()->withErrors(['seal_no' => $err])->withInput();
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
            'status_changed_at' => now(),
            'available_since'   => null,  // arrived → out of the available pool until re-dispositioned
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
                'no_seal_reason'  => $validated['no_seal_reason'] ?? null,
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
        $yardJob   = null;
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
                    'yard_job_id'     => $yardJob?->id,
                    'customer_id'     => $validated['customer_id'],
                    'service_type'    => $validated['reefer_service_type'] ?? 'long_term',
                    'status'          => 'pending',
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[GateIn] Reefer plug session creation failed: ' . $e->getMessage());
        }

        // Auto-place a customs hold when the container enters under a customs-hold job.
        try {
            if ($jobType->job_type_code === 'CUSTOMS_HOLD_IN') {
                app(\App\Services\HoldService::class)->place($container, 'customs', 'Auto-placed on Customs Hold In gate-in', auth()->id());
            }
        } catch (\Throwable $e) {
            \Log::warning('[GateIn] Customs auto-hold failed: ' . $e->getMessage());
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

        // Surface any soft warnings together (photo save + ISO 6346 check digit).
        $warnings = [];
        if ($photoError) {
            $warnings[] = $photoError;
        }
        if (! \App\Support\Iso6346::checkDigitValid($container->container_no)) {
            $warnings[] = "Container {$container->container_no} fails the ISO 6346 check digit — please verify it against the box.";
        }
        if ($w = $this->noGuardCaptureWarning($request)) {
            $warnings[] = $w;
        }
        if ($warnings) {
            $redirect->with('warning', implode('  ', $warnings));
        }

        return $redirect;
    }

    /**
     * Optional nudge (Phase B): when Guard Post is on and the operator opted into
     * the reminder, flag a gate movement that isn't linked to a cleared capture.
     * Non-blocking — returns the message, or null when it shouldn't fire.
     */
    private function noGuardCaptureWarning(Request $request): ?string
    {
        if ($request->filled('guard_capture_id')) {
            return null; // a capture was linked — nothing to warn about
        }

        $cs = \App\Models\CompanySetting::current();
        if (! $cs->enable_guard_post || ! $cs->guardpost_warn_no_capture) {
            return null;
        }

        return 'Recorded without a linked Guard Post capture — check the Review Queue if a capture exists.';
    }

    /** Documented reasons a laden container may legitimately move without a seal. */
    public const NO_SEAL_REASONS = ['lcl', 'customs_exam', 'broken_missing', 'special_equipment', 'other'];

    /**
     * Seal policy for laden moves. When require_seal_for_laden is on, a laden
     * gate movement must carry a seal number, or a documented no-seal reason
     * (LCL, customs exam, broken/missing, special equipment). Returns the
     * validation message to raise on `seal_no`, or null when the move is allowed.
     */
    private function sealRequirementError(bool $isLaden, ?string $sealNo, ?string $reason): ?string
    {
        if (! $isLaden) {
            return null; // empties are never sealed
        }
        if (! \App\Models\CompanySetting::current()->require_seal_for_laden) {
            return null; // policy off
        }
        if (filled($sealNo) || filled($reason)) {
            return null; // sealed, or a documented exception was given
        }

        return 'A seal number is required for laden containers. Enter the seal number, '
             . 'or record a no-seal reason (LCL, customs exam, broken/missing, or special equipment).';
    }

    public function gateOut(Request $request)
    {
        $validated = $request->validate([
            'container_no'  => [
                'required', 'string',
                'exists:containers,container_no',
                function ($attr, $val, $fail) {
                    $c = Container::where('container_no', $val)->first();
                    // Releasable dispositions: in the yard (in_yard), sound stock
                    // (available) or booked (reserved). An 'in_repair' box must finish
                    // repair first; 'released' has already left.
                    if ($c && !in_array($c->status, ['in_yard', 'available', 'reserved'], true)) {
                        $reason = $c->status === 'in_repair'
                            ? 'it is under repair — complete or close the work order first'
                            : "its status is '{$c->status}'";
                        $fail("Container {$val} cannot be gated out: {$reason}.");
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
            'no_seal_reason' => ['nullable', 'string', 'in:' . implode(',', self::NO_SEAL_REASONS)],
            'grade_id'       => ['nullable', 'exists:container_grades,id'],
            // Purpose + booking (export release)
            'gate_out_purpose'     => ['nullable', 'string', 'max:30'],
            'container_booking_id' => ['nullable', 'integer', 'exists:container_bookings,id'],
            // Hold override (authorised users only)
            'hold_override'        => ['nullable', 'boolean'],
            'hold_override_reason' => ['nullable', 'string', 'max:255'],
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

        // ── Booking / export-release context ────────────────────────────────
        // The release may fulfil a booking either because the container was
        // pre-reserved, or via reserve-at-gate (a booking chosen now, matching an
        // open line by size/type). Export-Release purposes expect a booking; the
        // enforce_export_booking setting makes that a hard rule instead of a warning.
        $purposeCode  = $validated['gate_out_purpose'] ?? null;
        $purpose      = $purposeCode
            ? \App\Models\YardJobType::where('job_type_code', $purposeCode)->where('movement_direction', 'gate_out')->first()
            : null;
        $needsBooking = (bool) ($purpose?->booking_applicable);

        $reservedLine = $container->status === 'reserved' ? $container->bookingLine : null;
        $gateLine     = $reservedLine;

        if (!$gateLine && !empty($validated['container_booking_id'])) {
            $booking = \App\Models\ContainerBooking::with('lines')->find($validated['container_booking_id']);
            // Match a line with a genuinely free slot (unallocated > 0, not just
            // not-yet-released), preferring an exact grade match when the container
            // is graded — mirrors the allocation rules so counters can't overshoot.
            $lines   = $booking?->lines->filter(fn ($l) => $l->size === $container->size
                && $l->type_code === $container->type_code
                && $l->unallocated > 0) ?? collect();
            $gateLine = $lines->first(fn ($l) => $l->grade_id && $l->grade_id === $container->grade_id)
                ?? $lines->first();
        }

        if ($needsBooking && !$gateLine) {
            if ((bool) (\App\Models\CompanySetting::current()->enforce_export_booking ?? false)) {
                return back()->withErrors(['container_no' =>
                    "An export release requires a booking, but no matching open booking line was found for {$container->container_no}."
                ])->withInput();
            }
            session()->flash('warning', "Container {$container->container_no} was released for export without a booking reservation.");
        }

        // ── Hold block ───────────────────────────────────────────────────────
        // A held container cannot be gated out — except a Customs Release, which is
        // the flow that clears the customs hold. An authorised user (containers.hold)
        // may override any other hold with a reason.
        if ($container->isHeld() && $purposeCode !== 'CUSTOMS_RELEASE') {
            $overriding = $request->boolean('hold_override')
                && auth()->user()->can('containers.hold')
                && filled($request->input('hold_override_reason'));

            if (!$overriding) {
                $holdList = $container->activeHolds()->pluck('hold_type')
                    ->map(fn ($t) => str_replace('_', ' ', $t))->implode(', ');
                return back()->withErrors(['container_no' =>
                    "Container {$container->container_no} is on hold ({$holdList}) and cannot be gated out. "
                    . 'Clear the hold, or an authorised user can override with a reason.'
                ])->withInput();
            }
        }

        // ── Reefer PTI gate ──────────────────────────────────────────────────
        // A reefer released for export must carry a valid (passing, unexpired) PTI.
        // The gate fires on an export-booking release (EXPORT_RELEASE) or any
        // reefer-applicable purpose (REEFER_OUT) — driven by the job-type flags,
        // not hard-coded codes. enforce_reefer_pti makes it a hard block; else warn.
        $reeferRelease = $needsBooking || (bool) ($purpose?->reefer_applicable);
        if ($container->isReefer() && $reeferRelease && !$container->hasValidPti()) {
            if ((bool) (\App\Models\CompanySetting::current()->enforce_reefer_pti ?? false)) {
                return back()->withErrors(['container_no' =>
                    "Reefer {$container->container_no} has no valid PTI on record and cannot be released. "
                    . 'Record a passing pre-trip inspection first.'
                ])->withInput();
            }
            session()->flash('warning', "Reefer {$container->container_no} was released without a valid PTI.");
        }

        // Seal policy: a laden gate-out must carry a seal, or a documented no-seal
        // reason, when the company enables the requirement (off by default). Laden
        // status comes from the container record, not the form.
        if ($err = $this->sealRequirementError(
            $container->cargo_status === 'laden',
            $validated['seal_no'] ?? null,
            $validated['no_seal_reason'] ?? null
        )) {
            return back()->withErrors(['seal_no' => $err])->withInput();
        }

        // Resolve actual gate-out datetime — admin can override, others use now()
        $gateOutTime = (auth()->user()->can('yard.backdate') && !empty($validated['gate_out_time']))
            ? \Carbon\Carbon::parse($validated['gate_out_time'])
            : now();
        $gateOutDate = $gateOutTime->toDateString();

        // Record gate movement
        $movement = DB::transaction(function () use ($container, $validated, $gateOutTime, $purposeCode, $gateLine) {
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
                'no_seal_reason'  => $validated['no_seal_reason'] ?? null,
                'gate_out_time'   => $gateOutTime,
                'movement_status' => 'done',
                'remarks'         => $validated['remarks'],
                'created_by'      => auth()->id(),
                // Export information
                'loading_vessel'  => $validated['loading_vessel'] ?? null,
                'loading_voyage'  => $validated['loading_voyage'] ?? null,
                'sailing_date'    => $validated['sailing_date'] ?? null,
                'shipper'         => $validated['shipper'] ?? null,
                // Purpose + booking fulfilled
                'gate_out_purpose'     => $purposeCode,
                'container_booking_id' => $gateLine?->container_booking_id ?? ($validated['container_booking_id'] ?? null),
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

        // Release the container and record any booking fulfilment ATOMICALLY, so a
        // failure can't leave the box released with stale booking counters. A
        // pre-reserved container moves allocated → released and clears its link; a
        // reserve-at-gate release just increments the chosen line's released count.
        DB::transaction(function () use ($container, $gateOutDate, $reservedLine, $gateLine, $purposeCode) {
            $container->update([
                'status'            => 'released',
                'status_changed_at' => now(),
                'available_since'   => null,  // left the yard → out of the available pool
                'location_zone'     => null,
                'gate_out_date'     => $gateOutDate,
                'location_row'      => null,
                'location_bay'      => null,
                'location_tier'     => null,
            ]);

            if ($reservedLine) {
                app(\App\Services\BookingService::class)->recordRelease($container);
            } elseif ($gateLine) {
                app(\App\Services\BookingService::class)->recordReleaseForLine($gateLine);
            }

            // A Customs Release clears the container's customs hold(s) as it leaves.
            if ($purposeCode === 'CUSTOMS_RELEASE') {
                app(\App\Services\HoldService::class)->clearByType($container, 'customs', 'Cleared at customs release gate-out', auth()->id());
            }
        });

        NotificationService::notifyAll(
            'Gate OUT — ' . $container->container_no,
            ($container->customer->name ?? 'Unknown') . ' · Container released',
            'info',
            route('yard.movements.edit', $movement)
        );

        $redirect = redirect()->route('yard.movements.gate-pass', $movement)
            ->with('gp_note', "Gate OUT recorded for {$container->container_no}.");

        $warnings = [];
        if ($photoError) {
            $warnings[] = $photoError;
        }
        if ($w = $this->noGuardCaptureWarning($request)) {
            $warnings[] = $w;
        }
        if ($warnings) {
            $redirect->with('warning', implode('  ', $warnings));
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

    /**
     * Driver-facing paperless gate pass, reached via a temporary signed URL
     * (shared over WhatsApp). Renders the company's default print format —
     * exactly the same header/footer/QR layout as the staff printout — flagged
     * as driverView so staff-only toolbar links are hidden but the driver keeps
     * the Print / Save-PDF action.
     */
    public function driverGatePass(GateMovement $movement)
    {
        return $this->renderDriverPass($movement);
    }

    /**
     * Short branded link (/g/{code}) shared with the driver over WhatsApp —
     * resolves the movement by its unguessable share code, then renders the
     * same driver print view. Refuses access once the link's window has passed.
     */
    public function shortGatePass(string $code)
    {
        $movement = GateMovement::where('share_code', $code)->firstOrFail();

        if (! $movement->shareLinkIsValid()) {
            return response()->view('yard.gate-pass-expired', ['movement' => $movement], 410);
        }

        return $this->renderDriverPass($movement);
    }

    /**
     * Refresh the driver share link's validity window and hand off to WhatsApp
     * with a pre-filled message. Redirecting through the server means every
     * "Send" gives the driver a fresh window (like the old signed URL) without
     * writing to the DB on every gate-list render.
     */
    public function whatsappGatePass(GateMovement $movement)
    {
        $cs = \App\Models\CompanySetting::current();

        if (! $cs->enable_gatepass_whatsapp) {
            return back()->with('error', 'Gate pass WhatsApp sharing is turned off in Company Settings.');
        }
        if (! $movement->driver_phone) {
            return back()->with('error', 'This movement has no driver phone number to send to.');
        }

        $movement->refreshShareLink();

        $company = $cs->company_name ?: 'Container Yard';
        $link    = $cs->gatePassUrl($movement->share_code);
        $message = '*' . $company . '*' . "\n"
                 . 'Hello' . ($movement->driver_name ? ' ' . $movement->driver_name : '')
                 . ', your ' . ($movement->movement_type === 'out' ? 'outward' : 'inward')
                 . ' gate pass for container ' . $movement->container_no
                 . ' is ready (link valid ' . GateMovement::SHARE_LINK_DAYS . ' days). Tap to view, download or print:' . "\n"
                 . $link;

        $waUrl = \App\Services\WhatsAppLink::chatUrl($movement->driver_phone, $message);
        if (! $waUrl) {
            return back()->with('error', 'Could not build a WhatsApp link for this driver number.');
        }

        return redirect()->away($waUrl);
    }

    private function renderDriverPass(GateMovement $movement)
    {
        $companySetting = \App\Models\CompanySetting::current();
        $format = $movement->movement_type === 'in'
            ? ($companySetting->default_gate_in_format  ?: 'full')
            : ($companySetting->default_gate_out_format ?: 'full');

        $movement->load(['container', 'customer', 'transporter', 'createdBy', 'approvalRequest.actions.actionedBy']);

        if ($movement->movement_type === 'in') {
            return view('yard.gate-pass-inward', [
                'movement' => $movement, 'format' => $format, 'driverView' => true,
            ]);
        }

        $gateIn = GateMovement::where('container_id', $movement->container_id)
            ->where('movement_type', 'in')
            ->latest('gate_in_time')
            ->first();

        return view('yard.gate-pass', [
            'movement' => $movement, 'gateIn' => $gateIn, 'format' => $format, 'driverView' => true,
        ]);
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

        if (!in_array($container->status, Container::IN_YARD_STATUSES, true)) {
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

        // Can this container actually be gated out right now? Mirrors the gate-out
        // validation so the form can warn at selection time instead of on save.
        $releaseBlock = null;
        if (! in_array($container->status, ['in_yard', 'available', 'reserved'], true)) {
            $releaseBlock = $container->status === 'in_repair'
                ? 'It is under repair — complete or close the work order first.'
                : "Its status is '{$container->status}'.";
        } elseif ($container->activeHire) {
            $releaseBlock = 'It is currently on hire — complete or cancel the hire before gating out.';
        } elseif ($container->isHeld()) {
            $holds = $container->activeHolds()->pluck('hold_type')
                ->map(fn ($t) => str_replace('_', ' ', $t))->implode(', ');
            $releaseBlock = "It is on hold ({$holds}). Clear the hold first — a Customs Release clears a customs hold.";
        }

        return response()->json([
            'found'            => true,
            'releasable'       => is_null($releaseBlock),
            'release_block'    => $releaseBlock,
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

    /**
     * Does a Guard Post capture exist for this container + direction? Lets the
     * gate officer see the guard's clearance status while typing/scanning, and
     * link a cleared capture without going through the queue. No-op when the
     * Guard Post feature is off.
     */
    public function guardPostCheck(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! \App\Models\CompanySetting::current()->enable_guard_post) {
            return response()->json(['enabled' => false, 'match' => false]);
        }

        $no  = strtoupper(trim($request->query('container_no', '')));
        $dir = $request->query('direction') === 'out' ? 'gate_out' : 'gate_in';

        if ($no === '') {
            return response()->json(['enabled' => true, 'match' => false]);
        }

        $base = GuardCapture::where('container_number', $no)->where('direction', $dir);

        // Prefer a cleared, not-yet-linked capture (the actionable one); otherwise
        // surface the latest capture so pending/hold/rejected are still visible.
        $cleared = (clone $base)->where('status', 'cleared')
            ->whereNull('linked_gate_movement_id')->latest('id')->first();
        $capture = $cleared ?? (clone $base)->latest('id')->first();

        if (! $capture) {
            return response()->json(['enabled' => true, 'match' => false]);
        }

        // Relations the verification panel reads (guard/officer names).
        $capture->loadMissing(['capturedBy', 'clearedBy', 'equipmentType']);

        return response()->json([
            'enabled'    => true,
            'match'      => true,
            'actionable' => (bool) $cleared,   // cleared + unlinked + right direction
            // The full verification panel (photos + driver/vehicle detail), rendered
            // from the same partial the queue-promote path @includes. No re-scan
            // buttons here — the officer keyed the number themselves.
            'panel_html' => view('yard.partials.guard-post-panel', [
                'guardCapture' => $capture,
                'rescan'       => false,
            ])->render(),
            'capture'    => [
                'id'           => $capture->id,
                'reference_no' => $capture->reference_no,
                'status'       => $capture->status,
                'status_label' => $capture->status_label,
                'direction'    => $capture->direction,
                'cleared_at'   => $capture->cleared_at?->format('d M Y H:i'),
                'linked'       => (bool) $capture->linked_gate_movement_id,
            ],
            'prefill'    => $cleared ? [
                'guard_capture_id'  => $cleared->id,
                'equipment_type_id' => $cleared->equipment_type_id,
                'vehicle_plate'     => $cleared->vehicle_number,
                'driver_name'       => $cleared->driver_name,
                'driver_ic'         => $cleared->nic_number,
                'driver_phone'      => $cleared->driver_phone,
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
            ->whereIn('status', Container::IN_YARD_STATUSES)
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
