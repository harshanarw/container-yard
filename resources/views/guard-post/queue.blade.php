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

{{-- Status filter tabs --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @php
        $tabDefs = [
            'pending'  => ['Pending',   '#f59e0b', '#fff8e1', '#f59e0b'],
            'cleared'  => ['Cleared',   '#16a34a', '#f0fdf4', '#16a34a'],
            'hold'     => ['On Hold',   '#d97706', '#fffbeb', '#d97706'],
            'rejected' => ['Rejected',  '#dc2626', '#fef2f2', '#dc2626'],
            'all'      => ['All',       '#6b7280', '#f9fafb', '#6b7280'],
        ];
    @endphp
    @foreach($tabDefs as $key => [$label, $textColor, $bgColor, $borderColor])
    @php $isActive = $filter === $key; @endphp
    <a href="{{ request()->fullUrlWithQuery(['status' => $key]) }}"
       class="text-decoration-none"
       style="
           display:inline-flex; align-items:center; gap:6px;
           padding:6px 14px; border-radius:20px; font-size:.8rem; font-weight:600;
           border: 2px solid {{ $borderColor }};
           background: {{ $isActive ? $bgColor : '#fff' }};
           color: {{ $isActive ? $textColor : '#6b7280' }};
           box-shadow: {{ $isActive ? '0 1px 6px rgba(0,0,0,.12)' : 'none' }};
           transition: all .15s;
       ">
        {{ $label }}
        @php $cnt = $counts->get($key, 0); @endphp
        @if($cnt)
        <span style="
            background:{{ $isActive ? $textColor : '#e5e7eb' }};
            color:{{ $isActive ? '#fff' : '#6b7280' }};
            border-radius:10px; padding:0 7px; font-size:.7rem; font-weight:700; line-height:1.6;
        ">{{ $cnt }}</span>
        @endif
    </a>
    @endforeach
</div>

<div class="card content-card">
    <div class="card-body p-0">
        @if($captures->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox display-5 d-block mb-2 opacity-50"></i>
                No captures found.
            </div>
        @else
        {{-- overflow:visible lets action buttons escape the container without clipping --}}
        <div style="overflow-x:auto; overflow-y:visible;">
            <table class="table table-sm table-hover mb-0" style="overflow:visible;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Reference</th>
                        <th>Direction</th>
                        <th>Container</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Captured By</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($captures as $capture)
                    <tr>
                        <td class="ps-3 font-monospace small">{{ $capture->reference_no }}</td>
                        <td>
                            @if($capture->direction === 'gate_in')
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>IN
                                </span>
                            @else
                                <span class="badge bg-primary-subtle text-primary">
                                    <i class="bi bi-box-arrow-right me-1"></i>OUT
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
                                $statusStyles = [
                                    'pending'  => 'background:#fff8e1;color:#b45309;border:1px solid #fcd34d;',
                                    'cleared'  => 'background:#f0fdf4;color:#16a34a;border:1px solid #86efac;',
                                    'hold'     => 'background:#fffbeb;color:#d97706;border:1px solid #fde68a;',
                                    'rejected' => 'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
                                ];
                            @endphp
                            <span class="badge fw-semibold"
                                  style="{{ $statusStyles[$capture->status] ?? '' }} font-size:.72rem; padding:4px 8px; border-radius:10px;">
                                {{ $capture->status_label }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">

                                {{-- View status --}}
                                <a href="{{ route('guard-post.status', $capture) }}"
                                   class="btn btn-sm btn-outline-secondary py-0"
                                   title="View capture details">
                                    <i class="bi bi-eye"></i>
                                </a>

                                {{-- Clear / Hold / Reject — direct buttons, no dropdown --}}
                                @if($capture->isPending())
                                <button type="button"
                                        class="btn btn-sm py-0 btn-action"
                                        style="background:#f0fdf4;color:#16a34a;border:1px solid #86efac;"
                                        onclick="openActionModal({{ $capture->id }}, 'cleared', '{{ $capture->reference_no }}')"
                                        title="Clear this capture">
                                    <i class="bi bi-check-circle me-1"></i>Clear
                                </button>
                                <button type="button"
                                        class="btn btn-sm py-0 btn-action"
                                        style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;"
                                        onclick="openActionModal({{ $capture->id }}, 'hold', '{{ $capture->reference_no }}')"
                                        title="Put on hold">
                                    <i class="bi bi-pause-circle me-1"></i>Hold
                                </button>
                                <button type="button"
                                        class="btn btn-sm py-0 btn-action"
                                        style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;"
                                        onclick="openActionModal({{ $capture->id }}, 'rejected', '{{ $capture->reference_no }}')"
                                        title="Reject this capture">
                                    <i class="bi bi-x-circle me-1"></i>Reject
                                </button>
                                @elseif($capture->isOnHold())
                                <button type="button"
                                        class="btn btn-sm py-0 btn-action"
                                        style="background:#f0fdf4;color:#16a34a;border:1px solid #86efac;"
                                        onclick="openActionModal({{ $capture->id }}, 'cleared', '{{ $capture->reference_no }}')"
                                        title="Clear this capture">
                                    <i class="bi bi-check-circle me-1"></i>Clear
                                </button>
                                <button type="button"
                                        class="btn btn-sm py-0 btn-action"
                                        style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;"
                                        onclick="openActionModal({{ $capture->id }}, 'rejected', '{{ $capture->reference_no }}')"
                                        title="Reject">
                                    <i class="bi bi-x-circle me-1"></i>Reject
                                </button>
                                @endif

                                {{-- Open Gate-In (cleared gate_in, not yet linked) --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_in' && !$capture->linked_gate_movement_id)
                                <a href="{{ route('yard.gate') }}?capture_id={{ $capture->id }}"
                                   class="btn btn-sm py-0 text-nowrap"
                                   style="background:#eff6ff;color:#2563eb;border:1px solid #93c5fd;"
                                   title="Open Gate-In form pre-filled from this capture">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Gate-In
                                </a>
                                @endif

                                {{-- Open Gate-Out (cleared gate_out, not yet linked) --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_out' && !$capture->linked_gate_movement_id)
                                <a href="{{ route('yard.gate') }}?tab=out&capture_id={{ $capture->id }}"
                                   class="btn btn-sm py-0 text-nowrap"
                                   style="background:#f0f9ff;color:#0284c7;border:1px solid #7dd3fc;"
                                   title="Open Gate-Out form pre-filled from this capture">
                                    <i class="bi bi-box-arrow-right me-1"></i>Gate-Out
                                </a>
                                @endif

                                {{-- Linked badge --}}
                                @if($capture->linked_gate_movement_id)
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:.68rem;">
                                    <i class="bi bi-link-45deg me-1"></i>Linked
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

{{-- ── Action confirmation modal ──────────────────────────────────────────── --}}
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" id="actionForm">
            @csrf @method('PATCH')
            <div class="modal-content">
                <div class="modal-header py-2" id="actionModalHeader">
                    <h6 class="modal-title mb-0 fw-semibold" id="actionModalTitle">Action Capture</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="actionInput">
                    <p class="small text-muted mb-3">
                        <i class="bi bi-shield-check me-1"></i>Ref: <strong id="actionRef" class="font-monospace"></strong>
                    </p>
                    <label class="form-label fw-semibold small">
                        Note <span class="text-muted fw-normal">(Optional)</span>
                    </label>
                    <textarea name="notes" id="actionNotes" class="form-control form-control-sm" rows="3"
                              maxlength="1000"
                              placeholder="Reason or instructions for the guard…"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" id="actionSubmitBtn">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
<style>
.btn-action { font-size: .75rem; font-weight: 600; }
.btn-action:hover { filter: brightness(.93); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const cfg = {
        cleared:  { title: 'Clear Capture',  headerBg: '#16a34a', btnCls: 'btn-success',  icon: 'bi-check-circle-fill' },
        hold:     { title: 'Put on Hold',     headerBg: '#d97706', btnCls: 'btn-warning',  icon: 'bi-pause-circle-fill' },
        rejected: { title: 'Reject Capture', headerBg: '#dc2626', btnCls: 'btn-danger',   icon: 'bi-x-circle-fill'     },
    };

    window.openActionModal = function (captureId, action, ref) {
        const c = cfg[action] || {};
        document.getElementById('actionModalTitle').innerHTML =
            `<i class="bi ${c.icon ?? 'bi-shield'} me-2"></i>${c.title ?? 'Action'}`;
        document.getElementById('actionModalHeader').style.background  = c.headerBg ?? '#6b7280';
        document.getElementById('actionModalHeader').style.color        = '#fff';
        document.getElementById('actionRef').textContent   = ref;
        document.getElementById('actionInput').value       = action;
        document.getElementById('actionNotes').value       = '';   // clear previous note

        const btn = document.getElementById('actionSubmitBtn');
        btn.className    = 'btn btn-sm ' + (c.btnCls ?? 'btn-primary');
        btn.textContent  = c.title ?? 'Confirm';

        document.getElementById('actionForm').action =
            '{{ url('/guard-post/capture') }}/' + captureId + '/status';

        const modal = bootstrap.Modal.getOrCreate(document.getElementById('actionModal'));
        modal.show();
    };
})();
</script>
@endpush
