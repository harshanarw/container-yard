@extends('layouts.app')

@section('title', 'Guard Post Queue')

@section('breadcrumb')
    <li class="breadcrumb-item active">Guard Post Queue</li>
@endsection

@section('content')
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2"></i>Guard Post Queue</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Guard Post Queue</li></ol></nav>
    </div>
    <a href="{{ route('guard-post.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Capture
    </a>
</div>

{{-- Status Tab Filter --}}
<ul class="nav nav-tabs mb-0">
    @php
        $tabs = ['pending' => 'Pending', 'cleared' => 'Cleared', 'hold' => 'On Hold', 'rejected' => 'Rejected', 'all' => 'All'];
        $badgeClass = ['pending' => 'bg-warning text-dark', 'cleared' => 'bg-success', 'hold' => 'bg-warning text-dark', 'rejected' => 'bg-danger'];
    @endphp
    @foreach($tabs as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $status === $key ? 'active' : '' }}"
           href="{{ route('guard-post.queue', ['status' => $key]) }}">
            {{ $label }}
            @if(isset($counts[$key]) && $counts[$key] > 0)
                <span class="badge {{ $badgeClass[$key] ?? 'bg-secondary' }} ms-1">{{ $counts[$key] }}</span>
            @endif
        </a>
    </li>
    @endforeach
</ul>

<div class="card content-card" style="border-radius: 0 12px 12px 12px;">
    <div class="card-body p-0">
        @if($captures->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                No captures found for this filter.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
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
                        <td><span class="font-monospace fw-semibold small">{{ $capture->reference_no }}</span></td>
                        <td>
                            @if($capture->direction === 'gate_in')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
                                </span>
                            @else
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bi bi-box-arrow-right me-1"></i>Gate Out
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="font-monospace small fw-semibold">{{ $capture->container_number ?: '—' }}</div>
                            @if($capture->iso_code)<div class="text-muted" style="font-size:.72rem;">{{ $capture->iso_code }}</div>@endif
                            @if($capture->container_image_url)
                                <a href="{{ $capture->container_image_url }}" target="_blank" class="text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-image me-1"></i>View
                                </a>
                            @endif
                        </td>
                        <td>
                            <div class="font-monospace small">{{ $capture->vehicle_number ?: '—' }}</div>
                            @if($capture->plate_image_url)
                                <a href="{{ $capture->plate_image_url }}" target="_blank" class="text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-image me-1"></i>View
                                </a>
                            @endif
                        </td>
                        <td class="small">
                            <div>{{ $capture->driver_name ?: '—' }}</div>
                            @if($capture->nic_number)<div class="text-muted font-monospace">{{ $capture->nic_number }}</div>@endif
                        </td>
                        <td class="small text-muted">{{ $capture->capturedBy?->full_name }}</td>
                        <td class="small text-muted text-nowrap">{{ $capture->captured_at->format('d M H:i') }}</td>
                        <td>
                            <span class="badge bg-{{ $capture->status_badge_class }}">{{ $capture->status_label }}</span>
                            @if($capture->clearance_note)
                                <div class="text-muted mt-1" style="font-size:.7rem;max-width:140px;">{{ Str::limit($capture->clearance_note, 40) }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end align-items-center">
                                {{-- View status page --}}
                                <a href="{{ route('guard-post.status', $capture) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View Status">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @if($capture->isPending() || $capture->isOnHold())
                                {{-- Action dropdown --}}
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                        Action
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:260px;"
                                         onclick="event.stopPropagation()">
                                        <form method="POST"
                                              action="{{ route('guard-post.update-status', $capture) }}">
                                            @csrf @method('PATCH')
                                            <div class="mb-2">
                                                <textarea name="note" rows="2" class="form-control form-control-sm"
                                                          placeholder="Note (optional)…"></textarea>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button type="submit" name="action" value="cleared"
                                                        class="btn btn-sm btn-success flex-fill"
                                                        data-confirm="Clear this capture and allow entry?"
                                                        data-confirm-class="btn-success"
                                                        data-confirm-label="Clear">
                                                    <i class="bi bi-check2"></i> Clear
                                                </button>
                                                <button type="submit" name="action" value="hold"
                                                        class="btn btn-sm btn-warning flex-fill"
                                                        data-confirm="Place this capture on hold?"
                                                        data-confirm-label="Hold">
                                                    <i class="bi bi-pause"></i> Hold
                                                </button>
                                                <button type="submit" name="action" value="rejected"
                                                        class="btn btn-sm btn-danger flex-fill"
                                                        data-confirm="Reject this capture?"
                                                        data-confirm-class="btn-danger"
                                                        data-confirm-label="Reject">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endif

                                @if($capture->isCleared() && !$capture->linked_gate_movement_id)
                                {{-- Link to Gate-In --}}
                                <a href="{{ route('yard.gate') }}?container={{ $capture->container_number }}"
                                   class="btn btn-sm btn-outline-primary" title="Open Gate-In">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2">
            {{ $captures->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
