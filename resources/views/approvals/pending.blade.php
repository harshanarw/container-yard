@extends('layouts.app')

@section('title', 'My Approvals')

@section('breadcrumb')
    <li class="breadcrumb-item active">My Approvals</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-check2-circle me-2 text-primary"></i>My Approvals</h4>
        <p class="text-muted mb-0 small">Pending approval actions assigned to your role</p>
    </div>
    <span class="badge bg-primary fs-6">{{ $actions->count() }} Pending</span>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($actions->isEmpty())
<div class="card content-card">
    <div class="card-body text-center py-5">
        <i class="bi bi-check2-all fs-1 text-success"></i>
        <h5 class="mt-3 text-muted">No pending approvals</h5>
        <p class="text-muted small">You have no approval actions waiting for your review.</p>
    </div>
</div>
@else
<div class="card content-card">
    <div class="card-header">
        <i class="bi bi-list-check me-2"></i>Pending Actions
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Document</th>
                        <th>Step</th>
                        <th>Initiated By</th>
                        <th>Initiated At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $action)
                    @php
                        $req = $action->approvalRequest;
                        $doc = $req->approvable;
                        $isGatePass = $req->workflow_type === 'gate_pass';
                        $docLabel = $isGatePass
                            ? 'Gate Pass — ' . ($doc?->container_no ?? '#' . $req->approvable_id)
                            : ucfirst(str_replace('_', ' ', $req->workflow_type)) . ' #' . $req->approvable_id;
                        $docLink = $isGatePass && $doc
                            ? route('yard.movements.edit', $doc)
                            : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                @if($docLink)
                                <a href="{{ $docLink }}" class="text-decoration-none">{{ $docLabel }}</a>
                                @else
                                {{ $docLabel }}
                                @endif
                            </div>
                            <div class="small text-muted">Request #{{ $req->id }}</div>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">Step {{ $action->step_order }}</span>
                            <div class="small mt-1">{{ $action->step_label }}</div>
                        </td>
                        <td>
                            <div>{{ $req->initiatedBy?->name ?? '—' }}</div>
                        </td>
                        <td>
                            <div>{{ $req->initiated_at?->format('d M Y') }}</div>
                            <div class="small text-muted">{{ $req->initiated_at?->format('H:i') }}</div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                {{-- Approve --}}
                                <form method="POST" action="{{ route('approvals.actions.approve', $action) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Approve this step?')">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                </form>

                                {{-- Reject --}}
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal{{ $action->id }}">
                                    <i class="bi bi-x-lg me-1"></i>Reject
                                </button>

                                @if($docLink)
                                <a href="{{ $docLink }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Reject Modal --}}
                    <div class="modal fade" id="rejectModal{{ $action->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Step</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="{{ route('approvals.actions.reject', $action) }}">
                                    @csrf
                                    <div class="modal-body">
                                        <p class="text-muted small mb-3">
                                            Rejecting <strong>{{ $action->step_label }}</strong> for {{ $docLabel }}.<br>
                                            This will mark the entire request as rejected.
                                        </p>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                                            <textarea name="remarks" class="form-control" rows="3" required
                                                      placeholder="State the reason for rejection…"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-x-circle me-1"></i>Confirm Rejection
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection
