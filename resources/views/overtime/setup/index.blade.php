@extends('layouts.app')

@section('title', 'Overtime Setup')

@section('breadcrumb')
    <li class="breadcrumb-item">Overtime</li>
    <li class="breadcrumb-item active">Setup</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-sliders me-2 text-primary"></i>Overtime Setup</h4>
        <p class="text-muted mb-0 small">
            What the overtime engine is reading right now — working hours, holiday calendar and tariff rates.
            Everything here is editable; nothing is hard-coded.
        </p>
    </div>
    @can('settings.company.view')
    <a href="{{ route('settings.company.index') }}#otPolicy" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-toggles me-1"></i>Enforcement setting
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Configuration gaps that would misbill or block the gate --}}
@foreach($issues as $issue)
<div class="alert alert-{{ $issue['level'] }} d-flex align-items-start gap-2 py-2 small">
    <i class="bi bi-{{ $issue['level'] === 'danger' ? 'exclamation-octagon' : 'exclamation-triangle' }} mt-1"></i>
    <div class="flex-grow-1">{{ $issue['text'] }}</div>
    <a href="{{ isset($issue['param']) ? route($issue['route'], $issue['param']) : route($issue['route']) }}"
       class="btn btn-sm btn-outline-{{ $issue['level'] }} flex-shrink-0 py-0">{{ $issue['cta'] }}</a>
</div>
@endforeach

{{-- Enforcement status --}}
<div class="alert alert-{{ $policyOn ? 'success' : 'secondary' }} d-flex align-items-center gap-2 py-2 small">
    <i class="bi bi-{{ $policyOn ? 'shield-check' : 'shield-slash' }}"></i>
    <div class="flex-grow-1">
        <strong>Gate-in enforcement is {{ $policyOn ? 'ON' : 'OFF' }}.</strong>
        @if($policyOn)
            An out-of-hours gate-in is blocked unless a valid, paid OT receipt for that BL is selected.
        @else
            Overtime is still calculated and receipts can be issued, but out-of-hours gate-ins are not blocked.
            Turn it on in Company Settings when you are ready to enforce.
        @endif
    </div>
</div>

<div class="row g-3">
    {{-- Working hours --}}
    <div class="col-12 col-xl-4">
        <div class="card content-card h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock me-2 text-primary"></i>Working Hours</span>
                <a href="{{ route('overtime.working-hours.index') }}" class="btn btn-sm btn-outline-secondary py-0">Manage</a>
            </div>
            <div class="card-body">
                @if($workingSet)
                    <div class="small text-muted mb-2">
                        Active set: <span class="fw-semibold text-body">{{ $workingSet->name }}</span>
                        @if($workingSet->is_default)<span class="badge bg-primary-subtle text-primary border ms-1">Default</span>@endif
                    </div>
                    <table class="table table-sm mb-0 small">
                        <tbody>
                        @foreach(\App\Models\WeeklyWorkingHour::DAYS as $key => $label)
                            @php $day = $workingDays->get($key); @endphp
                            <tr>
                                <td class="ps-0 text-muted">{{ $label }}</td>
                                <td class="text-end pe-0">
                                    @if($day && $day->is_regular_working_day && $day->normal_start_time)
                                        <span class="font-monospace">{{ $day->windowLabel() }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border">Closed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted small mb-0">No active working-hour set.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Holiday calendar --}}
    <div class="col-12 col-xl-4">
        <div class="card content-card h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar-event me-2 text-primary"></i>Holiday Calendar</span>
                <a href="{{ route('overtime.holidays.index') }}" class="btn btn-sm btn-outline-secondary py-0">Manage</a>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">
                    <span class="fw-semibold text-body">{{ $holidaysYear }}</span> active holiday(s) in {{ now()->year }}
                </div>
                @if($holidaysNext->isNotEmpty())
                    <div class="small text-muted mb-1">Next up</div>
                    <ul class="list-unstyled small mb-0">
                        @foreach($holidaysNext as $h)
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span>
                                {{ $h->holiday_name }}
                                @if($h->is_mercantile)<i class="bi bi-briefcase text-primary ms-1" title="Mercantile"></i>@endif
                            </span>
                            <span class="text-muted font-monospace">{{ $h->holiday_date->format('d M') }}</span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted small mb-0">No upcoming holidays configured.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Tariff --}}
    <div class="col-12 col-xl-4">
        <div class="card content-card h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-cash-coin me-2 text-primary"></i>OT Tariff</span>
                <a href="{{ route('overtime.tariffs.index') }}" class="btn btn-sm btn-outline-secondary py-0">Manage</a>
            </div>
            <div class="card-body">
                @if($effective)
                    <div class="small mb-1">
                        <span class="font-monospace fw-semibold">{{ $effective->version_code }}</span>
                        <span class="badge bg-success ms-1">Effective</span>
                    </div>
                    <div class="small text-muted">{{ $effective->name }}</div>
                    <hr class="my-2">
                    <div class="small text-muted">
                        From {{ $effective->effective_from->format('d M Y') }}
                        @if($effective->effective_to) to {{ $effective->effective_to->format('d M Y') }} @else onwards @endif
                    </div>
                    <div class="small text-muted">Currency: <span class="fw-semibold text-body">{{ $effective->currency }}</span></div>
                    <div class="small text-muted">{{ $ruleCount }} active rate rule(s)</div>
                    <a href="{{ route('overtime.tariffs.show', $effective) }}" class="btn btn-sm btn-outline-primary py-0 mt-2">View rates</a>
                @else
                    <p class="text-muted small mb-0">
                        No tariff version is effective today.
                        {{ $versionCount }} version(s) exist in total.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Resolver dry-run --}}
