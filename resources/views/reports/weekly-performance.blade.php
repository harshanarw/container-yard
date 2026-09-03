@extends('layouts.app')

@section('title', 'Weekly Performance')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Weekly Performance</li>
@endsection

@push('styles')
<style>
    /* The grid is as wide as the range is long, so it scrolls inside its own
       box. The page itself must never scroll sideways. */
    .wp-scroll { overflow-x: auto; }

    .wp-grid { border-collapse: separate; border-spacing: 0; font-size: .8rem; white-space: nowrap; }
    .wp-grid th, .wp-grid td { border: 1px solid var(--bs-border-color); padding: .3rem .45rem; }
    .wp-grid thead th { text-align: center; vertical-align: middle; background: var(--bs-tertiary-bg); font-weight: 600; }

    /* The customer and row-label columns stay put while the weeks scroll past.
       Without this the reader loses which line they are on around week three. */
    .wp-grid .wp-name, .wp-grid .wp-label { position: sticky; background: var(--bs-body-bg); z-index: 2; }
    .wp-grid .wp-name  { left: 0;      min-width: 190px; }
    .wp-grid .wp-label { left: 190px;  min-width: 108px; }
    .wp-grid thead .wp-name, .wp-grid thead .wp-label { z-index: 3; background: var(--bs-tertiary-bg); }

    .wp-grid td.wp-n { text-align: center; font-variant-numeric: tabular-nums; min-width: 40px; }
    .wp-grid .wp-band-start { border-left-width: 2px; }
    .wp-grid tbody tr.wp-pair-end td { border-bottom-width: 2px; }
    .wp-grid tfoot td { font-weight: 600; background: var(--bs-tertiary-bg); }
    .wp-grid tfoot tr.wp-grand td { border-top-width: 2px; }
    .wp-quiet .wp-name, .wp-quiet .wp-label { color: var(--bs-secondary-color); font-weight: 400; }
    .wp-total-band { background: var(--bs-secondary-bg); }

    @media print {
        #sidebar, #topbar, .no-print { display: none !important; }
        #main-content { margin: 0 !important; padding: 0 !important; }
        .wp-scroll { overflow: visible !important; }
        .wp-grid { font-size: .62rem; }
        .wp-grid .wp-name, .wp-grid .wp-label { position: static; }
    }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-graph-up me-2 text-primary"></i>Weekly Performance</h4>
        <p class="text-muted mb-0 small">Lifts per customer, week by week, by size and cargo status</p>
    </div>
    {{-- Both carry the current filters, so what downloads is what is on screen. --}}
    <div class="d-flex flex-wrap gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        @if(\App\Support\Export\WeeklyPerformanceWorkbook::available())
        <a href="{{ route('reports.weekly-performance.export', request()->query()) }}"
           class="btn btn-outline-success btn-sm" title="The sheet as an Excel workbook">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </a>
        @endif
        <a href="{{ route('reports.weekly-performance.export.csv', request()->query()) }}"
           class="btn btn-outline-success btn-sm" title="One heading row per column, for scripts and formulas">
            <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
    </div>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────────── --}}
