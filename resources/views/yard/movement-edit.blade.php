@extends('layouts.app')

@section('title', 'Edit Gate Movement #' . $movement->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.gate') }}">Gate In / Gate Out</a></li>
    <li class="breadcrumb-item active">Edit Movement #{{ $movement->id }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            @if($movement->movement_type === 'in')
                <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>
            @else
                <i class="bi bi-box-arrow-right me-2 text-success"></i>
            @endif
            Edit Gate {{ ucfirst($movement->movement_type) }} &mdash;
            <span class="font-monospace">{{ $movement->container_no }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            Movement #{{ $movement->id }} &nbsp;·&nbsp;
            Recorded {{ ($movement->gate_in_time ?? $movement->gate_out_time)?->format('d M Y H:i') }}
            &nbsp;·&nbsp; By {{ $movement->createdBy?->name ?? '—' }}
        </p>
    </div>
    <a href="{{ route('yard.gate') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header {{ $movement->movement_type === 'in' ? 'bg-primary' : 'bg-success' }} text-white">
                <i class="bi bi-pencil-square me-2"></i>Movement Details
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.movements.update', $movement) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    {{-- Container info (read-only) --}}
                    <div class="mb-3 p-3 bg-light rounded-3 small">
                        <div class="row g-1">
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Container</div>
                                <div class="fw-semibold font-monospace">{{ $movement->container_no }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Size / Type</div>
                                <div class="fw-semibold">{{ $movement->size }}' {{ $movement->container_type }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Customer</div>
                                <div class="fw-semibold">{{ $movement->customer?->name ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Location</div>
                                <div class="fw-semibold font-monospace">
                                    @if($movement->location_row)
                                        @if($movement->location_zone){{ $movement->location_zone }}-@endif{{ $movement->location_row }}{{ $movement->location_bay }}-T{{ $movement->location_tier }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger small py-2">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">

                        {{-- Gate In specific fields --}}
                        @if($movement->movement_type === 'in')

                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner</label>
                            <select name="customer_id" class="form-select s2-code" data-s2-sel="name">
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    data-code="{{ $customer->code }}" data-name="{{ $customer->name }}"
                                    {{ $movement->customer_id == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->code }} — {{ $customer->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound"          {{ $movement->condition === 'sound'          ? 'selected' : '' }}>Sound</option>
                                <option value="damaged"        {{ $movement->condition === 'damaged'        ? 'selected' : '' }}>Damaged</option>
                                <option value="require_repair" {{ $movement->condition === 'require_repair' ? 'selected' : '' }}>Requires Repair</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty / Laden</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty"  {{ $movement->cargo_status === 'empty'  ? 'selected' : '' }}>Empty</option>
                                <option value="laden"  {{ in_array($movement->cargo_status, ['laden','full']) ? 'selected' : '' }}>Laden</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control"
                                   value="{{ old('seal_no', $movement->seal_no) }}" placeholder="Optional">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   value="{{ old('vehicle_plate', $movement->vehicle_plate) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate In Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Admin only
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_in_time"
                                   class="form-control"
                                   value="{{ old('gate_in_time', $movement->gate_in_time?->format('Y-m-d\TH:i')) }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                        </div>

                        {{-- ── Storage Location ──────────────────────────────── --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-geo-alt me-1 text-primary"></i>Storage Location
                            </label>

                            {{-- Hidden submission fields (pre-filled with current values) --}}
                            <input type="hidden" name="location_zone" id="edit_loc_zone"
                                   value="{{ old('location_zone', $movement->location_zone) }}">
                            <input type="hidden" name="location_row"  id="edit_loc_row"
                                   value="{{ old('location_row',  $movement->location_row) }}">
                            <input type="hidden" name="location_bay"  id="edit_loc_bay"
                                   value="{{ old('location_bay',  $movement->location_bay) }}">
                            <input type="hidden" name="location_tier" id="edit_loc_tier"
                                   value="{{ old('location_tier', $movement->location_tier) }}">

                            {{-- Current / selected slot display --}}
                            <div id="editSelectedSlotDisplay"
                                 class="alert py-2 small mb-2 {{ ($movement->location_row || old('location_zone')) ? 'alert-primary' : 'd-none' }}">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Slot:
                                <strong id="editSelectedSlotCode" class="font-monospace">
                                    @php
                                        $loc_zone = old('location_zone', $movement->location_zone);
                                        $loc_row  = old('location_row',  $movement->location_row);
                                        $loc_bay  = old('location_bay',  $movement->location_bay);
                                        $loc_tier = old('location_tier', $movement->location_tier);
                                    @endphp
                                    @if($loc_zone && $loc_row && $loc_bay && $loc_tier)
                                        {{ $loc_zone }}-{{ $loc_row }}{{ $loc_bay }}-T{{ $loc_tier }}
                                    @endif
                                </strong>
                                <button type="button" class="btn btn-sm btn-link py-0 ps-2 text-primary"
                                        id="editChangeSlotBtn">
                                    <i class="bi bi-pencil-fill"></i> Change
                                </button>
                            </div>

                            {{-- Zone + slot selector (collapsed by default if slot already set) --}}
                            <div id="editLocationSelector"
                                 class="{{ ($movement->location_row || old('location_zone')) ? 'd-none' : '' }}">

                                {{-- Step 1: Zone cards --}}
                                <div id="editZoneSelectorPanel">
                                    <p class="text-muted small mb-2">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill me-1">Step 1</span>
                                        Select a storage zone:
                                    </p>
                                    <div class="d-flex gap-2 flex-wrap" id="editZoneCards">
                                        @foreach($zones as $zone)
                                        <button type="button" class="edit-zone-pick-btn btn btn-outline-secondary"
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
                                <div id="editSlotGridPanel" class="d-none mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <p class="text-muted small mb-0">
                                            <span class="badge bg-primary-subtle text-primary rounded-pill me-1">Step 2</span>
                                            Click an <span class="text-success fw-semibold">available</span> slot:
                                            <span id="editGridZoneLabel" class="fw-semibold"></span>
                                        </p>
                                        <button type="button" class="btn btn-sm btn-link p-0" id="editBackToZonesBtn">
                                            <i class="bi bi-arrow-left me-1"></i>Change zone
                                        </button>
                                    </div>
                                    <div class="d-flex gap-3 mb-2" style="font-size:.72rem;">
                                        <span><span class="legend-dot" style="background:#22c55e;display:inline-block;width:10px;height:10px;border-radius:2px;"></span> Available</span>
                                        <span><span class="legend-dot" style="background:#ef4444;display:inline-block;width:10px;height:10px;border-radius:2px;"></span> Occupied</span>
                                        <span><span class="legend-dot" style="background:#3b82f6;border:2px solid #1d4ed8;display:inline-block;width:10px;height:10px;border-radius:2px;"></span> Selection</span>
                                    </div>
                                    <div id="editSlotGridLoading" class="text-center text-muted py-3 d-none">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Loading slots…
                                    </div>
                                    <div id="editSlotGridContent" style="overflow-x:auto; max-height:280px; overflow-y:auto;"></div>
                                </div>

                            </div>
                        </div>
                        {{-- ── End Storage Location ──────────────────────────── --}}

                        @else {{-- Gate Out --}}

                        <div class="col-6">
                            <label class="form-label fw-semibold">Release Order No.</label>
                            <input type="text" name="release_order" class="form-control"
                                   value="{{ old('release_order', $movement->release_order) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   value="{{ old('vehicle_plate', $movement->vehicle_plate) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control"
                                   value="{{ old('driver_name', $movement->driver_name) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver IC/Passport</label>
                            <input type="text" name="driver_ic" class="form-control"
                                   value="{{ old('driver_ic', $movement->driver_ic) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate Out Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Admin only
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_out_time"
                                   class="form-control"
                                   value="{{ old('gate_out_time', $movement->gate_out_time?->format('Y-m-d\TH:i')) }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                        </div>

                        @endif

                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $movement->remarks) }}</textarea>
                        </div>


                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn {{ $movement->movement_type === 'in' ? 'btn-primary' : 'btn-success' }}">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="{{ route('yard.gate') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Photos & Documents --}}
    <div class="col-lg-5">
        <x-document-manager
            model-type="App\Models\GateMovement"
            :model-id="$movement->id"
            :folder="'gate-movements/' . $movement->movement_type . '/' . $movement->id"
            title="Photos &amp; Documents"
            accept="image/*,application/pdf"
            :max-files="10"
        />
    </div>
</div>

@endsection

@push('styles')
<style>
.edit-zone-pick-btn { text-align:left; padding:.5rem .75rem; transition: all .15s; }
.edit-zone-pick-btn:hover, .edit-zone-pick-btn.active { background: #eff6ff; border-color: #3b82f6 !important; }
.edit-zone-pick-btn.active { box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
.slot-cell { display:flex; flex-direction:column-reverse; gap:2px; min-width:58px; }
.tier-block { width:54px; height:24px; border-radius:4px; font-size:.62rem; font-weight:600;
              display:flex; align-items:center; justify-content:center; cursor:default; transition:all .15s; }
.tier-block.available { background:#dcfce7; color:#15803d; border:1px solid #86efac; cursor:pointer; }
.tier-block.available:hover { background:#bbf7d0; border-color:#4ade80; transform:scale(1.05); }
.tier-block.occupied  { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.tier-block.reserved  { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
.tier-block.damaged, .tier-block.in_repair { background:#fce7f3; color:#9d174d; border:1px solid #f9a8d4; }
.tier-block.selected  { background:#dbeafe; color:#1d4ed8; border:2px solid #3b82f6; transform:scale(1.05); }
.slot-grid-wrap { display:flex; flex-direction:column; gap:4px; }
.slot-grid-row  { display:flex; align-items:flex-end; gap:4px; }
.slot-row-label { width:28px; font-size:.7rem; font-weight:700; color:#6b7280; text-align:right; padding-right:4px; flex-shrink:0; }
.slot-bay-header { width:58px; text-align:center; font-size:.62rem; color:#9ca3af; font-weight:600; padding-bottom:3px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form      = document.querySelector('form');
    const submitBtn = form.querySelector('[type="submit"]');
    const origHtml  = submitBtn.innerHTML;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving…';
        const fd = new FormData(form);
        fetch(form.action, { method: 'POST', body: fd, redirect: 'manual' })
            .then(function () { window.location.reload(); })
            .catch(function () { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; });
    });
})();

// ── Edit-movement zone / slot selector ──────────────────────────────────────
(function () {
    const zoneInput    = document.getElementById('edit_loc_zone');
    const rowInput     = document.getElementById('edit_loc_row');
    const bayInput     = document.getElementById('edit_loc_bay');
    const tierInput    = document.getElementById('edit_loc_tier');

    if (!zoneInput) return; // not a gate-in movement

    const slotDisplay       = document.getElementById('editSelectedSlotDisplay');
    const slotCode          = document.getElementById('editSelectedSlotCode');
    const changeBtn         = document.getElementById('editChangeSlotBtn');
    const locationSelector  = document.getElementById('editLocationSelector');
    const zoneSelectorPanel = document.getElementById('editZoneSelectorPanel');
    const slotGridPanel     = document.getElementById('editSlotGridPanel');
    const slotGridContent   = document.getElementById('editSlotGridContent');
    const slotGridLoading   = document.getElementById('editSlotGridLoading');
    const gridZoneLabel     = document.getElementById('editGridZoneLabel');
    const backBtn           = document.getElementById('editBackToZonesBtn');

    let currentZone = null;

    function showZoneSelector() {
        locationSelector.classList.remove('d-none');
        zoneSelectorPanel.classList.remove('d-none');
        slotGridPanel.classList.add('d-none');
        document.querySelectorAll('.edit-zone-pick-btn').forEach(b => b.classList.remove('active'));
    }

    changeBtn && changeBtn.addEventListener('click', function () {
        showZoneSelector();
    });

    backBtn && backBtn.addEventListener('click', function () {
        zoneSelectorPanel.classList.remove('d-none');
        slotGridPanel.classList.add('d-none');
        document.querySelectorAll('.edit-zone-pick-btn').forEach(b => b.classList.remove('active'));
    });

    document.querySelectorAll('.edit-zone-pick-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.edit-zone-pick-btn').forEach(b => b.classList.remove('active'));
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
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            renderGrid(data.slots);
        } catch (e) {
            slotGridContent.innerHTML = '<div class="alert alert-danger small py-2"><i class="bi bi-wifi-off me-1"></i>Failed to load slots.</div>';
        } finally {
            slotGridLoading.classList.add('d-none');
        }
    }

    function renderGrid(slots) {
        if (!slots || slots.length === 0) {
            slotGridContent.innerHTML = '<div class="text-muted small py-2"><i class="bi bi-info-circle me-1"></i>No slots configured in this zone.</div>';
            return;
        }
        const rows = [...new Set(slots.map(s => s.row))].sort();
        const bays = [...new Set(slots.map(s => parseInt(s.bay)))].sort((a,b) => a-b);
        const byKey = {};
        slots.forEach(s => {
            const k = s.row + '|' + s.bay;
            if (!byKey[k]) byKey[k] = [];
            byKey[k].push(s);
        });
        let html = '<div class="slot-grid-wrap">';
        html += '<div class="slot-grid-row"><div class="slot-row-label"></div>';
        bays.forEach(bay => { html += `<div class="slot-bay-header">Bay ${bay}</div>`; });
        html += '</div>';
        rows.forEach(row => {
            html += `<div class="slot-grid-row"><div class="slot-row-label">${row}</div>`;
            bays.forEach(bay => {
                const cell = byKey[row + '|' + bay] || [];
                html += '<div class="slot-cell">';
                const tiersAsc = [...cell].sort((a,b) => a.tier - b.tier);
                tiersAsc.forEach(s => {
                    const cls = s.status === 'empty' ? 'available'
                              : s.status === 'occupied' ? 'occupied'
                              : s.status === 'reserved' ? 'reserved' : 'damaged';
                    const tip = s.status === 'occupied' ? (s.container_no || 'Occupied') : s.status;
                    html += `<div class="tier-block ${cls}" title="T${s.tier}: ${tip}"
                                  data-zone="${s.zone}" data-row="${s.row}" data-bay="${s.bay}" data-tier="${s.tier}">
                                T${s.tier}
                             </div>`;
                });
                html += '</div>';
            });
            html += '</div>';
        });
        html += '</div>';
        slotGridContent.innerHTML = html;

        slotGridContent.querySelectorAll('.tier-block.available').forEach(function (el) {
            el.addEventListener('click', function () {
                slotGridContent.querySelectorAll('.tier-block.selected').forEach(b => {
                    b.classList.remove('selected');
                    b.classList.add('available');
                });
                this.classList.remove('available');
                this.classList.add('selected');

                zoneInput.value = this.dataset.zone;
                rowInput.value  = this.dataset.row;
                bayInput.value  = this.dataset.bay;
                tierInput.value = this.dataset.tier;

                const code = this.dataset.zone + '-' + this.dataset.row + this.dataset.bay + '-T' + this.dataset.tier;
                slotCode.textContent = code;
                slotDisplay.classList.remove('d-none');
                locationSelector.classList.add('d-none');
            });
        });
    }
})();
</script>
@endpush
