@extends('layouts.app')

@section('title', 'Job Margin Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Job Margin</li>
@endsection

@section('content')

@php
    $fmt = fn ($n) => number_format((float) $n, 2);
    $signed = function ($n) use ($fmt) {
        return $n < 0 ? '(' . $fmt(abs($n)) . ')' : $fmt($n);
    };
    // preserve current filters on export/print links
    $qs = array_filter([
        'customer_id'   => $filters['customer_id'] ?? null,
        'job_type_id'   => $filters['job_type_id'] ?? null,
        'status'        => $filters['status'] ?? null,
        'from'          => $filters['from'] ?? null,
        'to'            => $filters['to'] ?? null,
        'search'        => $filters['search'] ?? null,
        'sort'          => $filters['sort'] ?? null,
        'include_empty' => ! empty($filters['include_empty']) ? 1 : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-clipboard-data me-2 text-primary"></i>Job Margin Report</h4>
        <p class="text-muted small mb-0">
            Per container-visit Revenue &minus; Cost = Margin · realized from the posted, job-tagged GL
        </p>
    </div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <a href="{{ route('finance.reports.job-margin', array_merge($qs, ['export' => 'csv'])) }}"
           class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

{{-- ── Filters ── --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('finance.reports.job-margin') }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Customer</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(($filters['customer_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Job Type</label>
                <select name="job_type_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($jobTypes as $t)
                        <option value="{{ $t->id }}" @selected(($filters['job_type_id'] ?? null) == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $v)
                        <option value="{{ $k }}" @selected(($filters['status'] ?? null) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Job created from</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">to</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Job No</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search…" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1 text-muted">Sort by</label>
                <select name="sort" class="form-select form-select-sm">
                    @foreach(['revenue' => 'Revenue', 'margin' => 'Margin', 'cost' => 'Cost', 'job' => 'Newest'] as $k => $v)
                        <option value="{{ $k }}" @selected(($filters['sort'] ?? 'revenue') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-center pt-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="include_empty" value="1" id="include_empty"
                           @checked(! empty($filters['include_empty']))>
                    <label class="form-check-label small text-muted" for="include_empty">Show jobs with no activity</label>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="{{ route('finance.reports.job-margin') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- ── Summary tiles ── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Realized Revenue</div>
            <div class="fw-bold fs-5 font-monospace text-success">{{ $fmt($totals['realized_revenue']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Realized Cost</div>
            <div class="fw-bold fs-5 font-monospace text-danger">{{ $fmt($totals['realized_cost']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3 {{ $totals['realized_margin'] >= 0 ? 'border-success' : 'border-danger' }}" style="border-left:4px solid">
            <div class="text-muted small">Realized Margin</div>
            <div class="fw-bold fs-5 font-monospace {{ $totals['realized_margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $signed($totals['realized_margin']) }}
                @if($totals['margin_pct'] !== null)
                    <span class="fs-6 text-muted">({{ $totals['margin_pct'] }}%)</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Pending Rev / Cost</div>
            <div class="fw-bold fs-6 font-monospace">
                <span class="text-success">{{ $fmt($totals['pending_revenue']) }}</span>
                <span class="text-muted"> / </span>
                <span class="text-danger">{{ $fmt($totals['pending_cost']) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- ── Table ── --}}
<div class="card content-card" id="printable">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small align-middle">
                <thead>
                    <tr class="table-light">
                        <th class="ps-3 py-2">Job</th>
                        <th>Customer</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Margin</th>
                        <th class="text-end">%</th>
                        <th class="text-end pe-3">Pending R / C</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    @php $j = $r['job']; $m = $r['realized_margin']; @endphp
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('yard.jobs.show', $j) }}" class="fw-semibold text-decoration-none font-monospace">{{ $j->job_no }}</a>
                            <div class="text-muted" style="font-size:.7rem">{{ $j->jobType->name ?? $j->job_type_code }}</div>
                        </td>
                        <td>{{ $j->customer->name ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge {{ \App\Models\YardJob::statusBadgeClass($j->status) }}" style="font-size:.68rem">
                                {{ \App\Models\YardJob::statusLabel($j->status) }}
                            </span>
                        </td>
                        <td class="text-end font-monospace text-success">{{ $fmt($r['realized_revenue']) }}</td>
                        <td class="text-end font-monospace text-danger">{{ $fmt($r['realized_cost']) }}</td>
                        <td class="text-end font-monospace fw-semibold {{ $m >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $signed($m) }}
                        </td>
                        <td class="text-end font-monospace {{ ($r['margin_pct'] ?? 0) < 0 ? 'text-danger' : 'text-muted' }}">
                            {{ $r['margin_pct'] === null ? '—' : $r['margin_pct'] . '%' }}
                        </td>
                        <td class="text-end pe-3 font-monospace" style="font-size:.72rem">
                            @if($r['pending_revenue'] > 0 || $r['pending_cost'] > 0)
                                <span class="text-success">{{ $fmt($r['pending_revenue']) }}</span>
                                <span class="text-muted"> / </span>
                                <span class="text-danger">{{ $fmt($r['pending_cost']) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4 fst-italic">
                            No jobs match the current filters. Try widening the date range or enabling
                            &ldquo;Show jobs with no activity&rdquo;.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-bold border-top border-2">
                        <td class="ps-3 py-2" colspan="3">TOTAL &middot; {{ $count }} {{ Str::plural('job', $count) }}</td>
                        <td class="text-end font-monospace text-success">{{ $fmt($totals['realized_revenue']) }}</td>
                        <td class="text-end font-monospace text-danger">{{ $fmt($totals['realized_cost']) }}</td>
                        <td class="text-end font-monospace {{ $totals['realized_margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $signed($totals['realized_margin']) }}
                        </td>
                        <td class="text-end font-monospace text-muted">
                            {{ $totals['margin_pct'] === null ? '—' : $totals['margin_pct'] . '%' }}
                        </td>
                        <td class="text-end pe-3 font-monospace" style="font-size:.72rem">
                            <span class="text-success">{{ $fmt($totals['pending_revenue']) }}</span>
                            <span class="text-muted"> / </span>
                            <span class="text-danger">{{ $fmt($totals['pending_cost']) }}</span>
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-2 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Realized</strong> figures come from posted GL entries tagged to each job (income &rarr; revenue,
    expense &rarr; cost). <strong>Pending</strong> is draft AR / AP / vouchers not yet posted — shown for the
    pipeline but never counted in margin.
</p>

<div class="print-footer d-none d-print-block text-center text-muted mt-4 pt-3 border-top" style="font-size:.78rem;">
    &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM Software' }}
    &nbsp;&middot;&nbsp; Printed {{ now()->format('d M Y H:i') }}
</div>

@push('styles')
<style>
@media print {
    .page-header .btn, .card.mb-3 form, .sidebar, nav { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>
@endpush

@endsection