<div class="card content-card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reports.weekly-performance') }}">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Weeks</label>
                    <select name="week_rule" class="form-select form-select-sm">
                        @foreach($weekRules as $key => $label)
                            <option value="{{ $key }}" {{ $filters['week_rule'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2">
                        <option value="">All customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ $filters['customer_id'] == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="onlyMoved"
                               name="only_with_movements" value="1" {{ $filters['only_with_movements'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="onlyMoved">Only with movements</label>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
                    <a href="{{ route('reports.weekly-performance') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

@if(empty($data['weeks']))
    <div class="alert alert-warning small">
        That date range produces no weeks. Check that <strong>From</strong> is on or before <strong>To</strong>.
    </div>
@else

{{-- ── The sheet ───────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <strong class="small">{{ $data['title'] }}</strong>
        <span class="small text-muted">
            {{ number_format($data['movement_count']) }} lift{{ $data['movement_count'] === 1 ? '' : 's' }}
            across {{ count($data['weeks']) }} week{{ count($data['weeks']) === 1 ? '' : 's' }}
        </span>
    </div>

    <div class="card-body p-0">
        <div class="wp-scroll">
            <table class="wp-grid mb-0 w-100">
                <thead>
                    {{-- Week number, then its date range, then Empty/Laden, then sizes.
                         Four header rows, as the yard's workbook has. --}}
                    <tr>
                        <th class="wp-name" rowspan="4">CUSTOMER</th>
                        <th class="wp-label" rowspan="4"></th>
                        @foreach($data['weeks'] as $week)
                            <th colspan="{{ count($data['columns']) }}" class="wp-band-start">WEEK {{ $week['no'] }}</th>
                        @endforeach
                        <th colspan="{{ count($data['columns']) }}" class="wp-band-start wp-total-band" rowspan="2">TOTAL</th>
                    </tr>
                    <tr>
                        @foreach($data['weeks'] as $week)
                            <th colspan="{{ count($data['columns']) }}" class="wp-band-start fw-normal small">
                                {{ $week['label'] }}
                                @if($week['partial'])
                                    {{-- A clipped band is fewer days, so its counts are not
                                         comparable with a full week's. Say so rather than
                                         letting it read as a quiet week. --}}
                                    <span class="text-muted fst-italic">({{ $week['days'] }}d)</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($data['weeks'] as $week)
                            @foreach($data['statuses'] as $status)
                                <th colspan="{{ count($data['sizes']) }}"
                                    class="{{ $loop->first ? 'wp-band-start' : '' }}">{{ strtoupper($status) }}</th>
                            @endforeach
                        @endforeach
                        @foreach($data['statuses'] as $status)
                            <th colspan="{{ count($data['sizes']) }}"
                                class="wp-total-band {{ $loop->first ? 'wp-band-start' : '' }}">{{ strtoupper($status) }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($data['weeks'] as $week)
                            @foreach($data['columns'] as $key)
                                <th class="{{ $loop->first ? 'wp-band-start' : '' }}">{{ explode('_', $key)[1] }}</th>
                            @endforeach
                        @endforeach
                        @foreach($data['columns'] as $key)
                            <th class="wp-total-band {{ $loop->first ? 'wp-band-start' : '' }}">{{ explode('_', $key)[1] }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                @forelse($data['rows'] as $row)
                    @foreach([['demounting', 'Demounting'], ['mounting', 'Mounting']] as $i => $pair)
                    <tr class="{{ $row['moved'] ? '' : 'wp-quiet' }} {{ $i === 1 ? 'wp-pair-end' : '' }}">
                        @if($i === 0)
                            <td class="wp-name fw-semibold" rowspan="2">{{ $row['customer'] }}</td>
                        @endif
                        <td class="wp-label">{{ $pair[1] }}</td>

                        @foreach($data['weeks'] as $w => $week)
                            @foreach($data['columns'] as $key)
                                <td class="wp-n {{ $loop->first ? 'wp-band-start' : '' }}">
                                    {{-- Zero prints blank, as the yard's sheet does: a page of
                                         noughts hides the figures that are actually there. --}}
                                    {{ $row[$pair[0]]['weeks'][$w][$key] ?: '' }}
                                </td>
                            @endforeach
                        @endforeach

                        @foreach($data['columns'] as $key)
                            <td class="wp-n wp-total-band {{ $loop->first ? 'wp-band-start' : '' }}">
                                {{ $row[$pair[0]]['total'][$key] ?: '' }}
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ 2 + (count($data['weeks']) + 1) * count($data['columns']) }}"
                            class="text-center text-muted py-3 fst-italic">
                            No customers match this filter.
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tfoot>
                    {{-- Demounting, Mounting, then the two added together. The grand
                         total is total lifts — how much the cranes did, regardless of
                         direction — which is the figure the yard's sheet already carried
                         in its single TOTAL row. --}}
                    @foreach([['demounting', 'TOTAL DEMOUNTING', ''], ['mounting', 'TOTAL MOUNTING', ''], ['grand', 'GRAND TOTAL', 'wp-grand']] as $foot)
                    <tr class="{{ $foot[2] }}">
                        <td class="wp-name" colspan="2">{{ $foot[1] }}</td>
                        @foreach($data['weeks'] as $w => $week)
                            @foreach($data['columns'] as $key)
                                <td class="wp-n {{ $loop->first ? 'wp-band-start' : '' }}">
                                    {{ $data['totals'][$foot[0]]['weeks'][$w][$key] ?: '' }}
                                </td>
                            @endforeach
                        @endforeach
                        @foreach($data['columns'] as $key)
                            <td class="wp-n wp-total-band {{ $loop->first ? 'wp-band-start' : '' }}">
                                {{ $data['totals'][$foot[0]]['total'][$key] ?: '' }}
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card-footer bg-transparent small text-muted">
        <strong>Demounting</strong> is Lift Off — the box coming off the truck at gate-in.
        <strong>Mounting</strong> is Lift On — the box going onto the truck at gate-out.
        Size and cargo status are recorded as they were at the gate, so a box that arrived
        laden and left empty is counted once each way. Blank means none.
        @if($data['unmapped'] > 0)
            <span class="text-danger d-block mt-1">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ $data['unmapped'] }} movement(s)
                carry a size or cargo status outside the reported set and appear in no column.
            </span>
        @endif
    </div>
</div>
@endif

@endsection
