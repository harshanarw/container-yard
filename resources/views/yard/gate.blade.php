@extends('layouts.app')

@section('title', 'Gate In / Gate Out')

@section('breadcrumb')
    <li class="breadcrumb-item active">Gate In / Gate Out</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Gate In / Gate Out</h4>
        <p class="text-muted mb-0 small">Record container arrivals and departures from the yard</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('yard.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-map me-1"></i>Yard Map
        </a>
        <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ now()->format('d M Y, H:i') }}</span>
    </div>
</div>

<!-- Quick Toggle: Gate In / Gate Out -->
<div class="d-flex flex-wrap gap-3 mb-4">
    <button class="btn btn-primary px-4" id="btnGateIn">
        <i class="bi bi-box-arrow-in-right me-2"></i>Gate In
    </button>
    <button class="btn btn-outline-success px-4" id="btnGateOut">
        <i class="bi bi-box-arrow-right me-2"></i>Gate Out
    </button>
</div>

<div class="row g-3">

    <!-- Gate In / Out Form -->
    <div class="col-lg-6">

        {{-- ── Gate In Card ──────────────────────────────────────────── --}}
        <div class="card content-card" id="gateInCard">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-box-arrow-in-right me-2"></i>Gate In — Container Arrival
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.in') }}" id="gateInForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="movement_type" value="in">

                    @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 1 — Container Details (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#eff6ff;border-left:3px solid #3b82f6;">
                        <i class="bi bi-box-seam text-primary"></i>
                        <span class="fw-semibold text-primary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Container Details</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="container_no" id="containerNoIn"
                                       class="form-control font-monospace text-uppercase"
                                       placeholder="XXXX0000000" required autocomplete="off" maxlength="11">
                                <button type="button" class="btn btn-outline-secondary" id="ocrBtnIn" title="Scan container with camera">
                                    <i class="bi bi-camera" id="ocrIconIn"></i>
                                </button>
                            </div>
                            {{-- Hidden file input for Gate-In OCR camera --}}
                            <input type="file" id="ocrInputIn" accept="image/*" capture="environment" class="d-none">
                            <div id="masterLookupInfo" class="mt-1 small d-none"></div>
                            <div id="ocrResultIn" class="mt-1 small d-none"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="gateEqtSelect" class="form-select s2-code" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}"
                                            data-code="{{ $eqt->eqt_code }}"
                                            data-name="{{ $eqt->description }}"
                                            data-size="{{ $eqt->size }}"
                                            data-type="{{ $eqt->type_code }}"
                                            data-eqt="{{ $eqt->eqt_code }}"
                                            data-iso="{{ $eqt->iso_code ?? '' }}"
                                            @if(in_array($eqt->type_code, ['RF','RH'])) data-chip-class="s2-code-chip s2-chip-reefer" @endif>
                                        {{ $eqt->eqt_code }} — {{ $eqt->description }}
                                    </option>
                                    @endforeach
                                </select>
                                <span id="gateEqtSizeBadge" class="badge bg-light border text-dark text-nowrap d-none"></span>
                                <span id="gateEqtTypeBadge" class="badge bg-info-subtle text-info text-nowrap d-none"></span>
                            </div>
                            <input type="hidden" name="size" id="gateEqtSize">
                            <input type="hidden" name="type_code" id="gateEqtTypeCode">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select s2-code" required data-s2-sel="name">
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" data-code="{{ $customer->code }}" data-name="{{ $customer->name }}">{{ $customer->code }} — {{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound">Sound</option>
                                <option value="damaged">Damaged</option>
                                <option value="require_repair">Requires Repair</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Empty / Laden</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty">Empty</option>
                                <option value="laden">Laden</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 1b — Additional Container Details (collapsible)
                    ════════════════════════════════════════════════════════ --}}
                    @php
                        $additionalHasOld = old('tare_weight_kg') || old('gross_weight_kg') || old('max_payload_kg')
                            || old('manufacture_year') || old('manufacturer') || old('owner_code')
                            || old('owner_name') || old('csc_plate_no') || old('csc_expiry_date');
                    @endphp
                    <div class="mb-3">
                    <div class="gate-section-hdr gate-section-collapse rounded-top mb-0"
                         style="background:#f5f3ff;border-left:3px solid #8b5cf6;"
                         data-bs-toggle="collapse" data-bs-target="#inAdditionalSection"
                         aria-expanded="{{ $additionalHasOld ? 'true' : 'false' }}" role="button">
                        <span>
                            <i class="bi bi-database me-2" style="color:#8b5cf6;"></i>
                            <span class="fw-semibold" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;color:#6d28d9;">Additional Container Details</span>
                            <span class="badge bg-secondary-subtle text-secondary fw-normal ms-2" style="font-size:.65rem;text-transform:none;">Optional</span>
                            <span id="additionalPrefillBadge" class="badge ms-1 d-none" style="font-size:.65rem;text-transform:none;background:#ddd4fe;color:#5b21b6;"></span>
                        </span>
                        <i class="bi bi-chevron-down collapse-chevron" style="color:#8b5cf6;"></i>
                    </div>
                    <div class="collapse {{ $additionalHasOld ? 'show' : '' }}" id="inAdditionalSection">
                        <div class="rounded-bottom p-3" style="border:1px solid #8b5cf6;border-top:none;">
                            <div class="row g-3">
                                {{-- Weights --}}
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Tare Weight <span class="text-muted fw-normal">(kg)</span></label>
                                    <input type="number" name="tare_weight_kg" id="add_tare_weight_kg"
                                           class="form-control" placeholder="e.g. 2200"
                                           min="0" max="99999" step="1" value="{{ old('tare_weight_kg') }}">
                                    <div id="hint_add_tare_weight_kg" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Max Gross Weight <span class="text-muted fw-normal">(kg)</span></label>
                                    <input type="number" name="gross_weight_kg" id="add_gross_weight_kg"
                                           class="form-control" placeholder="e.g. 30480"
                                           min="0" max="99999" step="1" value="{{ old('gross_weight_kg') }}">
                                    <div id="hint_add_gross_weight_kg" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Max Payload <span class="text-muted fw-normal">(kg, auto)</span></label>
                                    <input type="number" name="max_payload_kg" id="add_max_payload_kg"
                                           class="form-control" placeholder="Auto-calculated"
                                           min="0" max="99999" step="1" value="{{ old('max_payload_kg') }}">
                                    <div id="hint_add_max_payload_kg" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                {{-- Manufacture --}}
                                <div class="col-3">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Year of Manufacture</label>
                                    <input type="number" name="manufacture_year" id="add_manufacture_year"
                                           class="form-control" placeholder="{{ date('Y') }}"
                                           min="1970" max="{{ date('Y') + 1 }}" value="{{ old('manufacture_year') }}">
                                    <div id="hint_add_manufacture_year" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                <div class="col-9">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Manufacturer</label>
                                    <input type="text" name="manufacturer" id="add_manufacturer"
                                           class="form-control" placeholder="e.g. Triton, Florens, Maersk Container Industry"
                                           maxlength="100" value="{{ old('manufacturer') }}">
                                    <div id="hint_add_manufacturer" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                {{-- Owner --}}
                                <div class="col-3">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Owner Code</label>
                                    <input type="text" name="owner_code" id="add_owner_code"
                                           class="form-control font-monospace text-uppercase"
                                           placeholder="e.g. MSC" maxlength="20" value="{{ old('owner_code') }}">
                                    <div id="hint_add_owner_code" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                <div class="col-9">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">Owner / Operator Name</label>
                                    <input type="text" name="owner_name" id="add_owner_name"
                                           class="form-control" placeholder="e.g. Mediterranean Shipping Company"
                                           maxlength="100" value="{{ old('owner_name') }}">
                                    <div id="hint_add_owner_name" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                {{-- CSC --}}
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">CSC Plate No.</label>
                                    <input type="text" name="csc_plate_no" id="add_csc_plate_no"
                                           class="form-control font-monospace" placeholder="CSC plate serial"
                                           maxlength="50" value="{{ old('csc_plate_no') }}">
                                    <div id="hint_add_csc_plate_no" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold" style="font-size:.85rem;">CSC Expiry Date</label>
                                    <input type="date" name="csc_expiry_date" id="add_csc_expiry_date"
                                           class="form-control" value="{{ old('csc_expiry_date') }}">
                                    <div id="hint_add_csc_expiry_date" class="mt-1" style="font-size:.72rem;min-height:1.1rem;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>{{-- end Additional Container Details wrapper --}}

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 2 — Import Shipment Information (collapsible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="mb-3">
                    <div class="gate-section-hdr gate-section-collapse rounded-top mb-0"
                         style="background:#ecfeff;border-left:3px solid #0ea5e9;"
                         data-bs-toggle="collapse" data-bs-target="#inImportSection"
                         aria-expanded="false" role="button">
                        <span>
                            <i class="bi bi-ship text-info me-2"></i>
                            <span class="fw-semibold text-info" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Import Shipment Information</span>
                            <span class="badge bg-secondary-subtle text-secondary fw-normal ms-2" style="font-size:.65rem;text-transform:none;">Optional</span>
                        </span>
                        <i class="bi bi-chevron-down text-info collapse-chevron"></i>
                    </div>
                    <div class="collapse" id="inImportSection">
                        <div class="rounded-bottom p-3" style="border:1px solid #0ea5e9;border-top:none;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Ex / Discharged Vessel</label>
                                    <input type="text" name="vessel_name" class="form-control" placeholder="Vessel name">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Voyage No.</label>
                                    <input type="text" name="voyage_no" class="form-control" placeholder="e.g. 001W">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-semibold">Berthing Date</label>
                                    <input type="date" name="berthing_date" class="form-control">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-semibold">BL Number</label>
                                    <input type="text" name="bl_number" class="form-control" placeholder="Bill of Lading No.">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-semibold">D/O Expiry Date</label>
                                    <input type="date" name="do_expiry_date" class="form-control">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-semibold">FCL Expiry Date</label>
                                    <input type="date" name="fcl_expiry_date" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Consignee</label>
                                    <input type="text" name="consignee" class="form-control" placeholder="Consignee name">
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>{{-- end Import Shipment wrapper --}}

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 3 — Transport Details (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#f3f4f6;border-left:3px solid #6b7280;">
                        <i class="bi bi-truck text-secondary"></i>
                        <span class="fw-semibold text-secondary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Transport Details</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Truck / Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase" placeholder="e.g. WQR 1234">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Transporter
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <select name="transporter_id" class="form-select s2-code" data-s2-sel="name">
                                <option value="">— Select Transporter —</option>
                                @foreach($transporters as $t)
                                <option value="{{ $t->id }}" data-code="{{ $t->code }}" data-name="{{ $t->name }}">
                                    {{ $t->code }} — {{ $t->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">
                                Driver Name
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <input type="text" name="driver_name" class="form-control" placeholder="Driver's full name">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">
                                Driver NIC
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <input type="text" name="driver_ic" class="form-control" placeholder="IC / Passport No.">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">
                                Driver Phone
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <input type="text" name="driver_phone" class="form-control" placeholder="+60 12-345 6789">
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 4 — Storage Location (collapsible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="mb-3">
                    <div class="gate-section-hdr gate-section-collapse rounded-top mb-0"
                         style="background:#f0fdf4;border-left:3px solid #22c55e;"
                         data-bs-toggle="collapse" data-bs-target="#inLocationSection"
                         aria-expanded="false" role="button">
                        <span>
                            <i class="bi bi-geo-alt text-success me-2"></i>
                            <span class="fw-semibold text-success" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Storage Location</span>
                            <span class="badge bg-secondary-subtle text-secondary fw-normal ms-2" style="font-size:.65rem;text-transform:none;">Optional — assign later</span>
                        </span>
                        <i class="bi bi-chevron-down text-success collapse-chevron"></i>
                    </div>
                    <div class="collapse" id="inLocationSection">
                        <div class="rounded-bottom p-3" style="border:1px solid #22c55e;border-top:none;">

                            {{-- Hidden submission fields --}}
                            <input type="hidden" name="location_zone" id="loc_zone">
                            <input type="hidden" name="location_row"  id="loc_row">
                            <input type="hidden" name="location_bay"  id="loc_bay">
                            <input type="hidden" name="location_tier" id="loc_tier">

                            {{-- Selected slot display --}}
                            <div id="selectedSlotDisplay" class="alert alert-primary py-2 small d-none mb-2">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Slot selected: <strong id="selectedSlotCode" class="font-monospace"></strong>
                                <button type="button" class="btn btn-sm btn-link py-0 ps-2 text-primary" id="clearSlotBtn">
                                    <i class="bi bi-pencil-fill"></i> Change
                                </button>
                            </div>

                            {{-- Step 1: Zone cards --}}
                            <div id="zoneSelectorPanel">
                                <p class="text-muted small mb-2">
                                    <span class="badge bg-primary-subtle text-primary rounded-pill me-1">Step 1</span>
                                    Select a storage zone <span class="text-muted">(optional — can be assigned later)</span>:
                                </p>
                                <div class="d-flex gap-2 flex-wrap" id="zoneCards">
                                    @foreach($zones as $zone)
                                    <button type="button" class="zone-pick-btn btn btn-outline-secondary"
                                            data-zone="{{ $zone->code }}"
                                            data-name="{{ $zone->name }}"
                                            style="border-left: 4px solid {{ $zone->color }};">
                                        <span class="fw-semibold">{{ $zone->name }}</span>
                                        <span class="d-block" style="font-size:.7rem;">
                                            <span class="text-success">{{ $zone->empty_count }} free</span>
                                            &nbsp;/&nbsp;
                                            <span class="text-danger">{{ $zone->occupied_count }} occ.</span>
                                        </span>
                                    </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Step 2: Slot grid (loaded via AJAX) --}}
                            <div id="slotGridPanel" class="d-none mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="text-muted small mb-0">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill me-1">Step 2</span>
                                        Click an <span class="text-success fw-semibold">available</span> slot:
                                        <span id="gridZoneLabel" class="fw-semibold"></span>
                                    </p>
                                    <button type="button" class="btn btn-sm btn-link p-0" id="backToZonesBtn">
                                        <i class="bi bi-arrow-left me-1"></i>Change zone
                                    </button>
                                </div>
                                <div class="d-flex gap-3 mb-2" style="font-size:.72rem;">
                                    <span><span class="legend-dot" style="background:#22c55e;"></span> Available</span>
                                    <span><span class="legend-dot" style="background:#ef4444;"></span> Occupied</span>
                                    <span><span class="legend-dot" style="background:#eab308;"></span> Reserved</span>
                                    <span><span class="legend-dot" style="background:#ec4899;"></span> Damaged / Repair</span>
                                    <span><span class="legend-dot" style="background:#3b82f6;border:2px solid #1d4ed8;"></span> Your selection</span>
                                </div>
                                <div id="slotGridLoading" class="text-center text-muted py-3 d-none">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Loading slots…
                                </div>
                                <div id="slotGridContent" style="overflow-x:auto; max-height:300px; overflow-y:auto;"></div>
                            </div>

                        </div>
                    </div>
                    </div>{{-- end Storage Location wrapper --}}

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 5 — Remarks & Date/Time (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#f8f9fa;border-left:3px solid #9ca3af;">
                        <i class="bi bi-calendar-event text-secondary"></i>
                        <span class="fw-semibold text-secondary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Remarks &amp; Date / Time</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Any remarks…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate In Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Auto
                                    </span>
                                @endif
                            </label>
                            <input type="text" name="gate_in_time" id="gateInTime"
                                   class="form-control" autocomplete="off"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ── Photo Evidence ───────────────────────────────────── --}}
                    <div class="mt-1">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-camera me-1 text-primary"></i>Photo Evidence
                            <span class="text-muted fw-normal small">(optional, max 5)</span>
                            <span id="inPhotoCounter" class="badge bg-secondary-subtle text-secondary ms-1">0 / 5</span>
                        </label>
                        <input type="file" id="inPhotoInput" multiple accept="image/*" class="d-none">
                        <input type="file" id="inCameraInput" accept="image/*" capture="environment" class="d-none">
                        <div id="inDropZone" class="border border-2 rounded-3 text-center p-3 mb-2"
                             style="border-color:#dee2e6!important;border-style:dashed!important;cursor:pointer;transition:background .2s;">
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="inBrowseBtn"><i class="bi bi-folder2-open me-1"></i>Browse</button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="inCameraBtn"><i class="bi bi-camera me-1"></i>Camera</button>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.72rem;">or drag &amp; drop images here &nbsp;·&nbsp; max 5 MB each</div>
                        </div>
                        <div id="inPhotoError" class="alert alert-danger py-1 small d-none mb-2"></div>
                        <div class="row g-1" id="inPhotoPreview"></div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Record Gate In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Gate Out Card ─────────────────────────────────────────── --}}
        <div class="card content-card d-none" id="gateOutCard">
            <div class="card-header bg-success text-white">
                <i class="bi bi-box-arrow-right me-2"></i>Gate Out — Container Departure
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.out') }}" id="gateOutForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="movement_type" value="out">

                    @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 1 — Container (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#eff6ff;border-left:3px solid #3b82f6;">
                        <i class="bi bi-box-seam text-primary"></i>
                        <span class="fw-semibold text-primary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Container</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" class="form-control font-monospace text-uppercase"
                                   placeholder="XXXX0000000" required id="containerSearch" maxlength="11" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="ocrBtnOut" title="Scan container with camera">
                                <i class="bi bi-camera" id="ocrIconOut"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="containerSearchBtn"><i class="bi bi-search"></i></button>
                        </div>
                        {{-- Hidden file input for Gate-Out OCR camera --}}
                        <input type="file" id="ocrInputOut" accept="image/*" capture="environment" class="d-none">
                        <div class="form-text text-muted" style="font-size:.72rem;">Enter and search to confirm the container is in yard.</div>
                    </div>
                    <div id="ocrResultOut" class="mb-2 small d-none"></div>
                    <div id="containerInfoBox" class="mb-3 d-none"></div>

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 2 — Export Information (collapsible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="mb-3">
                    <div class="gate-section-hdr gate-section-collapse rounded-top mb-0"
                         style="background:#ecfeff;border-left:3px solid #0ea5e9;"
                         data-bs-toggle="collapse" data-bs-target="#outExportSection"
                         aria-expanded="false" role="button">
                        <span>
                            <i class="bi bi-send text-info me-2"></i>
                            <span class="fw-semibold text-info" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Export Information</span>
                            <span class="badge bg-secondary-subtle text-secondary fw-normal ms-2" style="font-size:.65rem;text-transform:none;">Optional</span>
                        </span>
                        <i class="bi bi-chevron-down text-info collapse-chevron"></i>
                    </div>
                    <div class="collapse" id="outExportSection">
                        <div class="rounded-bottom p-3" style="border:1px solid #0ea5e9;border-top:none;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Loading Vessel</label>
                                    <input type="text" name="loading_vessel" class="form-control" placeholder="Loading vessel name">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Voyage No.</label>
                                    <input type="text" name="loading_voyage" class="form-control" placeholder="e.g. 002E">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Sailing Date</label>
                                    <input type="date" name="sailing_date" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Shipper</label>
                                    <input type="text" name="shipper" class="form-control" placeholder="Shipper name">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Release Order No.</label>
                                    <input type="text" name="release_order" class="form-control" placeholder="RO-XXXX">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Seal Number</label>
                                    <input type="text" name="seal_no" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>{{-- end Export Information wrapper --}}

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 3 — Transport Details (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#f3f4f6;border-left:3px solid #6b7280;">
                        <i class="bi bi-truck text-secondary"></i>
                        <span class="fw-semibold text-secondary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Transport Details</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Truck / Vehicle Plate <span class="text-danger">*</span></label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase" placeholder="e.g. JHQ 5678">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Transporter
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <select name="transporter_id" class="form-select s2-code" data-s2-sel="name">
                                <option value="">— Select Transporter —</option>
                                @foreach($transporters as $t)
                                <option value="{{ $t->id }}" data-code="{{ $t->code }}" data-name="{{ $t->name }}">
                                    {{ $t->code }} — {{ $t->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Driver Name <span class="text-danger">*</span></label>
                            <input type="text" name="driver_name" class="form-control" placeholder="Driver's name">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Driver IC/Passport <span class="text-danger">*</span></label>
                            <input type="text" name="driver_ic" class="form-control" placeholder="ID number">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">
                                Driver Phone
                                <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">Optional</span>
                            </label>
                            <input type="text" name="driver_phone" class="form-control" placeholder="+60 12-345 6789">
                        </div>
                    </div>

                    {{-- ═══════════════════════════════════════════════════════
                         SECTION 4 — Remarks & Date/Time (always visible)
                    ════════════════════════════════════════════════════════ --}}
                    <div class="gate-section-hdr mb-2" style="background:#f8f9fa;border-left:3px solid #9ca3af;">
                        <i class="bi bi-calendar-event text-secondary"></i>
                        <span class="fw-semibold text-secondary" style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;">Remarks &amp; Date / Time</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Any remarks…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate Out Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Auto
                                    </span>
                                @endif
                            </label>
                            <input type="text" name="gate_out_time" id="gateOutTime"
                                   class="form-control" autocomplete="off"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ── Photo Evidence ───────────────────────────────────── --}}
                    <div class="mt-1">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-camera me-1 text-success"></i>Photo Evidence
                            <span class="text-muted fw-normal small">(optional, max 5)</span>
                            <span id="outPhotoCounter" class="badge bg-secondary-subtle text-secondary ms-1">0 / 5</span>
                        </label>
                        <input type="file" id="outPhotoInput" multiple accept="image/*" class="d-none">
                        <input type="file" id="outCameraInput" accept="image/*" capture="environment" class="d-none">
                        <div id="outDropZone" class="border border-2 rounded-3 text-center p-3 mb-2"
                             style="border-color:#dee2e6!important;border-style:dashed!important;cursor:pointer;transition:background .2s;">
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="outBrowseBtn"><i class="bi bi-folder2-open me-1"></i>Browse</button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="outCameraBtn"><i class="bi bi-camera me-1"></i>Camera</button>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.72rem;">or drag &amp; drop images here &nbsp;·&nbsp; max 5 MB each</div>
                        </div>
                        <div id="outPhotoError" class="alert alert-danger py-1 small d-none mb-2"></div>
                        <div class="row g-1" id="outPhotoPreview"></div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-right me-2"></i>Record Gate Out
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Movements -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Recent Gate Movements</span>
                <span class="badge bg-primary rounded-pill">{{ $recentMovements->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height:680px;overflow-y:auto;">
                <div class="list-group list-group-flush">
                    @forelse($recentMovements as $mv)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-muted small" style="width:45px;">
                                {{ ($mv->gate_in_time ?? $mv->gate_out_time)?->format('H:i') }}
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="font-monospace fw-semibold small">{{ $mv->container_no }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary {{ in_array($mv->container_type, ['RF','RH']) ? 'badge-reefer' : '' }}" style="font-size:.65rem;">{{ $mv->size }}' {{ $mv->container_type }}</span>
                                    @if($mv->movement_type === 'in')
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem;"><i class="bi bi-arrow-down-circle"></i> In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success" style="font-size:.65rem;"><i class="bi bi-arrow-up-circle"></i> Out</span>
                                    @endif
                                    @if($mv->cargo_status)
                                    <span class="badge rounded-pill {{ strtolower($mv->cargo_status) === 'laden' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }}" style="font-size:.62rem;">
                                        {{ strtolower($mv->cargo_status) === 'laden' ? 'Laden' : 'Empty' }}
                                    </span>
                                    @endif
                                    @if($mv->location_zone)
                                        <span class="badge rounded-pill" style="font-size:.6rem;background:#e0e7ff;color:#3730a3;">
                                            {{ $mv->location_zone }}-{{ $mv->location_row }}{{ $mv->location_bay }}-T{{ $mv->location_tier }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $mv->customer?->name }} &nbsp;·&nbsp; {{ $mv->vehicle_plate }}
                                </div>
                            </div>
                            @if($mv->movement_type === 'out')
                            <a href="{{ route('yard.movements.gate-pass', $mv) }}" target="_blank"
                               class="btn btn-outline-success btn-sm py-0 px-1"
                               style="font-size:.65rem;" title="Gate Pass">
                                <i class="bi bi-printer"></i>
                            </a>
                            @elseif($mv->movement_type === 'in')
                            <a href="{{ route('yard.movements.gate-pass', $mv) }}" target="_blank"
                               class="btn btn-outline-primary btn-sm py-0 px-1"
                               style="font-size:.65rem;" title="Inward Gate Pass">
                                <i class="bi bi-printer"></i>
                            </a>
                            @endif
                            <a href="{{ route('yard.movements.edit', $mv) }}"
                               class="btn btn-outline-secondary btn-sm py-0 px-1"
                               style="font-size:.65rem;" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item text-center text-muted small py-4">No gate movements recorded yet.</div>
                    @endforelse
                </div>
            </div>
            @php $inCount = $recentMovements->where('movement_type','in')->count(); $outCount = $recentMovements->where('movement_type','out')->count(); @endphp
            <div class="card-footer bg-white">
                <div class="row text-center small">
                    <div class="col"><div class="text-muted">Gate-In</div><strong class="text-primary">{{ $inCount }}</strong></div>
                    <div class="col border-start border-end"><div class="text-muted">Gate-Out</div><strong class="text-success">{{ $outCount }}</strong></div>
                    <div class="col"><div class="text-muted">Total</div><strong>{{ $recentMovements->count() }}</strong></div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('styles')
<style>
/* ── Section header bands ──────────────────────────────────────────────────── */
.gate-section-hdr {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .45rem .75rem;
    border-radius: .375rem;
}
.gate-section-collapse {
    cursor: pointer;
    border-radius: .375rem .375rem 0 0 !important;
    user-select: none;
    justify-content: space-between;
}
.gate-section-collapse:hover { filter: brightness(.97); }
.collapse-chevron { font-size: .9rem; transition: transform .2s ease; }
.gate-section-collapse[aria-expanded="true"] .collapse-chevron { transform: rotate(180deg); }

/* ── Zone / slot picker ────────────────────────────────────────────────────── */
.zone-pick-btn { text-align:left; padding:.5rem .75rem; transition: all .15s; }
.zone-pick-btn:hover, .zone-pick-btn.active { background: #eff6ff; border-color: #3b82f6 !important; }
.zone-pick-btn.active { box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
.legend-dot { display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:3px; }
.slot-cell {
    display:inline-flex; flex-direction:column-reverse; align-items:center; gap:2px;
    vertical-align: bottom;
}
.tier-block {
    width:58px; height:22px; border-radius:4px; border:1.5px solid transparent;
    display:flex; align-items:center; justify-content:center;
    font-size:.58rem; font-weight:700; cursor:not-allowed; transition:.12s;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.tier-block.available {
    background:#dcfce7; border-color:#22c55e; color:#166534; cursor:pointer;
}
.tier-block.available:hover { background:#bbf7d0; transform:scale(1.05); }
.tier-block.selected { background:#3b82f6 !important; border-color:#1d4ed8 !important; color:#fff !important; cursor:pointer; }
.tier-block.occupied  { background:#fee2e2; border-color:#ef4444; color:#991b1b; }
.tier-block.reserved  { background:#fef9c3; border-color:#eab308; color:#92400e; }
.tier-block.damaged   { background:#fce7f3; border-color:#ec4899; color:#9d174d; }
.tier-block.in_repair { background:#fce7f3; border-color:#ec4899; color:#9d174d; }
.slot-grid-wrap { display:flex; flex-direction:column; gap:4px; }
.slot-grid-row  { display:flex; align-items:flex-end; gap:4px; }
.slot-row-label { width:28px; font-size:.7rem; font-weight:700; color:#6b7280; text-align:right; padding-right:4px; flex-shrink:0; }
.slot-bay-header { width:58px; text-align:center; font-size:.62rem; color:#9ca3af; font-weight:600; padding-bottom:3px; }
</style>
@endpush

@push('scripts')
<script>
// ── Toggle Gate In / Gate Out ───────────────────────────────────────────────
const btnIn  = document.getElementById('btnGateIn');
const btnOut = document.getElementById('btnGateOut');
const cardIn  = document.getElementById('gateInCard');
const cardOut = document.getElementById('gateOutCard');

btnIn.addEventListener('click', () => {
    cardIn.classList.remove('d-none'); cardOut.classList.add('d-none');
    btnIn.classList.replace('btn-outline-primary','btn-primary');
    btnOut.classList.replace('btn-success','btn-outline-success');
});
btnOut.addEventListener('click', () => {
    cardOut.classList.remove('d-none'); cardIn.classList.add('d-none');
    btnOut.classList.replace('btn-outline-success','btn-success');
    btnIn.classList.replace('btn-primary','btn-outline-primary');
});

// ── Restore active tab from URL ?tab= param ─────────────────────────────────
(function () {
    const p = new URLSearchParams(window.location.search);
    if (p.get('tab') === 'out') btnOut.click();
})();

// ── Initialize AirDatepicker on Gate In / Gate Out datetime inputs ───────────
// Runs synchronously — at this point (bottom of body) DOM is fully rendered
// and all CDN scripts (including AirDatepicker) are already loaded.
(function () {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var now = new Date();
    var nowStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
              + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

    ['gateInTime', 'gateOutTime'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;

        if (el.readOnly || typeof AirDatepicker === 'undefined') {
            // Non-admin or picker unavailable: at least show current time as text
            el.value = nowStr;
            return;
        }

        var dp = new AirDatepicker(el, {
            locale:            window.ADP_EN,
            timepicker:        true,
            autoClose:         false,
            dateFormat:        'yyyy-MM-dd',
            timeFormat:        'HH:mm',
            dateTimeSeparator: ' ',
            position:          'bottom left',
            container:         'body',
            onSelect: function () { el.dispatchEvent(new Event('change')); },
        });
        dp.selectDate(new Date());
        dp.setViewDate(new Date());
    });
})();

// ── Container number enforcer ───────────────────────────────────────────────
(function () {
    const inp = document.getElementById('containerNoIn');
    inp.addEventListener('keydown', function (e) {
        const ctrl = e.ctrlKey || e.metaKey;
        const nav  = ['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab','Enter'].includes(e.key);
        if (ctrl || nav) return;
        const pos = this.selectionStart, sel = this.selectionEnd;
        if (this.value.length >= 11 && pos === sel) { e.preventDefault(); return; }
        if (pos < 4) { if (!/^[A-Za-z]$/.test(e.key)) { e.preventDefault(); return; } }
        else         { if (!/^[0-9]$/.test(e.key))     { e.preventDefault(); return; } }
    });
    inp.addEventListener('input', function () {
        const raw = this.value.toUpperCase();
        let out = '', letters = 0, digits = 0;
        for (let i = 0; i < raw.length; i++) {
            if (letters < 4 && /[A-Z]/.test(raw[i])) { out += raw[i]; letters++; }
            else if (letters === 4 && digits < 7 && /[0-9]/.test(raw[i])) { out += raw[i]; digits++; }
            if (out.length >= 11) break;
        }
        this.value = out;
    });
})();

// ── Container Master lookup on Gate-In ──────────────────────────────────────
(function () {
    const inp     = document.getElementById('containerNoIn');
    const infoBox = document.getElementById('masterLookupInfo');
    const eqtSel  = document.getElementById('gateEqtSelect');
    let lastVal   = '';

    async function lookupMaster(val) {
        if (val.length !== 11 || val === lastVal) return;
        lastVal = val;
        try {
            const res  = await fetch('{{ route("containers.master-lookup") }}?container_no=' + encodeURIComponent(val));
            const data = await res.json();
            if (data.found) {
                // Block early if container is currently in yard
                if (data.status === 'in_yard') {
                    const since = data.gate_in_date ? ' since ' + data.gate_in_date : '';
                    infoBox.className = 'mt-1 small';
                    infoBox.innerHTML =
                        '<div class="alert alert-danger py-2 mb-0 small">' +
                        '<i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                        '<strong>Already in yard' + since + '.</strong> ' +
                        'Gate-Out must be completed before a new Gate-In.' +
                        '</div>';
                } else {
                    infoBox.className = 'mt-1 small text-success';
                    infoBox.innerHTML = '<i class="bi bi-check-circle me-1"></i>Found in Container Master — profile pre-filled.';
                }
                // Pre-select equipment type if available
                if (data.equipment_type_id) {
                    if (typeof $ !== 'undefined') {
                        $(eqtSel).val(String(data.equipment_type_id)).trigger('change');
                    } else {
                        eqtSel.value = String(data.equipment_type_id);
                        eqtSel.dispatchEvent(new Event('change'));
                    }
                }
                // Fill additional container details from master record
                window.additionalDetails?.fillFromMaster(data);
            } else {
                infoBox.className = 'mt-1 small text-muted';
                infoBox.innerHTML = '<i class="bi bi-info-circle me-1"></i>New container — a master record will be created automatically.';
            }
        } catch (e) {
            infoBox.className = 'd-none';
        }
    }

    inp.addEventListener('input', function () {
        if (this.value.length < 11) {
            infoBox.className = 'd-none';
            lastVal = '';
            // Reset equipment type so stale pre-fill from previous container doesn't persist
            if (typeof $ !== 'undefined') {
                $(eqtSel).val(null).trigger('change');
            } else {
                eqtSel.value = '';
                eqtSel.dispatchEvent(new Event('change'));
            }
            // Clear additional details filled by previous master lookup
            window.additionalDetails?.reset();
        }
    });
    inp.addEventListener('blur', function () { lookupMaster(this.value); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') lookupMaster(this.value); });
})();

// ── Additional Container Details — fill / conflict logic ────────────────────
(function () {
    let masterRecord = null; // stores last master lookup response

    const FIELD_IDS = [
        'add_tare_weight_kg', 'add_gross_weight_kg', 'add_max_payload_kg',
        'add_manufacture_year', 'add_manufacturer',
        'add_owner_code', 'add_owner_name',
        'add_csc_plate_no', 'add_csc_expiry_date',
    ];

    const collapseEl = document.getElementById('inAdditionalSection');
    const badgeEl    = document.getElementById('additionalPrefillBadge');

    function g(id)    { return document.getElementById(id); }
    function hint(id) { return document.getElementById('hint_' + id); }

    function expandSection() {
        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
    }

    function updateBadge(count, source) {
        if (!count) { badgeEl.classList.add('d-none'); return; }
        badgeEl.textContent = count + ' field' + (count > 1 ? 's' : '') + ' from ' + source;
        badgeEl.classList.remove('d-none');
    }

    function isEmpty(v) {
        return v === null || v === undefined || String(v).trim() === '';
    }

    function valEq(a, b) {
        const sa = String(a).trim(), sb = String(b).trim();
        if (sa.toUpperCase() === sb.toUpperCase()) return true;
        // Date strings (YYYY-MM-DD) must not be compared numerically —
        // parseFloat('2025-12-31') === 2025 which would wrongly equate different dates.
        if (/^\d{4}-\d{2}-\d{2}/.test(sa) || /^\d{4}-\d{2}-\d{2}/.test(sb)) return false;
        const fa = parseFloat(sa), fb = parseFloat(sb);
        return !isNaN(fa) && !isNaN(fb) && Math.abs(fa - fb) < 0.05;
    }

    function clearHint(id) { hint(id).innerHTML = ''; }

    function showMatchHint(id) {
        hint(id).innerHTML =
            '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Matches master record</span>';
    }

    function showConflictHint(id, masterVal) {
        const el = g(id);
        hint(id).innerHTML =
            '<span class="text-warning-emphasis">' +
            '<i class="bi bi-exclamation-triangle me-1"></i>' +
            'Master has <strong>' + masterVal + '</strong> — ' +
            '<button type="button" class="btn btn-link btn-sm p-0 fw-semibold text-warning-emphasis" ' +
            'style="font-size:.72rem;vertical-align:baseline;">' +
            'Use master</button></span>';
        hint(id).querySelector('button').addEventListener('click', function () {
            el.value = masterVal;
            el.dispatchEvent(new Event('input'));
            clearHint(id);
        });
    }

    // Core fill: applies a value to one field, choosing the correct state
    function applyField(id, newVal, masterVal) {
        const el = g(id);
        if (!el || isEmpty(newVal)) return false;
        if (isEmpty(masterVal)) {
            el.value = newVal; clearHint(id);           // State 1: master empty — silent
        } else if (valEq(newVal, masterVal)) {
            el.value = newVal; showMatchHint(id);        // State 2: agrees with master
        } else {
            el.value = newVal; showConflictHint(id, masterVal); // State 3: conflict
        }
        return true;
    }

    // ── Auto-calculate max payload ───────────────────────────────────────────
    function calcPayload() {
        const tare  = parseFloat(g('add_tare_weight_kg')?.value)  || 0;
        const gross = parseFloat(g('add_gross_weight_kg')?.value) || 0;
        const payEl = g('add_max_payload_kg');
        if (!payEl) return;
        if (tare > 0 && gross > tare) {
            payEl.value = Math.round(gross - tare);
        } else if (tare > 0 || gross > 0) {
            payEl.value = ''; // clear stale payload if weights are invalid/incomplete
        }
    }
    g('add_tare_weight_kg')?.addEventListener('input',  calcPayload);
    g('add_gross_weight_kg')?.addEventListener('input', calcPayload);

    // ── Auto-derive owner code from container number ─────────────────────────
    document.getElementById('containerNoIn')?.addEventListener('input', function () {
        const ownerEl = g('add_owner_code');
        if (!ownerEl || ownerEl.value) return; // only fill if empty
        if (this.value.length >= 3) ownerEl.value = this.value.substring(0, 3).toUpperCase();
    });

    // ── Public API ───────────────────────────────────────────────────────────
    window.additionalDetails = {

        fillFromMaster(data) {
            masterRecord = data;
            const map = {
                add_tare_weight_kg:   data.tare_weight_kg,
                add_gross_weight_kg:  data.gross_weight_kg,
                add_max_payload_kg:   data.max_payload_kg,
                add_manufacture_year: data.manufacture_year,
                add_manufacturer:     data.manufacturer,
                add_owner_code:       data.owner_code,
                add_owner_name:       data.owner_name,
                add_csc_plate_no:     data.csc_plate_no,
                add_csc_expiry_date:  data.csc_expiry_date,
            };
            let filled = 0;
            for (const [id, masterVal] of Object.entries(map)) {
                if (isEmpty(masterVal)) continue;
                const el = g(id);
                if (!el) continue;
                if (isEmpty(el.value)) {
                    // Field is currently empty — fill from master silently
                    el.value = masterVal; clearHint(id); filled++;
                } else if (valEq(el.value, masterVal)) {
                    // Field already has the same value (e.g. OCR matched) — show green
                    showMatchHint(id);
                } else {
                    // Field has a different value (OCR filled it differently) — conflict
                    showConflictHint(id, masterVal);
                }
            }
            calcPayload();
            if (filled > 0) { expandSection(); updateBadge(filled, 'master'); }
        },

        fillFromOcr(ocrData) {
            const map = {
                add_tare_weight_kg:  ocrData.tare_kg,
                add_gross_weight_kg: ocrData.max_gross_kg,
            };
            let filled = 0;
            for (const [id, ocrVal] of Object.entries(map)) {
                if (isEmpty(ocrVal)) continue;
                const masterVal = masterRecord
                    ? (id === 'add_tare_weight_kg' ? masterRecord.tare_weight_kg : masterRecord.gross_weight_kg)
                    : null;
                if (applyField(id, ocrVal, masterVal)) filled++;
            }
            calcPayload();
            if (filled > 0) { expandSection(); updateBadge(filled, 'OCR'); }
        },

        reset() {
            masterRecord = null;
            FIELD_IDS.forEach(id => {
                const el = g(id);
                if (el) el.value = '';
                clearHint(id);
            });
            badgeEl.classList.add('d-none');
        },
    };
})();

// ── Equipment Type badges ───────────────────────────────────────────────────
(function () {
    const sel = document.getElementById('gateEqtSelect');
    const sizeHid = document.getElementById('gateEqtSize'), typeHid = document.getElementById('gateEqtTypeCode');
    const sizeBadge = document.getElementById('gateEqtSizeBadge'), typeBadge = document.getElementById('gateEqtTypeBadge');
    function applyEqt(opt) {
        if (!opt || !opt.value) { sizeHid.value = ''; typeHid.value = ''; sizeBadge.classList.add('d-none'); typeBadge.classList.add('d-none'); return; }
        const isReefer = ['RF', 'RH'].includes(opt.dataset.type);
        sizeHid.value = opt.dataset.size; typeHid.value = opt.dataset.type;
        sizeBadge.textContent = opt.dataset.size + "'"; typeBadge.textContent = opt.dataset.type;
        typeBadge.className = 'badge text-nowrap' + (isReefer ? ' badge-reefer' : ' bg-info-subtle text-info');
        sizeBadge.classList.remove('d-none'); typeBadge.classList.remove('d-none');
    }
    sel.addEventListener('change', () => applyEqt(sel.selectedOptions[0]));
    if (sel.value) applyEqt(sel.selectedOptions[0]);
})();

// ── Storage Location Slot Picker ─────────────────────────────────────────────
(function () {
    const zoneInput       = document.getElementById('loc_zone');
    const rowInput        = document.getElementById('loc_row');
    const bayInput        = document.getElementById('loc_bay');
    const tierInput       = document.getElementById('loc_tier');
    const selectedDisplay = document.getElementById('selectedSlotDisplay');
    const selectedCode    = document.getElementById('selectedSlotCode');
    const clearBtn        = document.getElementById('clearSlotBtn');
    const zoneSelectorPanel = document.getElementById('zoneSelectorPanel');
    const slotGridPanel   = document.getElementById('slotGridPanel');
    const slotGridContent = document.getElementById('slotGridContent');
    const slotGridLoading = document.getElementById('slotGridLoading');
    const gridZoneLabel   = document.getElementById('gridZoneLabel');
    const backBtn         = document.getElementById('backToZonesBtn');

    let currentZone = null;
    let currentSelectedEl = null;

    const statusClass = {
        empty: 'available', occupied: 'occupied', reserved: 'reserved',
        damaged: 'damaged', in_repair: 'in_repair',
    };

    function showZoneSelector() {
        zoneSelectorPanel.classList.remove('d-none');
        slotGridPanel.classList.add('d-none');
        document.querySelectorAll('.zone-pick-btn').forEach(b => b.classList.remove('active'));
    }

    function clearSelection() {
        zoneInput.value = ''; rowInput.value = ''; bayInput.value = ''; tierInput.value = '';
        selectedDisplay.classList.add('d-none');
        showZoneSelector();
        currentSelectedEl = null;
    }

    clearBtn.addEventListener('click', clearSelection);
    backBtn.addEventListener('click', () => { showZoneSelector(); });

    // Zone card click — load slot grid
    document.querySelectorAll('.zone-pick-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.zone-pick-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadZoneGrid(this.dataset.zone, this.dataset.name);
        });
    });

    async function loadZoneGrid(zoneCode, zoneName) {
        currentZone = zoneCode;
        slotGridContent.innerHTML = '';
        slotGridLoading.classList.remove('d-none');
        zoneSelectorPanel.classList.add('d-none');
        slotGridPanel.classList.remove('d-none');
        gridZoneLabel.textContent = '— ' + zoneName;

        try {
            const url = '{{ rtrim(url("/yard/zones"), "/") }}/' + encodeURIComponent(zoneCode) + '/slots';
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            renderGrid(data.slots);
        } catch (e) {
            slotGridContent.innerHTML = '<div class="alert alert-danger small py-2"><i class="bi bi-wifi-off me-1"></i>Failed to load slots. Please try again.</div>';
        } finally {
            slotGridLoading.classList.add('d-none');
        }
    }

    function renderGrid(slots) {
        if (!slots || slots.length === 0) {
            slotGridContent.innerHTML = '<div class="text-muted small py-2"><i class="bi bi-info-circle me-1"></i>No slots configured in this zone.</div>';
            return;
        }

        // Build row × bay → [tiers] map
        const grid = {}, rows = new Set(), bays = new Set();
        slots.forEach(s => {
            rows.add(s.row); bays.add(s.bay);
            if (!grid[s.row]) grid[s.row] = {};
            if (!grid[s.row][s.bay]) grid[s.row][s.bay] = [];
            grid[s.row][s.bay].push(s);
        });

        const sortedRows = [...rows].sort();
        const sortedBays = [...bays].sort((a, b) => a - b);

        let html = '<div class="slot-grid-wrap">';

        // Bay header row
        html += '<div class="slot-grid-row"><div class="slot-row-label"></div>';
        sortedBays.forEach(bay => { html += `<div class="slot-bay-header">Bay ${bay}</div>`; });
        html += '</div>';

        // Data rows
        sortedRows.forEach(row => {
            html += `<div class="slot-grid-row"><div class="slot-row-label">${row}</div>`;
            sortedBays.forEach(bay => {
                const tiers = (grid[row]?.[bay] || []).sort((a, b) => a.tier - b.tier);
                html += '<div class="slot-cell">';
                tiers.forEach(s => {
                    const cls = statusClass[s.status] || 'occupied';
                    const tooltip  = s.status === 'empty'
                        ? `${s.full_code} — Available`
                        : `${s.full_code} — ${s.container_no || s.status}`;
                    const label = s.status === 'empty'
                        ? `T${s.tier}`
                        : `<span style="font-size:.5rem;display:block;">${(s.container_no||'').substring(0,4)}</span>T${s.tier}`;
                    html += `<div class="tier-block ${cls}"
                                  data-zone="${s.zone}" data-row="${s.row}" data-bay="${s.bay}" data-tier="${s.tier}"
                                  data-code="${s.full_code}" data-status="${s.status}"
                                  title="${tooltip}">${label}</div>`;
                });
                if (tiers.length === 0) { html += '<div style="width:58px;height:22px;"></div>'; }
                html += '</div>';
            });
            html += '</div>';
        });

        html += '</div>';
        slotGridContent.innerHTML = html;

        // Click handler for available slots
        slotGridContent.querySelectorAll('.tier-block.available').forEach(el => {
            el.addEventListener('click', function () {
                // Deselect previous
                if (currentSelectedEl) currentSelectedEl.classList.remove('selected');
                this.classList.add('selected');
                currentSelectedEl = this;

                zoneInput.value = this.dataset.zone;
                rowInput.value  = this.dataset.row;
                bayInput.value  = this.dataset.bay;
                tierInput.value = this.dataset.tier;

                selectedCode.textContent = this.dataset.code;
                selectedDisplay.classList.remove('d-none');

                // Scroll back to show the selected display
                selectedDisplay.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        // Init tooltips on tier blocks
        slotGridContent.querySelectorAll('[title]').forEach(el => {
            if (typeof bootstrap !== 'undefined') new bootstrap.Tooltip(el, { trigger: 'hover' });
        });
    }
})();

// ── Photo uploader ──────────────────────────────────────────────────────────
function initPhotoUploader(cfg) {
    const MAX = cfg.max || 5, MAX_BYTES = 5 * 1024 * 1024;
    let files = [];

    function isImage(f) {
        return /^image\//.test((f.type||'').toLowerCase()) || /\.(jpe?g|png|webp|gif|bmp)$/i.test(f.name||'');
    }
    function updateCounter() {
        const n = files.length;
        cfg.counterEl.textContent = n + ' / ' + MAX;
        cfg.counterEl.className = n >= MAX ? 'badge bg-warning-subtle text-warning ms-1' : 'badge bg-secondary-subtle text-secondary ms-1';
    }
    function showError(msg) { cfg.errorEl.textContent = msg; cfg.errorEl.classList.remove('d-none'); setTimeout(() => cfg.errorEl.classList.add('d-none'), 4000); }
    function renderPreviews() {
        cfg.previewGrid.innerHTML = '';
        files.forEach(function (file, idx) {
            const col = document.createElement('div'); col.className = 'col-4 col-md-3';
            const reader = new FileReader();
            reader.onload = e => {
                col.innerHTML = '<div class="position-relative" style="border-radius:6px;overflow:hidden;"><img src="' + e.target.result + '" style="width:100%;height:70px;object-fit:cover;" alt=""><button type="button" class="btn btn-danger btn-sm rm-photo position-absolute" data-idx="' + idx + '" style="top:2px;right:2px;padding:1px 5px;font-size:.7rem;border-radius:50%;"><i class="bi bi-x"></i></button></div>';
                cfg.previewGrid.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
        updateCounter();
    }
    function addFiles(incoming) {
        Array.from(incoming).forEach(file => {
            if (!isImage(file))        { showError('"' + file.name + '" is not a supported image.'); return; }
            if (file.size > MAX_BYTES) { showError('"' + file.name + '" exceeds 5 MB.'); return; }
            if (files.length >= MAX)   { showError('Maximum ' + MAX + ' photos allowed.'); return; }
            if (!files.some(f => f.name === file.name && f.size === file.size)) files.push(file);
        });
        renderPreviews();
    }
    cfg.previewGrid.addEventListener('click', e => { const b = e.target.closest('.rm-photo'); if (!b) return; files.splice(parseInt(b.dataset.idx,10),1); renderPreviews(); });
    cfg.browseBtn.addEventListener('click', e => { e.stopPropagation(); cfg.fileInput.click(); });
    cfg.dropZone.addEventListener('click', () => cfg.fileInput.click());
    cfg.cameraBtn.addEventListener('click', e => { e.stopPropagation(); cfg.cameraInput.click(); });
    cfg.fileInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });
    cfg.cameraInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });
    cfg.dropZone.addEventListener('dragover',  e => { e.preventDefault(); cfg.dropZone.style.background = '#e8f0fe'; });
    cfg.dropZone.addEventListener('dragleave', () => { cfg.dropZone.style.background = ''; });
    cfg.dropZone.addEventListener('drop', e => { e.preventDefault(); cfg.dropZone.style.background = ''; addFiles(e.dataTransfer.files); });

    const form = cfg.fileInput.closest('form'), submitBtn = form.querySelector('[type="submit"]'), origHtml = submitBtn.innerHTML;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        const fd = new FormData(form);
        files.forEach(file => fd.append('photos[]', file));
        fetch(form.action, { method: 'POST', body: fd, redirect: 'manual' })
            .then(() => { window.location.href = cfg.redirectUrl || window.location.href; })
            .catch(() => { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; });
    });
}

initPhotoUploader({ fileInput: document.getElementById('inPhotoInput'), cameraInput: document.getElementById('inCameraInput'), browseBtn: document.getElementById('inBrowseBtn'), cameraBtn: document.getElementById('inCameraBtn'), dropZone: document.getElementById('inDropZone'), errorEl: document.getElementById('inPhotoError'), previewGrid: document.getElementById('inPhotoPreview'), counterEl: document.getElementById('inPhotoCounter'), redirectUrl: '{{ route("yard.gate") }}?tab=in', max: 5 });
initPhotoUploader({ fileInput: document.getElementById('outPhotoInput'), cameraInput: document.getElementById('outCameraInput'), browseBtn: document.getElementById('outBrowseBtn'), cameraBtn: document.getElementById('outCameraBtn'), dropZone: document.getElementById('outDropZone'), errorEl: document.getElementById('outPhotoError'), previewGrid: document.getElementById('outPhotoPreview'), counterEl: document.getElementById('outPhotoCounter'), redirectUrl: '{{ route("yard.gate") }}?tab=out', max: 5 });

// ── Gate Out container AJAX lookup ──────────────────────────────────────────
(function () {
    const inp = document.getElementById('containerSearch'), searchBtn = document.getElementById('containerSearchBtn');
    const infoBox = document.getElementById('containerInfoBox');
    let lookupDone = false;

    function setInfoBox(type, html) {
        infoBox.className = 'mb-3 alert alert-' + type + ' small p-2';
        infoBox.innerHTML = html;
        infoBox.classList.remove('d-none');
    }

    async function doLookup() {
        const val = inp.value.trim().toUpperCase();
        if (val.length < 11) { setInfoBox('warning', '<i class="bi bi-exclamation-triangle me-1"></i>Enter a full 11-character container number first.'); return; }
        searchBtn.disabled = true; searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const res = await fetch('{{ route("yard.container-lookup") }}?container_no=' + encodeURIComponent(val));
            const data = await res.json();
            if (!data.found) {
                lookupDone = false;
                setInfoBox('danger', '<i class="bi bi-x-circle me-1"></i><strong>Not found:</strong> ' + (data.message || 'Container not in yard.'));
            } else {
                lookupDone = true;
                const condMap  = { sound:'Sound', damaged:'Damaged', require_repair:'Requires Repair' };
                const cargoMap = { empty:'Empty', laden:'Laden', full:'Laden' };
                const daysBadge = data.days_in_yard !== null ? '<span class="badge bg-warning-subtle text-warning border ms-1">' + data.days_in_yard + ' day(s) in yard</span>' : '';
                setInfoBox('success',
                    '<div class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-success fs-5"></i><strong class="font-monospace fs-6">' + data.container_no + '</strong>' + daysBadge + '</div>' +
                    '<div class="row g-1 small">' +
                        '<div class="col-6"><span class="text-muted">Equipment:</span> ' + data.equipment_label + '</div>' +
                        '<div class="col-6"><span class="text-muted">Customer:</span> ' + data.customer + '</div>' +
                        '<div class="col-6"><span class="text-muted">Condition:</span> ' + (condMap[data.condition]||data.condition) + '</div>' +
                        '<div class="col-6"><span class="text-muted">Cargo:</span> ' + (cargoMap[data.cargo_status]||data.cargo_status) + '</div>' +
                        '<div class="col-6"><span class="text-muted">Location:</span> <strong class="font-monospace">' + (data.location||'—') + '</strong></div>' +
                        '<div class="col-6"><span class="text-muted">Gate In:</span> ' + (data.gate_in_time||data.gate_in_date||'—') + '</div>' +
                    '</div>'
                );
            }
        } catch (e) {
            lookupDone = false;
            setInfoBox('danger', '<i class="bi bi-wifi-off me-1"></i>Network error. Please try again.');
        } finally {
            searchBtn.disabled = false; searchBtn.innerHTML = '<i class="bi bi-search"></i>';
        }
    }

    searchBtn.addEventListener('click', doLookup);
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doLookup(); } });
    inp.addEventListener('input', function () {
        if (lookupDone) { lookupDone = false; setInfoBox('info', '<i class="bi bi-info-circle me-1"></i>Container changed — search again to verify.'); }
    });
    document.getElementById('gateOutForm').addEventListener('submit', function (e) {
        if (!lookupDone) { e.preventDefault(); setInfoBox('warning', '<i class="bi bi-exclamation-triangle me-1"></i>Please search and confirm the container is in yard.'); inp.focus(); }
    }, true);
})();

// ── Container OCR Scan ───────────────────────────────────────────────────────
(function () {
    const OCR_URL = '{{ route("yard.ocr-scan") }}';
    const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function setSpinner(iconEl, btnEl, on) {
        if (on) {
            iconEl.className = 'spinner-border spinner-border-sm';
            btnEl.disabled   = true;
        } else {
            iconEl.className = 'bi bi-camera';
            btnEl.disabled   = false;
        }
    }

    function showOcrResult(resultEl, type, html) {
        resultEl.className = 'mt-1 small alert alert-' + type + ' py-2 px-3';
        resultEl.innerHTML = html;
        resultEl.classList.remove('d-none');
    }

    function hideOcrResult(resultEl) {
        resultEl.className = 'd-none';
        resultEl.innerHTML = '';
    }

    // Set a <select> value and notify Select2 + plain listeners.
    // Always use jQuery's .val().trigger('change') when jQuery is available —
    // native dispatchEvent is NOT caught by jQuery's Select2 handlers.
    // No need to check data('select2') — jQuery trigger works for plain selects too.
    function preselectEqt(selectEl, equipmentTypeId) {
        if (!equipmentTypeId || !selectEl) return;
        const id = String(equipmentTypeId);
        if (typeof $ !== 'undefined') {
            $(selectEl).val(id).trigger('change');
        } else {
            selectEl.value = id;
            selectEl.dispatchEvent(new Event('change'));
        }
    }

    // Find the <option> value whose data-iso attribute matches an ISO 6346 size-type code.
    // Client-side match avoids a round-trip and is not affected by server-side lookup failures.
    function findEqtByIso(selectEl, isoCode) {
        if (!isoCode || !selectEl) return null;
        const upper = isoCode.toUpperCase();
        for (const opt of selectEl.options) {
            if (opt.dataset.iso && opt.dataset.iso.toUpperCase() === upper) return opt.value;
        }
        return null;
    }

    async function processImage(file, mode) {
        const isIn      = mode === 'in';
        const iconEl    = document.getElementById(isIn ? 'ocrIconIn'    : 'ocrIconOut');
        const btnEl     = document.getElementById(isIn ? 'ocrBtnIn'     : 'ocrBtnOut');
        const resultEl  = document.getElementById(isIn ? 'ocrResultIn'  : 'ocrResultOut');
        const infoEl    = isIn ? document.getElementById('masterLookupInfo') : null;
        const containerInp = document.getElementById(isIn ? 'containerNoIn' : 'containerSearch');

        setSpinner(iconEl, btnEl, true);
        hideOcrResult(resultEl);

        const fd = new FormData();
        fd.append('image', file);
        fd.append('_token', CSRF);

        try {
            const res  = await fetch(OCR_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success || !data.container_no) {
                showOcrResult(resultEl, 'warning',
                    '<i class="bi bi-exclamation-triangle me-1"></i>' +
                    (data.message || 'Could not read container number. Please enter manually.') +
                    (data.raw_text ? ' <small class="text-muted d-block mt-1">Raw OCR: ' + data.raw_text.substring(0, 80) + '</small>' : '')
                );
                return;
            }

            // Fill container number field
            containerInp.value = data.container_no;
            containerInp.dispatchEvent(new Event('input'));

            // Show amber "Please verify" badge when OCR couldn't confirm the check digit
            let containerLabel = '<strong class="font-monospace">' + data.container_no + '</strong>';
            if (data.check_digit_valid === false) {
                containerLabel += ' <span class="badge ms-1" style="background:#fef3c7;color:#92400e;border:1px solid #fbbf24;font-size:.68rem;">' +
                    '<i class="bi bi-exclamation-triangle-fill me-1"></i>Please verify number</span>';
            }
            let resultHtml = '<i class="bi bi-check-circle-fill text-success me-1"></i>' +
                containerLabel + ' extracted from image.';

            // Resolve equipment type to pre-select (needed for banner too)
            const eqtSel   = document.getElementById('gateEqtSelect');
            let   eqtToSet = null;
            if (data.master?.equipment_type_id) {
                eqtToSet = String(data.master.equipment_type_id);
            } else if (data.iso_type) {
                eqtToSet = findEqtByIso(eqtSel, data.iso_type) || (data.equipment_match?.id ? String(data.equipment_match.id) : null);
            }
            // Resolve the eqt code label for display
            let eqtCodeLabel = null;
            if (eqtToSet) {
                for (const opt of eqtSel.options) {
                    if (String(opt.value) === eqtToSet) { eqtCodeLabel = opt.dataset.code || opt.text; break; }
                }
            }

            // Append OCR extra data if found
            const extras = [];
            if (data.iso_type) {
                const eqtLabel = eqtCodeLabel
                    ? data.iso_type + ' → <strong>' + eqtCodeLabel + '</strong>'
                    : data.iso_type + ' <small class="text-muted">(no equipment match)</small>';
                extras.push('ISO: ' + eqtLabel);
            }
            if (data.tare_kg)      extras.push('Tare: <strong>' + data.tare_kg.toLocaleString() + ' kg</strong>');
            if (data.max_gross_kg) extras.push('Max Gross: <strong>' + data.max_gross_kg.toLocaleString() + ' kg</strong>');
            if (extras.length) resultHtml += ' <span class="text-muted">|</span> ' + extras.join(' &nbsp; ');

            // ── OCR diagnostic details (collapsible, system admins only) ─────────
            @if(auth()->user()->isSystemAdmin())
            (function() {
                function cell(label, value) {
                    return '<tr><td class="text-muted text-nowrap pe-3" style="vertical-align:top;width:110px;">' + label + '</td><td style="word-break:break-word;">' + value + '</td></tr>';
                }
                const isoRow = data.iso_type
                    ? (eqtCodeLabel
                        ? data.iso_type + ' <span class="text-success">→ ' + eqtCodeLabel + '</span>'
                        : data.iso_type + ' <span class="text-danger">(no match in list)</span>')
                    : '<span class="text-muted">not detected</span>';
                const cdRow  = data.check_digit_valid
                    ? '<span class="text-success">valid ✓</span>'
                    : '<span class="text-warning">not verified ⚠</span>';
                const safeRaw = (data.raw_text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                const tbl =
                    cell('Container', '<code>' + (data.container_no||'—') + '</code> ' + cdRow) +
                    cell('ISO type', isoRow) +
                    cell('Tare', data.tare_kg ? data.tare_kg.toLocaleString() + ' kg' : '<span class="text-muted">not detected</span>') +
                    cell('Max gross', data.max_gross_kg ? data.max_gross_kg.toLocaleString() + ' kg' : '<span class="text-muted">not detected</span>') +
                    cell('Master EQT', data.master?.equipment_type_id ? 'id=' + data.master.equipment_type_id : '<span class="text-muted">none</span>') +
                    cell('Raw OCR', '<pre style="margin:0;font-size:.62rem;white-space:pre-wrap;word-break:break-all;max-height:130px;overflow-y:auto;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;padding:4px;">' + safeRaw + '</pre>');
                resultHtml +=
                    '<details class="mt-2 pt-1 border-top">' +
                    '<summary style="cursor:pointer;font-size:.72rem;color:#6c757d;user-select:none;">OCR scan details</summary>' +
                    '<table class="mt-1 mb-0" style="font-size:.72rem;border-collapse:collapse;width:100%;">' + tbl + '</table>' +
                    '</details>';
            })();
            @endif

            if (isIn) {
                // Gate-In specific actions
                if (data.in_yard) {
                    // Already in yard — show warning
                    showOcrResult(resultEl, 'danger',
                        '<i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                        '<strong>' + data.container_no + ' is already in yard' +
                        (data.in_yard_since ? ' since ' + data.in_yard_since : '') + '.</strong> ' +
                        'Gate-Out must be completed before a new Gate-In.'
                    );
                } else {
                    showOcrResult(resultEl, 'success', resultHtml);
                    // Fill additional details from OCR tare/gross data only for valid gate-in
                    window.additionalDetails?.fillFromOcr(data);
                }

                // Pre-fill equipment type
                if (eqtToSet) {
                    preselectEqt(eqtSel, eqtToSet);
                }

                // Only trigger master lookup when not already in-yard — if it IS in-yard
                // the warning is already shown above; firing blur would duplicate it.
                if (!data.in_yard) {
                    containerInp.dispatchEvent(new Event('blur'));
                }

            } else {
                // Gate-Out: just show result and trigger search
                showOcrResult(resultEl, 'success', resultHtml);
                // Trigger the container-lookup search automatically
                document.getElementById('containerSearchBtn').click();
            }

        } catch (err) {
            showOcrResult(resultEl, 'danger',
                '<i class="bi bi-wifi-off me-1"></i>OCR request failed. Please try again or enter manually.'
            );
        } finally {
            setSpinner(iconEl, btnEl, false);
        }
    }

    // ── Wire up Gate-In OCR button ────────────────────────────────────────────
    const ocrBtnIn  = document.getElementById('ocrBtnIn');
    const ocrInputIn = document.getElementById('ocrInputIn');

    ocrBtnIn.addEventListener('click', () => ocrInputIn.click());
    ocrInputIn.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            processImage(this.files[0], 'in');
            this.value = ''; // Reset so same image can be re-scanned
        }
    });

    // Hide OCR result when user manually edits the container number
    document.getElementById('containerNoIn').addEventListener('input', function () {
        hideOcrResult(document.getElementById('ocrResultIn'));
    });

    // ── Wire up Gate-Out OCR button ───────────────────────────────────────────
    const ocrBtnOut   = document.getElementById('ocrBtnOut');
    const ocrInputOut = document.getElementById('ocrInputOut');

    ocrBtnOut.addEventListener('click', () => ocrInputOut.click());
    ocrInputOut.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            processImage(this.files[0], 'out');
            this.value = '';
        }
    });

    document.getElementById('containerSearch').addEventListener('input', function () {
        hideOcrResult(document.getElementById('ocrResultOut'));
    });

})();
</script>
@endpush
