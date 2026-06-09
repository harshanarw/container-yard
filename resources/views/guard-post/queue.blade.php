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

{{-- Status tabs --}}
<ul class="nav nav-tabs mb-3">
    @foreach([
        'pending'  => ['Pending',   'warning'],
        'cleared'  => ['Cleared',   'success'],
        'hold'     => ['On Hold',   'warning'],
        'rejected' => ['Rejected',  'danger'],
        'all'      => ['All',       'secondary'],
    ] as $key => [$label, $color])
    <li class="nav-item">
        <a href="{{ request()->fullUrlWithQuery(['status' => $key]) }}"
           class="nav-link {{ $filter === $key ? 'active' : '' }}">
            {{ $label }}
            @if(isset($counts[$key]) && $counts[$key])
                <span class="badge bg-{{ $color }} ms-1">{{ $counts[$key] }}</span>
            @endif
        </a>
    </li>
    @endforeach
</ul>

<div class="card content-card">
    <div class="card-body p-0">
        @if($captures->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox display-5 d-block mb-2 opacity-50"></i>
                No captures found.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Direction</th>
                        <th>Container</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Captured By</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($captures as $capture)
                    <tr>
                        <td class="font-monospace small">{{ $capture->reference_no }}</td>
                        <td>
                            @if($capture->direction === 'gate_in')
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-box-arrow-in-right me-1"></i>IN</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-box-arrow-right me-1"></i>OUT</span>
                            @endif
                        </td>
                        <td class="font-monospace small">{{ $capture->container_number ?? '—' }}</td>
                        <td class="small">{{ $capture->vehicle_number ?? '—' }}</td>
                        <td class="small">{{ $capture->driver_name ?? '—' }}</td>
                        <td class="small">{{ $capture->capturedBy?->full_name ?? '—' }}</td>
                        <td class="small text-muted text-nowrap">{{ $capture->captured_at?->format('d M H:i') ?? '—' }}</td>
                        <td><span class="badge {{ $capture->status_badge_class }}">{{ $capture->status_label }}</span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-wrap">
                                {{-- View status --}}
                                <a href="{{ route('guard-post.status', $capture) }}"
                                   class="btn btn-sm btn-outline-secondary py-0"
                                   title="View">
                                    <i class="bi bi-eye"></i>
                                </a>

                                {{-- Process Gate-In link (cleared, gate_in direction, not yet linked) --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_in' && !$capture->linked_gate_movement_id)
                                <a href="{{ route('yard.gate') }}?capture_id={{ $capture->id }}"
                                   class="btn btn-sm btn-success py-0 text-nowrap">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Open Gate-In
                                </a>
                                @endif

                                {{-- Process Gate-Out link --}}
                                @if($capture->isCleared() && $capture->direction === 'gate_out' && !$capture->linked_gate_movement_id)
                                <a href="{{ route('yard.gate') }}?tab=out&capture_id={{ $capture->id }}"
                                   class="btn btn-sm btn-primary py-0 text-nowrap">
                                    <i class="bi bi-box-arrow-right me-1"></i>Open Gate-Out
                                </a>
                                @endif

                                {{-- Action dropdown for pending/hold captures --}}
                                @if(in_array($capture->status, ['pending', 'hold']))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary py-0 dropdown-toggle" type="button"
                                            data-bs-toggle="dropdown">
                                        Action
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item text-success" type="button"
                                                    onclick="openActionModal({{ $capture->id }}, 'cleared', '{{ $capture->reference_no }}')">
                                                <i class="bi bi-check-circle me-2"></i>Clear
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-warning" type="button"
                                                    onclick="openActionModal({{ $capture->id }}, 'hold', '{{ $capture->reference_no }}')">
                                                <i class="bi bi-pause-circle me-2"></i>Put on Hold
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-danger" type="button"
                                                    onclick="openActionModal({{ $capture->id }}, 'rejected', '{{ $capture->reference_no }}')">
                                                <i class="bi bi-x-circle me-2"></i>Reject
                                            </button>
                                        </li>
                                    </ul>
                                </div>
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

{{-- Action modal --}}
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="actionForm">
            @csrf @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Action Capture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="actionInput">
                    <p class="small text-muted mb-2">Capture: <strong id="actionRef"></strong></p>
                    <label class="form-label fw-semibold small">Note <span class="text-muted fw-normal">(Optional)</span></label>
                    <textarea name="notes" class="form-control" rows="3" maxlength="1000"
                              placeholder="Add a reason or instructions for the guard…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="actionSubmitBtn">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openActionModal(captureId, action, ref) {
    const titles = { cleared: 'Clear Capture', hold: 'Put on Hold', rejected: 'Reject Capture' };
    const colors = { cleared: 'btn-success', hold: 'btn-warning', rejected: 'btn-danger' };

    document.getElementById('actionModalTitle').textContent = titles[action] || 'Action Capture';
    document.getElementById('actionRef').textContent        = ref;
    document.getElementById('actionInput').value            = action;

    const btn = document.getElementById('actionSubmitBtn');
    btn.className = 'btn ' + (colors[action] || 'btn-primary');
    btn.textContent = titles[action] || 'Confirm';

    const baseUrl = '{{ url('guard-post/capture') }}/' + captureId + '/status';
    document.getElementById('actionForm').action = baseUrl;

    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>
@endpush
