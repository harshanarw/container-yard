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

                    {{-- Container Number --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" id="containerNoIn"
                                   class="form-control font-monospace text-uppercase"
                                   placeholder="XXXX0000000" required autocomplete="off" maxlength="11">
                            <button type="button" class="btn btn-outline-secondary" id="scanBtn" title="Scan">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                        <div id="masterLookupInfo" class="mt-1 small d-none"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="gateEqtSelect" class="form-select" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}" data-size="{{ $eqt->size }}" data-type="{{ $eqt->type_code }}" data-eqt="{{ $eqt->eqt_code }}">
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
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound">Sound</option>
                                <option value="damaged">Damaged</option>
                                <option value="require_repair">Requires Repair</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty / Full</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty">Empty</option>
                                <option value="full">Full</option>
                            </select>
                        </div>

                        {{-- ── Storage Location ──────────────────────────────── --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt me-1 text-primary"></i>Storage Location
                                <span class="text-danger">*</span>
                            </label>

                            {{-- Hidden submission fields --}}
                            <input type="hidden" name="location_zone" id="loc_zone" required>
                            <input type="hidden" name="location_row"  id="loc_row"  required>
                            <input type="hidden" name="location_bay"  id="loc_bay"  required>
                            <input type="hidden" name="location_tier" id="loc_tier" required>

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
                                    Select a storage zone:
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
                        {{-- ── End Storage Location ──────────────────────────── --}}

                        <div class="col-6">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase" placeholder="e.g. WQR 1234">
                        </div>
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
                            <input type="datetime-local" name="gate_in_time" id="gateInTime"
                                   class="form-control"
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Photo Evidence -->
                    <div class="mt-3">
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

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" class="form-control font-monospace text-uppercase"
                                   placeholder="XXXX0000000" required id="containerSearch" maxlength="11" autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" id="containerSearchBtn"><i class="bi bi-search"></i></button>
                        </div>
                        <div class="form-text text-muted" style="font-size:.72rem;">Enter and search to confirm the container is in yard.</div>
                    </div>

                    <div id="containerInfoBox" class="mb-3 d-none"></div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Release Order No.</label>
                            <input type="text" name="release_order" class="form-control" placeholder="RO-XXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase" placeholder="e.g. JHQ 5678">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control" placeholder="Driver's name">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver IC/Passport</label>
                            <input type="text" name="driver_ic" class="form-control" placeholder="ID number">
                        </div>
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
                            <input type="datetime-local" name="gate_out_time" id="gateOutTime"
                                   class="form-control"
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Photo Evidence -->
                    <div class="mt-3">
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
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem;">{{ $mv->size }}' {{ $mv->container_type }}</span>
                                    @if($mv->movement_type === 'in')
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem;"><i class="bi bi-arrow-down-circle"></i> In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success" style="font-size:.65rem;"><i class="bi bi-arrow-up-circle"></i> Out</span>
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
                infoBox.className = 'mt-1 small text-success';
                infoBox.innerHTML = '<i class="bi bi-check-circle me-1"></i>Found in Container Master — profile pre-filled.';
                // Pre-select equipment type if available
                if (data.equipment_type_id) {
                    for (const opt of eqtSel.options) {
                        if (opt.value == data.equipment_type_id) {
                            eqtSel.value = data.equipment_type_id;
                            eqtSel.dispatchEvent(new Event('change'));
                            break;
                        }
                    }
                }
            } else {
                infoBox.className = 'mt-1 small text-muted';
                infoBox.innerHTML = '<i class="bi bi-info-circle me-1"></i>New container — a master record will be created automatically.';
            }
        } catch (e) {
            infoBox.className = 'd-none';
        }
    }

    inp.addEventListener('input', function () {
        if (this.value.length < 11) { infoBox.className = 'd-none'; lastVal = ''; }
    });
    inp.addEventListener('blur', function () { lookupMaster(this.value); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') lookupMaster(this.value); });
})();

// ── Equipment Type badges ───────────────────────────────────────────────────
(function () {
    const sel = document.getElementById('gateEqtSelect');
    const sizeHid = document.getElementById('gateEqtSize'), typeHid = document.getElementById('gateEqtTypeCode');
    const sizeBadge = document.getElementById('gateEqtSizeBadge'), typeBadge = document.getElementById('gateEqtTypeBadge');
    function applyEqt(opt) {
        if (!opt || !opt.value) { sizeHid.value = ''; typeHid.value = ''; sizeBadge.classList.add('d-none'); typeBadge.classList.add('d-none'); return; }
        sizeHid.value = opt.dataset.size; typeHid.value = opt.dataset.type;
        sizeBadge.textContent = opt.dataset.size + "'"; typeBadge.textContent = opt.dataset.type;
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
                    const clickable = s.status === 'empty' ? 'available' : '';
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
            .then(() => window.location.reload())
            .catch(() => { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; });
    });
}

initPhotoUploader({ fileInput: document.getElementById('inPhotoInput'), cameraInput: document.getElementById('inCameraInput'), browseBtn: document.getElementById('inBrowseBtn'), cameraBtn: document.getElementById('inCameraBtn'), dropZone: document.getElementById('inDropZone'), errorEl: document.getElementById('inPhotoError'), previewGrid: document.getElementById('inPhotoPreview'), counterEl: document.getElementById('inPhotoCounter'), max: 5 });
initPhotoUploader({ fileInput: document.getElementById('outPhotoInput'), cameraInput: document.getElementById('outCameraInput'), browseBtn: document.getElementById('outBrowseBtn'), cameraBtn: document.getElementById('outCameraBtn'), dropZone: document.getElementById('outDropZone'), errorEl: document.getElementById('outPhotoError'), previewGrid: document.getElementById('outPhotoPreview'), counterEl: document.getElementById('outPhotoCounter'), max: 5 });

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
                const cargoMap = { empty:'Empty', full:'Full' };
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
</script>
@endpush
