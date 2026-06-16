@extends('layouts.app')

@section('title', 'Audit Log')

@section('breadcrumb')
    <li class="breadcrumb-item active">Audit Log</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-journal-text me-2 text-primary"></i>Audit Log</h4>
        <p class="text-muted mb-0 small">Complete record of all system actions — who did what, when, and on which record</p>
    </div>
</div>

{{-- Search / Filter Bar --}}
<div class="card content-card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('audit-log.index') }}" id="auditFilterForm">
            <div class="row g-2 align-items-end">

                {{-- Reference search --}}
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-search me-1"></i>Reference / Container / Job No.
                    </label>
                    <input type="text" name="reference" value="{{ request('reference') }}"
                           class="form-control form-control-sm font-monospace text-uppercase"
                           placeholder="e.g. EEEE4444444 or EST-2026-001"
                           autocomplete="off">
                </div>

                {{-- Module --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Module</label>
                    <select name="module" class="form-select form-select-sm">
                        <option value="">All Modules</option>
                        @foreach($modules as $mod)
                        <option value="{{ $mod['key'] }}" {{ request('module') === $mod['key'] ? 'selected' : '' }}>
                            {{ $mod['label'] }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Event --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Action / Event</label>
                    <select name="event" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        @foreach($events as $ev)
                        <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>
                            {{ ucfirst($ev) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- User --}}
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">User</label>
                    <select name="causer_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('causer_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->full_name ?? $u->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Date from --}}
                <div class="col-6 col-md-1">
                    <label class="form-label fw-semibold small mb-1">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="form-control form-control-sm">
                </div>

                {{-- Date to --}}
                <div class="col-6 col-md-1">
                    <label class="form-label fw-semibold small mb-1">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="form-control form-control-sm">
                </div>

                {{-- Buttons --}}
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('audit-log.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- Results --}}
<div class="card content-card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">
            <i class="bi bi-list-ul me-1 text-primary"></i>
            {{ number_format($logs->total()) }} record{{ $logs->total() !== 1 ? 's' : '' }} found
        </span>
        <span class="text-muted small">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
    </div>

    @if($logs->isEmpty())
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-journal-x" style="font-size:2.5rem;opacity:.3"></i>
        <p class="mt-2 mb-0">No audit log entries found for the selected filters.</p>
    </div>
    @else
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.8rem">
            <thead class="table-light">
                <tr>
                    <th style="width:140px">Date / Time</th>
                    <th style="width:120px">Module</th>
                    <th style="width:95px">Action</th>
                    <th>Description</th>
                    <th style="width:120px">Reference</th>
                    <th style="width:140px">User</th>
                    <th style="width:100px">Role</th>
                    <th style="width:100px">IP</th>
                    <th style="width:36px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                @php
                    $eventClass = match($log->event) {
                        'created', 'gate-in', 'plug-in'              => 'bg-success-subtle text-success border-success-subtle',
                        'deleted'                                     => 'bg-danger-subtle text-danger border-danger-subtle',
                        'approved'                                    => 'bg-primary-subtle text-primary border-primary-subtle',
                        'rejected'                                    => 'bg-warning-subtle text-warning border-warning-subtle',
                        'gate-out', 'plug-out'                       => 'bg-info-subtle text-info border-info-subtle',
                        'temp-log'                                    => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                        default                                       => 'bg-light text-muted border-secondary-subtle',
                    };
                    $eventIcon = match($log->event) {
                        'created'   => 'bi-plus-circle',
                        'updated'   => 'bi-pencil',
                        'deleted'   => 'bi-trash',
                        'gate-in'   => 'bi-box-arrow-in-right',
                        'gate-out'  => 'bi-box-arrow-right',
                        'plug-in'   => 'bi-plug',
                        'plug-out'  => 'bi-plug-fill',
                        'temp-log'  => 'bi-thermometer-half',
                        'approved'  => 'bi-check-circle',
                        'rejected'  => 'bi-x-circle',
                        default     => 'bi-activity',
                    };
                @endphp
                <tr>
                    <td class="text-nowrap">
                        <div class="fw-semibold">{{ $log->created_at->format('d M Y') }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $log->created_at->format('H:i:s') }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-secondary border" style="font-size:.68rem;text-transform:none">
                            {{ $log->log_name ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge border {{ $eventClass }}" style="font-size:.68rem">
                            <i class="bi {{ $eventIcon }} me-1"></i>{{ $log->event }}
                        </span>
                    </td>
                    <td class="text-wrap" style="max-width:280px">
                        {{ $log->description ?? '—' }}
                    </td>
                    <td>
                        @if($log->reference)
                        <span class="font-monospace fw-semibold" style="font-size:.78rem">{{ $log->reference }}</span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold" style="font-size:.78rem">{{ $log->causer_name ?? 'System' }}</div>
                    </td>
                    <td>
                        @if($log->causer_role)
                        <span class="text-muted" style="font-size:.72rem">
                            {{ ucwords(str_replace('_', ' ', $log->causer_role)) }}
                        </span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-muted font-monospace" style="font-size:.7rem">
                        {{ $log->ip_address ?? '—' }}
                    </td>
                    <td class="text-center">
                        @if($log->properties)
                        <button type="button"
                                class="btn btn-xs btn-outline-secondary js-audit-diff"
                                title="View changes"
                                data-url="{{ route('audit-log.detail', $log) }}">
                            <i class="bi bi-code-square"></i>
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="card-footer py-2 d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }}
        </div>
        <div>
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </div>
    @endif
    @endif
</div>

{{-- Diff / Detail Modal --}}
<div class="modal fade" id="auditDiffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1">
                <h6 class="modal-title fw-semibold" id="auditDiffTitle">
                    <i class="bi bi-code-square me-2 text-primary"></i>Change Details
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2" id="auditDiffBody">
                <div class="text-center py-3">
                    <span class="spinner-border spinner-border-sm"></span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .btn-xs { padding: .18rem .5rem; font-size: .72rem; }
    .table > :not(caption) > * > td { padding: .45rem .6rem; vertical-align: middle; }
    .audit-diff-table td:first-child { width: 160px; font-size: .75rem; color: #6b7280; }
    .audit-diff-old { background: #fee2e2; border-radius: 3px; padding: 1px 4px; color: #991b1b; font-family: monospace; font-size: .75rem; word-break: break-all; }
    .audit-diff-new { background: #dcfce7; border-radius: 3px; padding: 1px 4px; color: #14532d; font-family: monospace; font-size: .75rem; word-break: break-all; }
    .audit-diff-val { font-family: monospace; font-size: .75rem; word-break: break-all; background: #f3f4f6; border-radius: 3px; padding: 1px 4px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const modal   = new bootstrap.Modal(document.getElementById('auditDiffModal'));
    const titleEl = document.getElementById('auditDiffTitle');
    const bodyEl  = document.getElementById('auditDiffBody');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-audit-diff');
        if (!btn) return;

        bodyEl.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        titleEl.innerHTML = '<i class="bi bi-code-square me-2 text-primary"></i>Change Details';
        modal.show();

        fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                titleEl.innerHTML = '<i class="bi bi-code-square me-2 text-primary"></i>'
                    + escHtml(data.description || 'Change Details');

                const props = data.properties;
                if (!props) {
                    bodyEl.innerHTML = '<p class="text-muted small mb-0">No property details stored.</p>';
                    return;
                }

                let html = '';

                // Updated diff: old / new
                if (props.old && props.new) {
                    const fields = [...new Set([...Object.keys(props.old), ...Object.keys(props.new)])];
                    html += '<div class="small fw-semibold text-muted mb-2">Fields changed:</div>';
                    html += '<table class="table table-sm audit-diff-table mb-0"><tbody>';
                    fields.forEach(field => {
                        const oldVal = props.old[field] ?? null;
                        const newVal = props.new[field] ?? null;
                        html += '<tr>'
                            + '<td class="text-muted">' + escHtml(field) + '</td>'
                            + '<td>'
                            + (oldVal !== null ? '<span class="audit-diff-old">' + escHtml(String(oldVal)) + '</span>' : '<span class="text-muted">—</span>')
                            + '<i class="bi bi-arrow-right mx-2 text-muted" style="font-size:.7rem"></i>'
                            + (newVal !== null ? '<span class="audit-diff-new">' + escHtml(String(newVal)) + '</span>' : '<span class="text-muted">—</span>')
                            + '</td>'
                            + '</tr>';
                    });
                    html += '</tbody></table>';
                }
                // Created snapshot
                else if (props.attributes) {
                    html += '<div class="small fw-semibold text-muted mb-2">Record snapshot:</div>';
                    html += '<table class="table table-sm audit-diff-table mb-0"><tbody>';
                    Object.entries(props.attributes).forEach(([field, val]) => {
                        if (val === null || val === '') return;
                        html += '<tr>'
                            + '<td>' + escHtml(field) + '</td>'
                            + '<td><span class="audit-diff-val">' + escHtml(String(val)) + '</span></td>'
                            + '</tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html = '<pre class="small bg-light p-2 rounded mb-0" style="font-size:.72rem;max-height:400px;overflow:auto">'
                        + escHtml(JSON.stringify(props, null, 2)) + '</pre>';
                }

                bodyEl.innerHTML = html;
            })
            .catch(() => {
                bodyEl.innerHTML = '<div class="alert alert-danger small mb-0">Failed to load details.</div>';
            });
    });

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
@endpush
