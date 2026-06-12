@extends('layouts.app')

@section('title', 'Capture Queue')

@section('breadcrumb')
    <li class="breadcrumb-item active">Guard Post Queue</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2 text-success"></i>Guard Post Queue</h4>
        <p class="text-muted mb-0 small">Review and action pending gate captures from the guard post</p>
    </div>
    <a href="{{ route('guard-post.create') }}" class="btn btn-success btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Capture
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Status filter bar ────────────────────────────────────────────────────── --}}
@php
    $tabs = [
        'pending'  => ['label' => 'Pending',   'color' => '#b45309', 'bg' => '#fef3c7', 'fill' => '#d97706', 'icon' => 'bi-hourglass-split'],
        'cleared'  => ['label' => 'Cleared',   'color' => '#15803d', 'bg' => '#dcfce7', 'fill' => '#16a34a', 'icon' => 'bi-check-circle-fill'],
        'hold'     => ['label' => 'On Hold',   'color' => '#c2410c', 'bg' => '#ffedd5', 'fill' => '#ea580c', 'icon' => 'bi-pause-circle-fill'],
        'rejected' => ['label' => 'Rejected',  'color' => '#991b1b', 'bg' => '#fee2e2', 'fill' => '#dc2626', 'icon' => 'bi-x-circle-fill'],
        'all'      => ['label' => 'All',       'color' => '#374151', 'bg' => '#f3f4f6', 'fill' => '#6b7280', 'icon' => 'bi-collection'],
    ];
@endphp
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    @foreach($tabs as $key => $t)
    @php
        $active = $filter === $key;
        $cnt    = $counts->get($key, 0);
    @endphp
    <a href="{{ request()->fullUrlWithQuery(['status' => $key]) }}"
       class="gp-tab {{ $active ? 'gp-tab-active' : '' }}"
       style="--tab-color:{{ $t['color'] }};--tab-bg:{{ $t['bg'] }};--tab-fill:{{ $t['fill'] }};">
        <i class="bi {{ $t['icon'] }} me-1" style="font-size:.75rem;"></i>
        {{ $t['label'] }}
        @if($cnt)
            <span class="gp-tab-count">{{ $cnt }}</span>
        @endif
    </a>
    @endforeach
</div>

