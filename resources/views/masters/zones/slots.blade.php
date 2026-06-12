@extends('layouts.app')

@section('title', 'Slot Configuration — Zone ' . $zone->code)

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.zones.index') }}">Storage Zones</a></li>
    <li class="breadcrumb-item active">Zone {{ $zone->code }} — Slots</li>
@endsection

@push('styles')
<style>
.slot-grid-wrap  { display:flex; flex-direction:column; gap:5px; }
.slot-grid-row   { display:flex; align-items:flex-end; gap:5px; }
.slot-row-label  { width:32px; font-size:.72rem; font-weight:700; color:#6b7280; text-align:right;
                   padding-right:6px; flex-shrink:0; padding-bottom:4px; }
.slot-bay-header { min-width:64px; text-align:center; font-size:.65rem; color:#9ca3af;
                   font-weight:700; padding-bottom:4px; letter-spacing:.5px; }
.slot-cell       { display:flex; flex-direction:column-reverse; gap:3px; min-width:64px; }
.tier-block      { width:60px; height:28px; border-radius:5px; font-size:.65rem; font-weight:600;
                   display:flex; align-items:center; justify-content:center; gap:3px;
                   position:relative; transition:all .12s; user-select:none; }
.tier-block.empty    { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.tier-block.occupied { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.tier-block.reserved { background:#fef9c3; color:#a16207; border:1px solid #fde047; }
.tier-block.damaged,
.tier-block.in_repair{ background:#fce7f3; color:#9d174d; border:1px solid #f9a8d4; }

/* Delete buttons */
.tier-block .del-slot { display:none; position:absolute; top:-4px; right:-4px;
                        width:14px; height:14px; border-radius:50%; background:#ef4444;
                        color:#fff; font-size:.55rem; line-height:14px; text-align:center;
                        cursor:pointer; border:none; padding:0; z-index:2; }
.tier-block .del-slot-blocked { display:none; position:absolute; top:-4px; right:-4px;
                        width:14px; height:14px; border-radius:50%; background:#9ca3af;
                        color:#fff; font-size:.55rem; line-height:14px; text-align:center;
                        cursor:not-allowed; border:none; padding:0; z-index:2; }
.tier-block.empty:hover .del-slot,
.tier-block.empty:hover .del-slot-blocked { display:block; }

/* Drag & drop */
.tier-block.occupied[draggable="true"] { cursor: grab; }
.tier-block.occupied[draggable="true"]:active { cursor: grabbing; }
.tier-block.dragging  { opacity:.35; border-style:dashed !important; }
.tier-block.drag-over { background:#dbeafe !important; border:2px dashed #3b82f6 !important;
                        color:#1d4ed8 !important; transform:scale(1.08); }
/* Drag mode: highlight all empty slots as potential targets */
body.dragging-active .tier-block.empty:not(.drag-over) {
    border-color:#93c5fd !important; background:#eff6ff !important;
}

.legend-dot { display:inline-block; width:11px; height:11px; border-radius:3px; vertical-align:middle; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <div style="width:36px;height:36px;border-radius:8px;background:{{ $zone->color }};border:2px solid rgba(0,0,0,.1);flex-shrink:0;"></div>
        <div>
            <h4 class="mb-0">
                <span class="badge fw-bold me-1" style="background:{{ $zone->color }};font-size:1rem;">{{ $zone->code }}</span>
                {{ $zone->name }}
                <span class="badge rounded-pill {{ $zone->is_active ? 'bg-success' : 'bg-secondary' }} ms-1" style="font-size:.72rem;">
                    {{ $zone->is_active ? 'Active' : 'Inactive' }}
                </span>
            </h4>
            <p class="text-muted mb-0 small">{{ $zone->description ?? 'Configure rows, bays and tiers for this zone.' }}</p>
        </div>
    </div>
    <a href="{{ route('masters.zones.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Zones
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Stats bar --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ $stats['total'] }}</div>
            <div class="small text-muted">Total Slots</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $stats['empty'] }}</div>
            <div class="small text-muted">Available</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-danger">{{ $stats['occupied'] }}</div>
            <div class="small text-muted">Occupied</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-warning">{{ $stats['reserved'] + $stats['other'] }}</div>
            <div class="small text-muted">Reserved / Other</div>
        </div>
    </div>
</div>

<div class="row g-3">

    {{-- Left: Bulk generator --}}
    <div class="col-lg-4">

        {{-- Generate Slots Card --}}
        @can('masters.storage-zones.create')
        <div class="card content-card mb-3">
            <div class="card-header bg-primary text-white py-2">
                <i class="bi bi-grid-3x3-gap me-1"></i>Bulk Generate Slots
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('masters.zones.slots.generate', $zone) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Rows <span class="text-danger">*</span>
                            <span class="text-muted fw-normal">(letters)</span>
                        </label>
                        <input type="text" name="rows" class="form-control form-control-sm text-uppercase @error('rows') is-invalid @enderror"
                               value="{{ old('rows') }}" placeholder="e.g. A-E  or  A,B,C,D" required
                               autocomplete="off">
                        <div class="form-text" style="font-size:.7rem;">
                            Range: <code>A-E</code> → A, B, C, D, E &nbsp;|&nbsp; List: <code>A,B,C</code>
                        </div>
                        @error('rows')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Bays <span class="text-danger">*</span>
                            <span class="text-muted fw-normal">(numbers)</span>
                        </label>
                        <input type="text" name="bays" class="form-control form-control-sm @error('bays') is-invalid @enderror"
                               value="{{ old('bays') }}" placeholder="e.g. 1-10  or  1,2,3,4,5" required
                               autocomplete="off">
                        <div class="form-text" style="font-size:.7rem;">
                            Range: <code>1-10</code> → 1 to 10 &nbsp;|&nbsp; List: <code>1,3,5</code>
                        </div>
                        @error('bays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Tiers <span class="text-danger">*</span>
                            <span class="text-muted fw-normal">(stack levels)</span>
                        </label>
                        <input type="text" name="tiers" class="form-control form-control-sm @error('tiers') is-invalid @enderror"
                               value="{{ old('tiers') }}" placeholder="e.g. 1-5  or  1,2,3" required
                               autocomplete="off">
                        <div class="form-text" style="font-size:.7rem;">
                            Tier 1 = ground level, higher = stacked above
                        </div>
                        @error('tiers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Live preview counter --}}
                    <div id="previewCount" class="alert alert-primary py-2 small mb-3 d-none">
                        <i class="bi bi-calculator me-1"></i>
                        Will generate <strong id="previewNum">0</strong> slots
                        <span id="previewDetail" class="text-muted"></span>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm" id="generateBtn">
                            <i class="bi bi-plus-circle me-1"></i>Generate Slots
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- Quick Templates --}}
        <div class="card content-card mb-3">
            <div class="card-header py-2 small fw-semibold">
                <i class="bi bi-lightning me-1 text-warning"></i>Quick Templates
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-2">Click to fill the form above:</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary template-btn"
                            data-rows="A-E" data-bays="1-10" data-tiers="1-3">
                        5×10×3 <span class="text-muted">(150)</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary template-btn"
                            data-rows="A-D" data-bays="1-8" data-tiers="1-4">
                        4×8×4 <span class="text-muted">(128)</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary template-btn"
                            data-rows="A-C" data-bays="1-6" data-tiers="1-2">
                        3×6×2 <span class="text-muted">(36)</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary template-btn"
                            data-rows="A-B" data-bays="1-5" data-tiers="1">
                        2×5×1 <span class="text-muted">(10)</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Danger zone --}}
        @can('masters.storage-zones.delete')
        @if($stats['empty'] > 0)
        <div class="card content-card border-danger">
            <div class="card-header py-2 small fw-semibold text-danger bg-danger-subtle">
                <i class="bi bi-exclamation-triangle me-1"></i>Bulk Remove
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-2">
                    Remove all <strong>{{ $stats['empty'] }}</strong> empty slot(s) from this zone.
                    Occupied/reserved slots are not affected.
                </p>
                <form method="POST" action="{{ route('masters.zones.slots.clear', $zone) }}"
                      data-confirm="Remove all {{ $stats['empty'] }} empty slot(s) from Zone {{ $zone->code }}? Occupied and reserved slots are not affected."
                      data-confirm-title="Clear Empty Slots"
                      data-confirm-class="btn-danger"
                      data-confirm-label="Clear Slots">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Clear Empty Slots
                    </button>
                </form>
            </div>
        </div>
        @endif
        @endcan

    </div>

    {{-- Right: Slot grid --}}
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header d-flex align-items-center justify-content-between py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-grid me-1 text-primary"></i>Slot Grid — Zone {{ $zone->code }}
                </span>
                <div class="d-flex gap-3" style="font-size:.7rem;">
                    <span><span class="legend-dot" style="background:#dcfce7;border:1px solid #86efac;"></span> Empty</span>
                    <span><span class="legend-dot" style="background:#fee2e2;border:1px solid #fca5a5;"></span> Occupied</span>
                    <span><span class="legend-dot" style="background:#fef9c3;border:1px solid #fde047;"></span> Reserved</span>
                    <span><span class="legend-dot" style="background:#fce7f3;border:1px solid #f9a8d4;"></span> Damaged/Repair</span>
                </div>
            </div>
            <div class="card-body" style="overflow-x:auto;">

                @if($slots->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-1">No slots configured yet for Zone {{ $zone->code }}.</p>
                        <p class="small">Use the Bulk Generate form on the left to create slots.</p>
                    </div>
                @else
                    @php
                        $rows     = $slots->pluck('row')->unique()->sort()->values();
                        $bays     = $slots->pluck('bay')->unique()->sort()->values();
                        $byKey    = $slots->groupBy(fn($s) => $s->row . '|' . $s->bay);
                    @endphp

                    {{-- Small note --}}
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Hover over an <span style="color:#15803d;">empty</span> slot to reveal the delete button.
                        <span class="text-danger fw-semibold">&times;</span> = deletable (topmost empty slot).
                        <span class="text-secondary fw-semibold">&times;</span> = blocked — a higher tier exists above; remove top tiers first.
                        Occupied/reserved slots cannot be deleted.
                    </p>

                    <div class="slot-grid-wrap">
                        {{-- Bay header row --}}
                        <div class="slot-grid-row">
                            <div class="slot-row-label"></div>
                            @foreach($bays as $bay)
                                <div class="slot-bay-header">Bay {{ $bay }}</div>
                            @endforeach
                        </div>

                        {{-- Data rows --}}
                        @foreach($rows as $row)
                        <div class="slot-grid-row">
                            <div class="slot-row-label">{{ $row }}</div>
                            @foreach($bays as $bay)
                                @php $cell = $byKey->get($row . '|' . $bay, collect()); @endphp
                                <div class="slot-cell">
                                    @forelse($cell->sortBy('tier') as $slot)
                                        @php
                                            $hasAbove = $cell->contains(fn($s) => $s->tier > $slot->tier);
                                        @endphp
                                        <div class="tier-block {{ $slot->status }}"
                                             title="{{ $slot->slot_code }}: {{ $slot->status }}{{ $slot->container ? ' — ' . $slot->container->container_no : '' }}{{ ($slot->status === 'empty' && $hasAbove) ? ' (cannot delete — tiers above exist)' : '' }}{{ $slot->status === 'occupied' ? ' — drag to move' : '' }}"
                                             @if($slot->status === 'occupied')
                                                 draggable="true"
                                                 data-drag-zone="{{ $zone->code }}"
                                                 data-drag-row="{{ $slot->row }}"
                                                 data-drag-bay="{{ $slot->bay }}"
                                                 data-drag-tier="{{ $slot->tier }}"
                                                 data-drag-container="{{ $slot->container?->container_no }}"
                                             @elseif($slot->status === 'empty')
                                                 data-drop-zone="{{ $zone->code }}"
                                                 data-drop-row="{{ $slot->row }}"
                                                 data-drop-bay="{{ $slot->bay }}"
                                                 data-drop-tier="{{ $slot->tier }}"
                                             @endif>
                                            T{{ $slot->tier }}
                                            @if($slot->status === 'empty')
                                                @if($hasAbove)
                                                    {{-- Blocked: higher tiers exist in this stack --}}
                                                    <span class="del-slot del-slot-blocked"
                                                          title="Remove T{{ $slot->tier + 1 }} and above first">
                                                        &times;
                                                    </span>
                                                @else
                                                    @can('masters.storage-zones.delete')
                                                    <form method="POST"
                                                          action="{{ route('masters.zones.slots.destroy', [$zone, $slot]) }}"
                                                          class="d-inline del-slot-form">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="del-slot"
                                                                title="Delete slot {{ $slot->slot_code }}"
                                                                onclick="event.stopPropagation();"
                                                                data-confirm="Delete slot {{ $slot->slot_code }}?"
                                                                data-confirm-title="Delete Slot"
                                                                data-confirm-class="btn-danger"
                                                                data-confirm-label="Delete">
                                                            &times;
                                                        </button>
                                                    </form>
                                                    @endcan
                                                @endif
                                            @elseif($slot->status === 'occupied' && $slot->container)
                                                <span style="font-size:.55rem;max-width:44px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                    {{ substr($slot->container->container_no, 0, 6) }}
                                                </span>
                                            @endif
                                        </div>
                                    @empty
                                        <div style="min-width:60px;height:28px;"></div>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-3 pt-2 border-top small text-muted d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            {{ $stats['total'] }} slots &nbsp;·&nbsp;
                            {{ $rows->count() }} row(s): {{ $rows->implode(', ') }} &nbsp;·&nbsp;
                            {{ $bays->count() }} bay(s): {{ $bays->first() }}–{{ $bays->last() }} &nbsp;·&nbsp;
                            @php $maxTier = $slots->max('tier'); @endphp
                            Tiers up to {{ $maxTier }}
                        </span>
                        @if($stats['occupied'] > 0)
                        <span class="badge bg-info-subtle text-info">
                            <i class="bi bi-arrows-move me-1"></i>Drag occupied slots to move containers
                        </span>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>

</div>

{{-- ── Location Adjustment Activity Log ── --}}
<div class="card content-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-clock-history me-2 text-primary"></i>Location Adjustment Log
            <span class="badge bg-secondary-subtle text-secondary ms-1">Zone {{ $zone->code }}</span>
        </span>
        <span class="small text-muted">Last {{ $recentAdjustments->count() }} adjustments</span>
    </div>
    <div class="card-body p-0">
        @if($recentAdjustments->isEmpty())
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-check-circle fs-2 d-block mb-2 opacity-25"></i>
                No location adjustments recorded yet for Zone {{ $zone->code }}.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Container</th>
                        <th>From</th>
                        <th></th>
                        <th>To</th>
                        <th>Adjusted By</th>
                        <th>Notes</th>
                        <th class="pe-3">When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentAdjustments as $adj)
                    <tr>
                        <td class="ps-3 font-monospace fw-semibold small">{{ $adj->container_no }}</td>
                        <td>
                            <span class="badge bg-danger-subtle text-danger font-monospace" style="font-size:.72rem;">
                                {{ $adj->from_code }}
                            </span>
                        </td>
                        <td class="text-muted px-0"><i class="bi bi-arrow-right"></i></td>
                        <td>
                            <span class="badge bg-success-subtle text-success font-monospace" style="font-size:.72rem;">
                                {{ $adj->to_code }}
                            </span>
                        </td>
                        <td class="small">
                            <i class="bi bi-person me-1 text-muted"></i>{{ $adj->adjustedBy?->name ?? '—' }}
                        </td>
                        <td class="small text-muted">{{ $adj->notes ?? '—' }}</td>
                        <td class="pe-3 small text-muted text-nowrap">{{ $adj->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ── Move confirmation modal ── --}}