<div class="card content-card mt-3">
    <div class="card-header py-2">
        <i class="bi bi-search me-2 text-primary"></i>Test the Configuration
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Pick any date and time to see exactly what the engine would decide for a gate movement —
            the day category, whether it is overtime, and which rate would apply.
        </p>
        <div class="row g-2 align-items-end mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Date</label>
                <input type="date" id="otPreviewDate" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Time</label>
                <input type="time" id="otPreviewTime" class="form-control form-control-sm" value="18:00">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Movement</label>
                <select id="otPreviewMovement" class="form-select form-select-sm">
                    <option value="gate_in">Gate-In</option>
                    <option value="gate_out">Gate-Out</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="button" id="otPreviewBtn" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-play-fill me-1"></i>Check
                </button>
            </div>
        </div>
        <div id="otPreviewResult" class="d-none"></div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('otPreviewBtn');
    const out = document.getElementById('otPreviewResult');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const payload = {
            date: document.getElementById('otPreviewDate').value,
            time: document.getElementById('otPreviewTime').value,
            movement_type: document.getElementById('otPreviewMovement').value,
        };
        if (!payload.date || !payload.time) return;

        btn.disabled = true;
        out.classList.remove('d-none');
        out.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Checking…</div>';

        fetch(@json(route('overtime.setup.preview')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.ok ? r.json() : Promise.reject(r))
        .then(render)
        .catch(() => { out.innerHTML = '<div class="alert alert-danger py-2 small mb-0">Could not evaluate that date/time.</div>'; })
        .finally(() => { btn.disabled = false; });
    });

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    function render(d) {
        let html = '<div class="d-flex flex-wrap gap-2 align-items-center mb-2">'
            + '<span class="badge bg-dark">' + esc(d.at) + '</span>'
            + '<span class="badge bg-primary-subtle text-primary border">' + esc(d.category_label) + '</span>'
            + (d.is_overtime
                ? '<span class="badge bg-warning text-dark">Overtime</span>'
                : '<span class="badge bg-success">Normal working hours</span>');
        if (d.holiday) html += '<span class="badge bg-info-subtle text-info border"><i class="bi bi-calendar-event me-1"></i>' + esc(d.holiday) + '</span>';
        html += '</div>';

        if (!d.is_overtime) {
            html += '<div class="alert alert-success py-2 small mb-0">'
                 +  'Inside normal working hours — no overtime receipt is needed.</div>';
        } else if (d.unconfigured) {
            html += '<div class="alert alert-danger py-2 small mb-0">'
                 +  '<strong>Unconfigured overtime period.</strong> This time is outside working hours but no tariff rule covers it. '
                 +  'With enforcement on, a gate-in here is blocked pending supervisor override or a new rule.</div>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
                 +  '<thead class="table-light"><tr><th>Rule</th><th>Period</th><th>Description</th>'
                 +  '<th>Valid From</th><th>Valid To</th><th class="text-end">Rate</th></tr></thead><tbody>';
            d.windows.forEach(w => {
                html += '<tr>'
                     +  '<td class="font-monospace small">' + esc(w.rule) + '</td>'
                     +  '<td><span class="badge bg-secondary">' + esc(w.period) + '</span></td>'
                     +  '<td class="small">' + esc(w.label) + '</td>'
                     +  '<td class="small text-muted">' + esc(w.valid_from) + '</td>'
                     +  '<td class="small text-muted">' + esc(w.valid_to) + '</td>'
                     +  '<td class="text-end fw-semibold">' + esc(w.currency) + ' ' + esc(w.rate) + '</td>'
                     +  '</tr>';
            });
            html += '</tbody></table></div>'
                 +  '<div class="form-text mt-1">The operator picks one of these when generating the receipt.</div>';
        }

        out.innerHTML = html;
    }
})();
</script>
@endpush

@endsection
