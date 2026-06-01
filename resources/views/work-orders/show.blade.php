@extends('layouts.app')

@section('title', 'Work Order ' . $workOrder->wo_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">{{ $workOrder->wo_no }}</li>
@endsection

@section('content')

@php
$statusColors = [
    'pending'     => 'secondary',
    'in_progress' => 'primary',
    'on_hold'     => 'warning',
    'completed'   => 'info',
    'rejected'    => 'danger',
    'closed'      => 'success',
    'cancelled'   => 'danger',
];
@endphp

{{-- ── Page Header ── --}}
<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>{{ $workOrder->wo_no }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-{{ $statusColors[$workOrder->status] ?? 'secondary' }}">
                {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
            </span>
            @if($workOrder->repairCategory)
            &nbsp;<span class="badge bg-{{ $workOrder->repairCategory->color }}">{{ $workOrder->repairCategory->code }}</span>
            <span class="text-muted">{{ $workOrder->repairCategory->name }}</span>
            @endif
            &nbsp;·&nbsp; {{ $workOrder->container_no }}
            &nbsp;·&nbsp; {{ $workOrder->customer->name ?? '—' }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($canEdit)
        <a href="{{ route('work-orders.edit', $workOrder) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif

        @if($canStart)
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="in_progress">
            <button type="submit" class="btn btn-success btn-sm"
                    data-confirm="Start this work order?"
                    data-confirm-title="Start Work Order"
                    data-confirm-class="btn-success"
                    data-confirm-label="Start">
                <i class="bi bi-play-circle me-1"></i>Start Work
            </button>
        </form>
        @endif

        @if($canComplete && $workOrder->status === 'in_progress')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-info btn-sm"
                    data-confirm="Mark as complete? The work order will move to QC review."
                    data-confirm-title="Mark Complete"
                    data-confirm-class="btn-info"
                    data-confirm-label="Mark Complete">
                <i class="bi bi-check-lg me-1"></i>Mark Complete
            </button>
        </form>
        @endif

        @if($canComplete && $workOrder->status === 'on_hold')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="in_progress">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-play-circle me-1"></i>Resume
            </button>
        </form>
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-info btn-sm"
                    data-confirm="Mark as complete? The work order will move to QC review."
                    data-confirm-title="Mark Complete"
                    data-confirm-class="btn-info"
                    data-confirm-label="Mark Complete">
                <i class="bi bi-check-lg me-1"></i>Mark Complete
            </button>
        </form>
        @endif

        @if($workOrder->status === 'in_progress')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="on_hold">
            <button type="submit" class="btn btn-warning btn-sm"
                    data-confirm="Put this work order on hold?"
                    data-confirm-title="Put On Hold"
                    data-confirm-class="btn-warning"
                    data-confirm-label="Put On Hold">
                <i class="bi bi-pause-circle me-1"></i>Hold
            </button>
        </form>
        @endif

        @if($canStartRework)
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="in_progress">
            <button type="submit" class="btn btn-warning btn-sm"
                    data-confirm="Start rework on this work order? It will return to In Progress."
                    data-confirm-title="Start Rework"
                    data-confirm-class="btn-warning"
                    data-confirm-label="Start Rework">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Start Rework
            </button>
        </form>
        @endif

        @if(in_array($workOrder->status, ['pending', 'in_progress', 'on_hold']))
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Cancel this work order? This action cannot be undone."
                    data-confirm-title="Cancel Work Order"
                    data-confirm-class="btn-danger"
                    data-confirm-label="Cancel">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($canDelete)
        <form method="POST" action="{{ route('work-orders.destroy', $workOrder) }}" class="d-inline">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Delete this work order? This cannot be undone."
                    data-confirm-title="Delete Work Order"
                    data-confirm-class="btn-danger"
                    data-confirm-label="Delete">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif

        <a href="{{ route('work-orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

{{-- ── Flash messages ── --}}
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

{{-- ── QC Rejected banner ── --}}
@if($workOrder->status === 'rejected')
<div class="alert alert-danger mb-4 py-3">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-x-octagon-fill fs-5 flex-shrink-0 mt-1"></i>
        <div class="flex-grow-1">
            <strong>QC Rejected — Rework Required</strong>
            @if($workOrder->qc_notes)
            <p class="mb-1 mt-1 small">{{ $workOrder->qc_notes }}</p>
            @endif
            <div class="small text-muted mt-1">
                Rejected by <strong>{{ $workOrder->qcBy?->name ?? '—' }}</strong>
                on {{ $workOrder->qc_at?->format('d M Y, H:i') ?? '—' }}
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Pending QC reminder (for non-QC users) ── --}}
@if($workOrder->status === 'completed' && !$canQc)
<div class="alert alert-info mb-4 py-2 small">
    <i class="bi bi-clipboard-check me-1"></i>
    This work order is complete and <strong>awaiting QC inspection</strong> by a supervisor.
</div>
@endif

{{-- ── Detail cards ── --}}
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light"><h5 class="mb-0">Work Details</h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal small">Container</dt>
                    <dd class="col-7 fw-semibold">{{ $workOrder->container_no }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Customer</dt>
                    <dd class="col-7 fw-semibold">{{ $workOrder->customer->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Priority</dt>
                    <dd class="col-7">
                        <span class="badge {{ $workOrder->priority === 'critical' ? 'bg-danger' : ($workOrder->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">
                            {{ ucfirst($workOrder->priority) }}
                        </span>
                    </dd>

                    <dt class="col-5 text-muted fw-normal small">Assigned To</dt>
                    <dd class="col-7">{{ $workOrder->assignedTo->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Target Date</dt>
                    <dd class="col-7 small">{{ $workOrder->target_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Started</dt>
                    <dd class="col-7 small">{{ $workOrder->started_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Completed</dt>
                    <dd class="col-7 small">{{ $workOrder->completed_date?->format('d M Y') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light"><h5 class="mb-0">Estimate & Notes</h5></div>
            <div class="card-body">
                @if($workOrder->estimate)
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal small">Estimate #</dt>
                    <dd class="col-7 fw-semibold">
                        <a href="{{ route('estimates.show', $workOrder->estimate) }}">
                            {{ $workOrder->estimate->estimate_no }}
                        </a>
                    </dd>
                    <dt class="col-5 text-muted fw-normal small">Status</dt>
                    <dd class="col-7">
                        <span class="badge bg-{{ $workOrder->estimate->status === 'approved' ? 'success' : 'secondary' }}">
                            {{ ucfirst($workOrder->estimate->status) }}
                        </span>
                    </dd>
                    <dt class="col-5 text-muted fw-normal small">Grand Total</dt>
                    <dd class="col-7 fw-semibold">
                        {{ $workOrder->estimate->currency }}
                        {{ number_format($workOrder->estimate->grand_total ?? 0, 2) }}
                    </dd>
                </dl>
                @endif

                @if($workOrder->instructions)
                <hr class="my-2">
                <div class="text-muted small fw-semibold mb-1">Instructions</div>
                <p class="small mb-0">{{ $workOrder->instructions }}</p>
                @endif

                @if($workOrder->technician_notes)
                <hr class="my-2">
                <div class="text-muted small fw-semibold mb-1">Technician Notes</div>
                <p class="small mb-0">{{ $workOrder->technician_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── QC Record card (shown after QC has been done) ── --}}
@if($workOrder->status === 'closed' && $workOrder->qc_by)
<div class="card mb-4 border-success">
    <div class="card-header bg-success-subtle d-flex align-items-center gap-2">
        <i class="bi bi-patch-check-fill text-success"></i>
        <h6 class="mb-0">QC Record</h6>
        <span class="ms-auto badge bg-success">Passed</span>
    </div>
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-sm-3 fw-normal text-muted">QC Inspector</dt>
            <dd class="col-sm-9">{{ $workOrder->qcBy->name }}</dd>
            <dt class="col-sm-3 fw-normal text-muted">Inspected</dt>
            <dd class="col-sm-9">{{ $workOrder->qc_at->format('d M Y, H:i') }}</dd>
            @if($workOrder->qc_notes)
            <dt class="col-sm-3 fw-normal text-muted">Notes</dt>
            <dd class="col-sm-9">{{ $workOrder->qc_notes }}</dd>
            @endif
        </dl>
    </div>
</div>
@endif

{{-- ── Work Order Lines ── --}}
@if($workOrder->lines->count() > 0)
@php
    $showQcCols = in_array($workOrder->status, ['closed', 'rejected'])
               || $workOrder->lines->contains(fn($l) => $l->qc_status !== null);
@endphp
<div class="card mb-4">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Work Order Lines <span class="text-muted fw-normal small">({{ $workOrder->lines->count() }})</span></h5>
        @if($showQcCols)
        <span class="small text-muted">
            @php
                $passed = $workOrder->lines->where('qc_status', 'passed')->count();
                $failed = $workOrder->lines->where('qc_status', 'failed')->count();
                $total  = $workOrder->lines->count();
            @endphp
            <span class="badge bg-success me-1">{{ $passed }} passed</span>
            @if($failed > 0)<span class="badge bg-danger me-1">{{ $failed }} failed</span>@endif
            @if($total - $passed - $failed > 0)<span class="badge bg-secondary">{{ $total - $passed - $failed }} pending</span>@endif
        </span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:30px">#</th>
                    <th>Location</th>
                    <th>Component</th>
                    <th>Damage</th>
                    <th>Repair</th>
                    <th class="text-end" style="width:55px">Qty</th>
                    <th style="width:100px">Status</th>
                    @if($showQcCols)
                    <th style="width:90px">QC</th>
                    <th>QC Notes</th>
                    @else
                    <th>Technician Notes</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->lines as $i => $line)
                <tr class="{{ $line->qc_status === 'failed' ? 'table-danger' : ($line->qc_status === 'passed' ? 'table-success bg-opacity-25' : '') }}">
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td class="small">{{ $line->locationCode?->code ?? '—' }}</td>
                    <td class="small fw-semibold">{{ $line->componentCode?->code ?? '—' }}</td>
                    <td class="small">{{ $line->damageCode?->code ?? '—' }}</td>
                    <td class="small">{{ $line->repairCode?->code ?? '—' }}</td>
                    <td class="small text-end">{{ $line->qty }}</td>
                    <td class="small">
                        @php
                            $lsc = match($line->status) {
                                'completed'   => 'bg-success',
                                'in_progress' => 'bg-primary',
                                'skipped'     => 'bg-secondary',
                                default       => 'bg-light text-dark border',
                            };
                        @endphp
                        <span class="badge {{ $lsc }}">{{ ucfirst($line->status) }}</span>
                    </td>
                    @if($showQcCols)
                    <td class="small">
                        @if($line->qc_status === 'passed')
                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Pass</span>
                        @elseif($line->qc_status === 'failed')
                            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Fail</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $line->qc_notes ?? '—' }}</td>
                    @else
                    <td class="small text-muted">{{ $line->technician_notes ?? '—' }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── QC Review Panel (visible to supervisors/admins when status = completed) ── --}}
@if($canQc)
<div class="card border-warning mb-4">
    <div class="card-header bg-warning-subtle d-flex align-items-center gap-2">
        <i class="bi bi-clipboard-check fs-5 text-warning"></i>
        <h5 class="mb-0">QC Inspection</h5>
        <span class="ms-auto badge bg-warning text-dark">Awaiting Review</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Mark each repair line as <strong class="text-success">Pass</strong> or <strong class="text-danger">Fail</strong>.
            If any line fails, the work order is returned for rework.
            When all lines pass, the work order is closed.
        </p>

        @if($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            @foreach($errors->all() as $err)<div><i class="bi bi-exclamation-circle me-1"></i>{{ $err }}</div>@endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('work-orders.submit-qc', $workOrder) }}" id="qcForm">
            @csrf

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0" id="qcTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px">#</th>
                            <th>Loc</th>
                            <th>Component</th>
                            <th>Damage</th>
                            <th>Repair</th>
                            <th class="text-end" style="width:50px">Qty</th>
                            <th style="width:180px">QC Result <span class="text-danger">*</span></th>
                            <th>Failure Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workOrder->lines as $i => $line)
                        <tr class="qc-line-row" id="qc-row-{{ $line->id }}">
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td class="small">{{ $line->locationCode?->code ?? '—' }}</td>
                            <td class="small fw-semibold">
                                {{ $line->componentCode?->code ?? '—' }}
                                @if($line->componentCode?->name)
                                <div class="text-muted" style="font-size:.7rem">{{ $line->componentCode->name }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $line->damageCode?->code ?? '—' }}</td>
                            <td class="small">{{ $line->repairCode?->code ?? '—' }}</td>
                            <td class="small text-end">{{ $line->qty }}</td>
                            <td>
                                <div class="btn-group btn-group-sm w-100" role="group">
                                    <input type="radio" class="btn-check qc-radio"
                                           name="line_results[{{ $line->id }}]"
                                           id="pass_{{ $line->id }}"
                                           value="passed"
                                           data-line="{{ $line->id }}"
                                           {{ old('line_results.'.$line->id) === 'passed' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-success" for="pass_{{ $line->id }}">
                                        <i class="bi bi-check-lg"></i> Pass
                                    </label>

                                    <input type="radio" class="btn-check qc-radio"
                                           name="line_results[{{ $line->id }}]"
                                           id="fail_{{ $line->id }}"
                                           value="failed"
                                           data-line="{{ $line->id }}"
                                           {{ old('line_results.'.$line->id) === 'failed' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-danger" for="fail_{{ $line->id }}">
                                        <i class="bi bi-x-lg"></i> Fail
                                    </label>
                                </div>
                            </td>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm fail-note-input"
                                       id="note_{{ $line->id }}"
                                       name="line_qc_notes[{{ $line->id }}]"
                                       value="{{ old('line_qc_notes.'.$line->id) }}"
                                       placeholder="Reason for failure…"
                                       style="display:{{ old('line_results.'.$line->id) === 'failed' ? 'block' : 'none' }}">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">
                        Overall QC Notes <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea name="qc_notes" class="form-control form-control-sm" rows="2"
                              placeholder="General inspection remarks, observations…">{{ old('qc_notes') }}</textarea>
                </div>
                <div class="col-md-5">
                    <div class="d-grid gap-2">
                        <button type="button" id="passAllBtn" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-check-all me-1"></i>Pass All Lines
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm" id="submitQcBtn">
                            <i class="bi bi-clipboard-check me-1"></i>Submit QC Review
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if($canQc)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Show/hide failure note input based on radio selection
    document.querySelectorAll('.qc-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var lineId   = this.dataset.line;
            var noteInput = document.getElementById('note_' + lineId);
            var row       = document.getElementById('qc-row-' + lineId);

            if (this.value === 'failed') {
                noteInput.style.display = 'block';
                noteInput.focus();
                row.classList.add('table-danger');
                row.classList.remove('table-success');
            } else {
                noteInput.style.display = 'none';
                noteInput.value = '';
                row.classList.remove('table-danger');
                row.classList.add('table-success');
            }
        });
    });

    // Pass All button — select all Pass radios
    document.getElementById('passAllBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.qc-radio[value="passed"]').forEach(function (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        });
    });

    // Highlight rows that were already old()-populated on failed
    document.querySelectorAll('.qc-radio[value="failed"]:checked').forEach(function (radio) {
        var row = document.getElementById('qc-row-' + radio.dataset.line);
        if (row) row.classList.add('table-danger');
    });
    document.querySelectorAll('.qc-radio[value="passed"]:checked').forEach(function (radio) {
        var row = document.getElementById('qc-row-' + radio.dataset.line);
        if (row) row.classList.add('table-success');
    });
});
</script>
@endif
@endpush