<div class="modal fade" id="moveModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title"><i class="bi bi-arrows-move me-1 text-primary"></i>Move Container</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="bg-light rounded-3 p-2 mb-3 small">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-muted">Container:</span>
                        <strong id="moveContainerNo" class="font-monospace"></strong>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger font-monospace" id="moveFromCode"></span>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <span class="badge bg-success-subtle text-success font-monospace" id="moveToCode"></span>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">
                        Reason / Notes <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea id="moveNotes" class="form-control form-control-sm" rows="2"
                              placeholder="e.g. Crane access issue, re-stacking required…"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" id="moveCancelBtn">Cancel</button>
                @can('masters.storage-zones.edit')
                <button type="button" class="btn btn-sm btn-primary" id="moveConfirmBtn">
                    <i class="bi bi-check-lg me-1"></i>Confirm Move
                </button>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Quick template buttons ───────────────────────────────────────────────
    document.querySelectorAll('.template-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('[name="rows"]').value  = this.dataset.rows;
            document.querySelector('[name="bays"]').value  = this.dataset.bays;
            document.querySelector('[name="tiers"]').value = this.dataset.tiers;
            updatePreview();
            document.querySelector('[name="rows"]').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // ── Live slot count preview ──────────────────────────────────────────────
    const rowsInput  = document.querySelector('[name="rows"]');
    const baysInput  = document.querySelector('[name="bays"]');
    const tiersInput = document.querySelector('[name="tiers"]');
    const countEl    = document.getElementById('previewCount');
    const numEl      = document.getElementById('previewNum');
    const detailEl   = document.getElementById('previewDetail');

    function parseRange(val, type) {
        val = val.trim().toUpperCase();
        if (!val) return [];
        const rangeMatch = val.match(/^([A-Z0-9]+)\s*-\s*([A-Z0-9]+)$/);
        if (rangeMatch) {
            const s = rangeMatch[1], e = rangeMatch[2];
            if (type === 'alpha' && s.length === 1 && e.length === 1) {
                const sc = s.charCodeAt(0), ec = e.charCodeAt(0);
                if (sc <= ec && ec - sc <= 25) {
                    const r = [];
                    for (let i = sc; i <= ec; i++) r.push(String.fromCharCode(i));
                    return r;
                }
            } else if (type === 'num' && !isNaN(s) && !isNaN(e)) {
                const si = parseInt(s), ei = parseInt(e);
                if (si <= ei && ei - si <= 99) {
                    const r = [];
                    for (let i = si; i <= ei; i++) r.push(i);
                    return r;
                }
            }
        }
        return val.split(',').map(v => v.trim()).filter(v => v);
    }

    function updatePreview() {
        const rows  = parseRange(rowsInput.value,  'alpha');
        const bays  = parseRange(baysInput.value,  'num');
        const tiers = parseRange(tiersInput.value, 'num');
        const total = rows.length * bays.length * tiers.length;
        if (total > 0) {
            numEl.textContent = total;
            detailEl.textContent = ` (${rows.length} rows × ${bays.length} bays × ${tiers.length} tiers)`;
            countEl.classList.remove('d-none');
            countEl.className = total > 500
                ? 'alert alert-danger py-2 small mb-3'
                : 'alert alert-primary py-2 small mb-3';
            if (total > 500) {
                numEl.textContent = total + ' — exceeds 500 limit';
            }
        } else {
            countEl.classList.add('d-none');
        }
    }

    [rowsInput, baysInput, tiersInput].forEach(function (el) {
        el && el.addEventListener('input', updatePreview);
    });
})();

