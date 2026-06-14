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
        <p class="text-muted mb-0 small">Search all container history, job cycles, and workflow activities</p>
    </div>
    @if($searched && $movements && $movements->total() > 0)
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('container-inquiry.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
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
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-box-seam"></i></span>
                        <input type="text" name="container_no" class="form-control form-control-sm text-uppercase"
                               placeholder="e.g. TCKU1234567"
                               value="{{ $filters['container_no'] ?? '' }}"
                               maxlength="11" autocomplete="off" style="text-transform:uppercase">
                    </div>
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
                    <select name="job_type_code" class="form-select form-select-sm">
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
                    <label class="form-label form-label-sm mb-1">Gate In From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ $filters['date_from'] ?? '' }}">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm mb-1">Gate In To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ $filters['date_to'] ?? '' }}">
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
                    <th>Job Status</th>
                    <th>Condition</th>
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
                    $condClass = match($m->condition) {
                        'sound'          => 'text-success',
                        'damaged'        => 'text-danger',
                        'require_repair' => 'text-warning',
                        default          => 'text-muted',
                    };
                @endphp
                <tr>
                    <td class="ps-3 fw-semibold font-monospace">
                        <a href="{{ route('container-inquiry.show', $m->container_no) }}"
                           class="text-decoration-none text-primary">
                            {{ $m->container_no }}
                        </a>
                    </td>
                    <td>{{ optional($m->customer)->name ?? '—' }}</td>
                    <td class="font-monospace">
                        @if($yardJob)
                            <span class="badge bg-light text-dark border">{{ $yardJob->job_no }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($yardJob?->jobType)
                            <span class="badge bg-info-subtle text-info">{{ $yardJob->jobType->type_short_code }}</span>
                            {{ $yardJob->jobType->job_type_name }}
                        @else
                            <span class="text-muted">{{ $m->job_type_code ?? '—' }}</span>
                        @endif
                    </td>
                    <td class="text-nowrap">{{ $m->gate_in_time?->format('d M Y H:i') ?? '—' }}</td>
                    <td class="text-nowrap text-muted">
                        {{-- Gate-out time if any --}}
                        @if($yardJob?->completed_at)
                            {{ $yardJob->completed_at->format('d M Y') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($yardJob)
                            <span class="badge {{ $jobStatusClass }} text-uppercase" style="font-size:.7rem">
                                {{ $yardJob->status }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="{{ $condClass }}">
                        {{ ucfirst(str_replace('_', ' ', $m->condition ?? '—')) }}
                    </td>
                    <td class="text-center">{{ $m->size ? $m->size . 'ft' : '—' }}</td>
                    <td class="pe-3">
                        <a href="{{ route('container-inquiry.show', $m->container_no) }}"
                           class="btn btn-xs btn-outline-primary" title="View Full History">
                            <i class="bi bi-eye me-1"></i>History
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
