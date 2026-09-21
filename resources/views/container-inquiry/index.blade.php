@extends('layouts.app')

@section('title', 'Container Inquiry')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Container Inquiry</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-search me-2 text-primary"></i>Container Inquiry</h4>
        {{-- Names what can actually be searched. The old subtitle described the
             drill-down only, so the gate-movement search nobody could find was
             not advertised by the page that holds it. --}}
        <p class="text-muted mb-0 small">
            Search gate movements by date, customer, job, BL, vessel, vehicle or driver &mdash;
            then open a container for its full job, repair and billing history
        </p>
    </div>
    @if($searched && $movements && $movements->total() > 0)
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('container-inquiry.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        @if(\App\Support\Export\TabularExport::supports('xlsx'))
        <a href="{{ route('container-inquiry.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
           class="btn btn-outline-success btn-sm"
           title="Every column, including M&amp;R status, stage age, export readiness and holds.">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        @endif
        @if(\App\Support\Export\GateMovementWorkbook::available())
        {{-- The other audience for the same rows: both trucks, both drivers,
             the BL and the day count, with no M&R columns in the way. --}}
        <a href="{{ route('container-inquiry.gate-log', request()->query()) }}"
           class="btn btn-outline-primary btn-sm"
           title="Both gates with vehicle, driver, BL and days in yard — for gate and damage queries.">
            <i class="bi bi-truck me-1"></i>Gate Movements (Excel)
        </a>
        @endif
    </div>
    @endif
</div>

{{-- Search Form --}}
<div class="card content-card mb-3">
    <div class="card-header py-2 fw-semibold small"><i class="bi bi-funnel me-1"></i>Search Parameters</div>
    <div class="card-body py-3">
        <form method="GET" action="{{ route('container-inquiry.index') }}" id="inquirySearchForm">
            <div class="row g-2">

                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Container Number</label>
                    <select name="container_no" id="containerNoSelect" class="form-select form-select-sm" style="width:100%">
                        @if(!empty($filters['container_no']))
                        <option value="{{ $filters['container_no'] }}" selected>{{ $filters['container_no'] }}</option>
                        @endif
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2 s2-code" data-s2-sel="name">
                        <option value="">All Customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}"
                                {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>
                                {{ $c->code }} – {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1">Job Type</label>
                    <select name="job_type_code" class="form-select form-select-sm select2">
                        <option value="">All Job Types</option>
                        @foreach($jobTypes as $jt)
                            <option value="{{ $jt->job_type_code }}"
                                {{ ($filters['job_type_code'] ?? '') === $jt->job_type_code ? 'selected' : '' }}>
                                {{ $jt->job_type_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1">Job Number</label>
                    <input type="text" name="job_no" class="form-control form-control-sm"
                           placeholder="e.g. YD-ER-00001"
                           value="{{ $filters['job_no'] ?? '' }}">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ $filters['date_from'] ?? '' }}">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ $filters['date_to'] ?? '' }}">
                </div>

                {{-- Which gate the date range is measured against. The labels
                     above used to say "Gate In From/To" because that is all the
                     filter could do; a box that arrived in June and left in
                     August was absent from an August search. --}}
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">Movement</label>
                    <select name="movement_scope" class="form-select form-select-sm">
                        @foreach(\App\Services\ContainerInquiryService::MOVEMENT_SCOPES as $value => $label)
                            <option value="{{ $value }}"
                                {{ ($filters['movement_scope'] ?? 'either') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- M&R status filters — plain indexed columns on gate_movements,
                 so these cost no more than the date range does. --}}
            <div class="row g-2 mt-1">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm mb-1">M&amp;R Status</label>
                    <select name="mr_status" class="form-select form-select-sm select2">
                        <option value="">All statuses</option>
                        @foreach($mrStatusesByLane as $lane => $codes)
                            <optgroup label="{{ \App\Support\MrStatusCatalogue::laneLabel($lane === 'general' ? null : $lane) }}">
                                @foreach($codes as $code => $label)
                                    <option value="{{ $code }}"
                                        {{ ($filters['mr_status'] ?? '') === $code ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Stage</label>
                    <select name="mr_status_group" class="form-select form-select-sm select2">
                        <option value="">Any stage</option>
                        @foreach($mrStatusGroups as $key => $label)
                            <option value="{{ $key }}"
                                {{ ($filters['mr_status_group'] ?? '') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5 d-flex align-items-end">
                    <div class="form-check form-switch me-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="filterExportReady" name="export_ready" value="1"
                               {{ !empty($filters['export_ready']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterExportReady"
                               title="Filters on the container's state today, not on what that visit was doing.">
                            Export ready <span class="text-muted">(now)</span>
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="filterOnHold" name="on_hold" value="1"
                               {{ !empty($filters['on_hold']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="filterOnHold"
                               title="Filters on the container's state today, not on what that visit was doing.">
                            On hold <span class="text-muted">(now)</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Advanced filters toggle --}}
            @php
                $hasAdvanced = !empty($filters['vessel_name']) || !empty($filters['voyage_no'])
                    || !empty($filters['bl_number']) || !empty($filters['seal_no']) || !empty($filters['eir_ref'])
                    || !empty($filters['vehicle_plate']) || !empty($filters['driver_name']);
            @endphp
            <div class="row g-2 mt-1">
                <div class="col-12">
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted"
                            data-bs-toggle="collapse" data-bs-target="#advancedFilters"
                            aria-expanded="{{ $hasAdvanced ? 'true' : 'false' }}">
                        <i class="bi bi-sliders me-1"></i>Advanced Filters
                        <i class="bi bi-chevron-down ms-1" style="font-size:.7rem"></i>
                    </button>
                </div>
            </div>

            <div class="collapse {{ $hasAdvanced ? 'show' : '' }}" id="advancedFilters">
                <div class="row g-2 mt-1 pt-2 border-top">
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Vessel Name</label>
                        <input type="text" name="vessel_name" class="form-control form-control-sm"
                               placeholder="e.g. Ever Given"
                               value="{{ $filters['vessel_name'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Voyage No</label>
                        <input type="text" name="voyage_no" class="form-control form-control-sm"
                               placeholder="e.g. 0123W"
                               value="{{ $filters['voyage_no'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">BL Number</label>
                        <input type="text" name="bl_number" class="form-control form-control-sm"
                               placeholder="e.g. MAEU123456"
                               value="{{ $filters['bl_number'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Seal No</label>
                        <input type="text" name="seal_no" class="form-control form-control-sm"
                               placeholder="e.g. SL00123"
                               value="{{ $filters['seal_no'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">EIR Ref (Gate ID)</label>
                        <input type="number" name="eir_ref" class="form-control form-control-sm"
                               placeholder="Gate movement ID"
                               value="{{ $filters['eir_ref'] ?? '' }}">
                    </div>

                    {{-- Both are matched against either gate: the truck that
                         delivered a box and the one that collected it are
                         different vehicles, and either may be the one being
                         looked for after a gate dispute or a damage claim. --}}
                    <div class="col-12 col-md-2">
                        <label class="form-label form-label-sm mb-1">Vehicle No</label>
                        <input type="text" name="vehicle_plate" class="form-control form-control-sm text-uppercase"
                               placeholder="e.g. ABC1234"
                               value="{{ $filters['vehicle_plate'] ?? '' }}">
                        <div class="form-text" style="font-size:.7rem">Matches from the start of the plate.</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">Driver Name</label>
                        <input type="text" name="driver_name" class="form-control form-control-sm"
                               placeholder="Any part of the name"
                               value="{{ $filters['driver_name'] ?? '' }}">
                    </div>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                    <a href="{{ route('container-inquiry.index') }}" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@if(!$searched)
{{-- Landing hint --}}
<div class="text-center py-5 text-muted">
    <i class="bi bi-search" style="font-size:3rem;opacity:.3"></i>
    <p class="mt-3 mb-0 fw-semibold">Enter search parameters above to find container history</p>
    <p class="small mt-1">Search by container number, customer, job type, or date range</p>
</div>

@elseif($movements->isEmpty())
<div class="text-center py-5 text-muted">
    <i class="bi bi-inbox" style="font-size:3rem;opacity:.3"></i>
    <p class="mt-3 mb-0 fw-semibold">No records found for the given criteria</p>
    <p class="small mt-1">Try broadening your search parameters</p>
</div>

@else
{{-- Result count --}}
<div class="d-flex align-items-center justify-content-between mb-2">
    <p class="text-muted small mb-0">
        Showing <strong>{{ $movements->firstItem() }}–{{ $movements->lastItem() }}</strong>
        of <strong>{{ number_format($movements->total()) }}</strong> gate-in records
    </p>
    <p class="text-muted small mb-0">
        Page {{ $movements->currentPage() }} of {{ $movements->lastPage() }}
    </p>
</div>

{{-- Results Table --}}
<div class="card content-card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Container No</th>
                    <th>Customer</th>
                    <th>Job No</th>
                    <th>Job Type</th>
                    <th>Gate In</th>
                    <th>Gate Out</th>
                    <th class="text-end">Days</th>
                    <th>BL No</th>
                    <th>Cargo</th>
                    <th>Job Status</th>
                    <th>M&amp;R Status</th>
                    <th class="text-center">Size</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($movements as $m)
                @php
                    $yardJob = $m->yardJob;
                    $jobStatusClass = match(optional($yardJob)->status) {
                        'open'      => 'bg-success-subtle text-success',
                        'closed'    => 'bg-secondary-subtle text-secondary',
                        'cancelled' => 'bg-danger-subtle text-danger',
                        default     => 'bg-light text-muted',
                    };
                    // The status is a column on this row, so the badge costs no
                    // query. Lane-aware wording: a wash never reads as a repair.
                    $mrCode  = $m->mr_status;
                    $mrLabel = $mrCode ? \App\Support\MrStatusCatalogue::label($mrCode, $m->mr_lane) : null;
                    $mrBadge = $mrCode ? \App\Support\MrStatusCatalogue::badgeClass($mrCode) : '';

                    $holds       = $m->container?->activeHolds ?? collect();
                    $ptiLapsed   = (bool) $m->container?->mrStatusHasExpired();

                    $matchedGateOut = $gateOutMap[$m->id] ?? null;

                    // Elapsed time, not billable days: a box in and out the same
                    // day is 0 here and 1 chargeable day on the invoice.
                    // DaysInYard is the one calculation five screens share.
                    $days = \App\Support\DaysInYard::between(
                        $m->gate_in_time,
                        $matchedGateOut?->gate_out_time,
                    );
                @endphp
                <tr>
                    <td class="ps-3 fw-semibold font-monospace">
                        <a href="{{ route('container-inquiry.show', $m->container_no) }}"
                           class="text-decoration-none text-primary">
                            {{ $m->container_no }}
                        </a>
                    </td>
                    <td><x-movement-party :movement="$m" /></td>
                    <td class="font-monospace">
                        @if($yardJob)
                            <span class="badge bg-light text-dark border">{{ $yardJob->job_no }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($yardJob?->jobType)
                            <span class="badge bg-info-subtle text-info">{{ $yardJob->jobType->type_short_code }}</span>
                            {{ $yardJob->jobType->job_type_name }}
                        @else
                            <span class="text-muted">{{ $m->job_type_code ?? '-' }}</span>
                        @endif
                    </td>
                    {{-- Each gate's vehicle and driver sit under that gate's
                         time rather than in columns of their own: the truck that
                         delivered a box and the one that collected it are
                         different, and separating them across the table is how
                         they get read as the same. --}}
                    <td class="text-nowrap">
                        {{ $m->gate_in_time?->format('d M Y H:i') ?? '-' }}
                        @if($m->vehicle_plate || $m->driver_name)
                            <div class="text-muted" style="font-size:.7rem">
                                @if($m->vehicle_plate)<i class="bi bi-truck me-1"></i>{{ $m->vehicle_plate }}@endif
                                @if($m->driver_name)<span class="ms-1">{{ $m->driver_name }}</span>@endif
                            </div>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if($matchedGateOut?->gate_out_time)
                            {{ $matchedGateOut->gate_out_time->format('d M Y H:i') }}
                            @if($matchedGateOut->vehicle_plate || $matchedGateOut->driver_name)
                                <div class="text-muted" style="font-size:.7rem">
                                    @if($matchedGateOut->vehicle_plate)<i class="bi bi-truck me-1"></i>{{ $matchedGateOut->vehicle_plate }}@endif
                                    @if($matchedGateOut->driver_name)<span class="ms-1">{{ $matchedGateOut->driver_name }}</span>@endif
                                </div>
                            @endif
                        @else
                            <span class="badge bg-success-subtle text-success" style="font-size:.65rem">In Yard</span>
                        @endif
                    </td>
                    <td class="text-end">{{ $days === null ? '-' : $days }}</td>
                    <td class="font-monospace" style="font-size:.75rem">{{ $m->bl_number ?: '-' }}</td>
                    <td>
                        @if($m->cargo_status)
                            <span class="badge {{ $m->cargo_status === 'laden' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}"
                                  style="font-size:.7rem">{{ ucfirst($m->cargo_status) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($yardJob)
                            <span class="badge {{ $jobStatusClass }} text-uppercase" style="font-size:.7rem">
                                {{ $yardJob->status }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($mrCode)
                            <span class="badge {{ $mrBadge }}" style="font-size:.7rem">{{ $mrLabel }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif

                        {{-- Modifiers: true alongside the status, not instead of
                             it. A box can be under repair AND under customs hold,
                             and the second half is what matters at the gate. --}}
                        @foreach($holds as $hold)
                            <span class="badge bg-dark-subtle text-dark border ms-1" style="font-size:.65rem"
                                  title="{{ \App\Models\ContainerHold::TYPES[$hold->hold_type] ?? 'Hold' }}">
                                <i class="bi bi-lock-fill"></i>
                                {{ \App\Models\ContainerHold::TYPES[$hold->hold_type] ?? ucfirst($hold->hold_type) }}
                            </span>
                        @endforeach

                        @if($ptiLapsed)
                            <span class="badge bg-warning-subtle text-warning border ms-1" style="font-size:.65rem"
                                  title="The PTI this status relied on has since expired.">
                                PTI expired
                            </span>
                        @endif
                    </td>
                    <td class="text-center">{{ $m->size ? $m->size . 'ft' : '-' }}</td>
                    <td class="pe-3 text-center">
                        <a href="{{ route('container-inquiry.show', $m->container_no) }}"
                           class="btn btn-outline-primary"
                           style="padding:.15rem .4rem;font-size:.72rem;line-height:1.4"
                           title="View Full History"
                           data-bs-toggle="tooltip" data-bs-placement="left">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
<div class="d-flex justify-content-center">
    {{ $movements->appends(request()->query())->links() }}
</div>

@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof $.fn.select2 === 'undefined') return;

    // Container Number — AJAX autocomplete
    $('#containerNoSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type to search…',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route('container-inquiry.autocomplete') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) {
                return {
                    results: data.map(function (no) {
                        return { id: no, text: no };
                    })
                };
            },
            cache: true
        },
        templateResult: function (item) {
            if (!item.id) return item.text;
            return $('<span class="font-monospace fw-semibold">' + item.text + '</span>');
        }
    });

    // Activate Bootstrap tooltips on the eye buttons
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
@endpush