// ── Drag-and-drop slot move ──────────────────────────────────────────────────
(function () {
    const MOVE_URL = '{{ route('masters.zones.slots.move', $zone) }}';
    const CSRF     = '{{ csrf_token() }}';

    let dragData = null;

    const modal          = new bootstrap.Modal(document.getElementById('moveModal'));
    const moveFromCode   = document.getElementById('moveFromCode');
    const moveToCode     = document.getElementById('moveToCode');
    const moveContNo     = document.getElementById('moveContainerNo');
    const moveNotes      = document.getElementById('moveNotes');
    const moveConfirmBtn = document.getElementById('moveConfirmBtn');
    const moveCancelBtn  = document.getElementById('moveCancelBtn');

    // ── Drag source (occupied blocks) ──
    document.querySelectorAll('.tier-block[draggable="true"]').forEach(function (el) {
        el.addEventListener('dragstart', function (e) {
            dragData = {
                zone: this.dataset.dragZone, row: this.dataset.dragRow,
                bay:  this.dataset.dragBay,  tier: this.dataset.dragTier,
                containerNo: this.dataset.dragContainer,
            };
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragData.containerNo);
            setTimeout(() => this.classList.add('dragging'), 0);
            document.body.classList.add('dragging-active');
        });
        el.addEventListener('dragend', function () {
            this.classList.remove('dragging');
            document.body.classList.remove('dragging-active');
            document.querySelectorAll('.drag-over').forEach(e => e.classList.remove('drag-over'));
            dragData = null;
        });
    });

    // ── Drop targets (empty blocks) ──
    document.querySelectorAll('.tier-block[data-drop-zone]').forEach(function (el) {
        el.addEventListener('dragover',  function (e) { if (dragData) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; } });
        el.addEventListener('dragenter', function (e) { if (dragData) { e.preventDefault(); document.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over')); this.classList.add('drag-over'); } });
        el.addEventListener('dragleave', function ()  { this.classList.remove('drag-over'); });
        el.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragData) return;
            this.classList.remove('drag-over');

            const fromCode = dragData.zone + '-' + dragData.row + dragData.bay + '-T' + dragData.tier;
            const toCode   = this.dataset.dropZone + '-' + this.dataset.dropRow + this.dataset.dropBay + '-T' + this.dataset.dropTier;
            if (fromCode === toCode) return;

            moveContNo.textContent   = dragData.containerNo || '—';
            moveFromCode.textContent = fromCode;
            moveToCode.textContent   = toCode;
            moveNotes.value          = '';
            // Store ALL move data on the confirm button so dragData isn't needed after dragend fires
            moveConfirmBtn.dataset.fromRow      = dragData.row;
            moveConfirmBtn.dataset.fromBay      = dragData.bay;
            moveConfirmBtn.dataset.fromTier     = dragData.tier;
            moveConfirmBtn.dataset.containerNo  = dragData.containerNo;
            moveConfirmBtn.dataset.toRow        = this.dataset.dropRow;
            moveConfirmBtn.dataset.toBay        = this.dataset.dropBay;
            moveConfirmBtn.dataset.toTier       = this.dataset.dropTier;
            modal.show();
        });
    });

    // ── Confirm ──
    moveConfirmBtn.addEventListener('click', async function () {
        // Read all data from dataset (dragData may already be null after dragend fired)
        const payload = {
            from_row:  this.dataset.fromRow,  from_bay:  parseInt(this.dataset.fromBay),  from_tier: parseInt(this.dataset.fromTier),
            to_row:    this.dataset.toRow,     to_bay:    parseInt(this.dataset.toBay),     to_tier:   parseInt(this.dataset.toTier),
            notes: moveNotes.value.trim() || null,
        };
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Moving…';
        try {
            const res  = await fetch(MOVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            modal.hide();
            if (res.ok && data.success) {
                sessionStorage.setItem('slot_move_success', data.message);
                window.location.reload();
            } else {
                showToast(data.error || 'Move failed.', 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Move';
            }
        } catch (err) {
            modal.hide();
            showToast('Network error. Please try again.', 'danger');
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Move';
        }
    });

    moveCancelBtn.addEventListener('click', function () { dragData = null; });

    // Show success toast after reload
    const msg = sessionStorage.getItem('slot_move_success');
    if (msg) { sessionStorage.removeItem('slot_move_success'); showToast(msg, 'success'); }

    function showToast(msg, type) {
        let c = document.getElementById('toastContainer');
        if (!c) {
            c = document.createElement('div');
            c.id = 'toastContainer';
            c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            c.style.zIndex = '1100';
            document.body.appendChild(c);
        }
        const id = 'toast-' + Date.now();
        c.insertAdjacentHTML('beforeend',
            `<div id="${id}" class="toast align-items-center text-bg-${type} border-0 show" role="alert" style="min-width:260px;">
               <div class="d-flex"><div class="toast-body small">${msg}</div>
               <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
             </div>`);
        setTimeout(() => document.getElementById(id)?.remove(), 4000);
    }
})();
</script>
@endpush