{{-- ── Captures table ───────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-body p-0">
        @if($captures->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox display-5 d-block mb-2 opacity-50"></i>
                No captures found for this filter.
            </div>
        @else
        <div class="gp-table-wrap">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Reference</th>
                        <th>Dir.</th>
                        <th>Container</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Captured By</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="min-width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($captures as $capture)
                    <tr>
                        <td class="ps-3 font-monospace small fw-semibold">{{ $capture->reference_no }}</td>
                        <td>
                            @if($capture->direction === 'gate_in')
                                <span class="status-pill" style="background:#dbeafe;color:#1e40af;border-color:#93c5fd;">
                                    <i class="bi bi-box-arrow-in-right"></i> IN
                                </span>
                            @else
                                <span class="status-pill" style="background:#dcfce7;color:#166534;border-color:#86efac;">
                                    <i class="bi bi-box-arrow-right"></i> OUT
                                </span>
                            @endif
                        </td>
                        <td class="font-monospace small">{{ $capture->container_number ?? '—' }}</td>
                        <td class="small">{{ $capture->vehicle_number ?? '—' }}</td>
                        <td class="small">{{ $capture->driver_name ?? '—' }}</td>
                        <td class="small">{{ $capture->capturedBy?->full_name ?? '—' }}</td>
                        <td class="small text-muted text-nowrap">
                            {{ $capture->captured_at?->format('d M H:i') ?? '—' }}
                        </td>
                        <td>
                            @php
                                $sp = match($capture->status) {
                                    'pending'  => 'background:#fef3c7;color:#92400e;border-color:#fcd34d;',
                                    'cleared'  => 'background:#dcfce7;color:#166534;border-color:#86efac;',
                                    'hold'     => 'background:#ffedd5;color:#9a3412;border-color:#fdba74;',
                                    'rejected' => 'background:#fee2e2;color:#991b1b;border-color:#fca5a5;',
                                    default    => 'background:#f3f4f6;color:#374151;border-color:#d1d5db;',
                                };
                            @endphp
                            <span class="status-pill fw-semibold" style="{{ $sp }}">
                                {{ $capture->status_label }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end align-items-center">

                                {{-- View --}}
                                <a href="{{ route('guard-post.status', $capture) }}"
                                   class="act-btn act-view" title="View details">
                                    <i class="bi bi-eye"></i>
                                </a>

                                {{-- Actions for pending --}}
                                @if($capture->isPending())
                                @can('guard-post.edit')
                                <button type="button" class="act-btn act-clear"
                                        onclick="openActionModal({{ $capture->id }},'cleared','{{ $capture->reference_no }}')"
                                        title="Clear — allow entry">
                                    <i class="bi bi-check-lg me-1"></i>Clear
                                </button>
                                <button type="button" class="act-btn act-hold"
                                        onclick="openActionModal({{ $capture->id }},'hold','{{ $capture->reference_no }}')"
                                        title="Put on hold">
                                    <i class="bi bi-pause-fill me-1"></i>Hold
                                </button>
                                <button type="button" class="act-btn act-reject"
                                        onclick="openActionModal({{ $capture->id }},'rejected','{{ $capture->reference_no }}')"
                                        title="Reject entry">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </button>
                                @endcan
                                @endif

                                {{-- Actions for on-hold --}}
                                @if($capture->isOnHold())
                                @can('guard-post.edit')
                                <button type="button" class="act-btn act-clear"
                                        onclick="openActionModal({{ $capture->id }},'cleared','{{ $capture->reference_no }}')"
                                        title="Clear — allow entry">
                                    <i class="bi bi-check-lg me-1"></i>Clear
                                </button>
                                <button type="button" class="act-btn act-reject"
                                        onclick="openActionModal({{ $capture->id }},'rejected','{{ $capture->reference_no }}')"
                                        title="Reject entry">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </button>
                                @endcan
                                @endif

                                {{-- Gate-In link (cleared, gate_in, not yet linked) --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_in' && !$capture->linked_gate_movement_id)
                                @can('guard-post.edit')
                                <a href="{{ route('yard.gate') }}?capture_id={{ $capture->id }}"
                                   class="act-btn act-gate-in" title="Open Gate-In form — pre-filled from this capture">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Gate-In
                                </a>
                                @endcan
                                @endif

                                {{-- Gate-Out link --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_out' && !$capture->linked_gate_movement_id)
                                @can('guard-post.edit')
                                <a href="{{ route('yard.gate') }}?tab=out&capture_id={{ $capture->id }}"
                                   class="act-btn act-gate-out" title="Open Gate-Out form — pre-filled from this capture">
                                    <i class="bi bi-box-arrow-right me-1"></i>Gate-Out
                                </a>
                                @endcan
                                @endif

                                {{-- Linked badge --}}
                                @if($capture->linked_gate_movement_id)
                                <span class="status-pill" style="background:#f3f4f6;color:#6b7280;border-color:#d1d5db;font-size:.68rem;">
                                    <i class="bi bi-link-45deg"></i> Linked
                                </span>
                                @endif

                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $captures->links() }}</div>
        @endif
    </div>
</div>

{{-- ── Action confirmation modal ────────────────────────────────────────────── --}}
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form method="POST" id="actionForm">
            @csrf
            <input type="hidden" name="_method" value="PATCH">
            <div class="modal-content">
                <div class="modal-header py-2" id="actionModalHeader">
                    <h6 class="modal-title mb-0 fw-semibold text-white" id="actionModalTitle">
                        Action Capture
                    </h6>
                    <button type="button" class="btn-close btn-close-white btn-sm"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-2">
                    <input type="hidden" name="action" id="actionInput">
                    <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded" id="actionRefBox"
                         style="background:#f8f9fa;border:1px solid #e9ecef;">
                        <i class="bi bi-shield-check text-muted"></i>
                        <span class="small text-muted">Capture:</span>
                        <strong class="font-monospace small" id="actionRef"></strong>
                    </div>
                    <label class="form-label fw-semibold small mb-1">
                        Note <span class="text-muted fw-normal">(Optional)</span>
                    </label>
                    <textarea name="notes" id="actionNotes" class="form-control form-control-sm" rows="3"
                              maxlength="1000"
                              placeholder="Reason or instructions for the guard…"></textarea>
                </div>
                <div class="modal-footer py-2 gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" id="actionSubmitBtn">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
/* ── Status filter tabs ─────────────────────────────────────── */
.gp-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 13px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    border: 2px solid var(--tab-fill);
    color: var(--tab-color);
    background: #fff;
    transition: background .15s, color .15s, box-shadow .15s;
}
.gp-tab:hover {
    background: var(--tab-bg);
    color: var(--tab-color);
    text-decoration: none;
}
.gp-tab-active {
    background: var(--tab-fill) !important;
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.18);
}
.gp-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    background: rgba(255,255,255,.35);
    color: inherit;
    border: 1px solid rgba(255,255,255,.5);
}
.gp-tab-active .gp-tab-count {
    background: rgba(0,0,0,.18);
    border-color: rgba(0,0,0,.1);
    color: #fff;
}

/* ── Table overflow fix ─────────────────────────────────────── */
.gp-table-wrap { overflow-x: auto; overflow-y: visible; }

/* ── Status pills ───────────────────────────────────────────── */
.status-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid;
    font-size: .72rem;
    white-space: nowrap;
}

/* ── Action buttons ─────────────────────────────────────────── */
.act-btn {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: .72rem;
    font-weight: 600;
    border: 1px solid;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    background: none;
    transition: filter .1s;
}
.act-btn:hover { filter: brightness(.9); text-decoration: none; }

.act-view    { color:#6b7280;  border-color:#d1d5db; background:#f9fafb; }
.act-clear   { color:#15803d;  border-color:#86efac; background:#f0fdf4; }
.act-hold    { color:#c2410c;  border-color:#fdba74; background:#fff7ed; }
.act-reject  { color:#b91c1c;  border-color:#fca5a5; background:#fef2f2; }
.act-gate-in { color:#1d4ed8;  border-color:#93c5fd; background:#eff6ff; }
.act-gate-out{ color:#15803d;  border-color:#86efac; background:#f0fdf4; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const cfg = {
        cleared:  { title: 'Clear Capture',  bg: '#16a34a', btn: 'btn-success' },
        hold:     { title: 'Put on Hold',     bg: '#ea580c', btn: 'btn-warning'  },
        rejected: { title: 'Reject Capture', bg: '#dc2626', btn: 'btn-danger'   },
    };

    window.openActionModal = function (captureId, action, ref) {
        const c = cfg[action] || { title: 'Action', bg: '#6b7280', btn: 'btn-secondary' };

        document.getElementById('actionModalTitle').textContent = c.title;
        document.getElementById('actionModalHeader').style.background = c.bg;
        document.getElementById('actionRef').textContent = ref;
        document.getElementById('actionInput').value = action;
        document.getElementById('actionNotes').value = '';

        const submitBtn = document.getElementById('actionSubmitBtn');
        submitBtn.className = 'btn btn-sm ' + c.btn;
        submitBtn.textContent = c.title;

        document.getElementById('actionForm').action =
            '{{ url('/guard-post/capture') }}/' + captureId + '/status';

        // FIX: correct Bootstrap 5 API — getOrCreateInstance, not getOrCreate
        bootstrap.Modal.getOrCreateInstance(document.getElementById('actionModal')).show();
    };
})();
</script>
@endpush
