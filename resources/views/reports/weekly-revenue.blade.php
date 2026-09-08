@extends('layouts.app')

@section('title', 'Weekly Performance — Revenue')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Weekly Performance — Revenue</li>
@endsection

@push('styles')
<style>
    /* The grid grows with the range, so it scrolls inside its own box. The page
       itself must never scroll sideways. */
    .wr-scroll { overflow-x: auto; }

    .wr-grid { border-collapse: separate; border-spacing: 0; font-size: .8rem; white-space: nowrap; }
    .wr-grid th, .wr-grid td { border: 1px solid var(--bs-border-color); padding: .3rem .5rem; }
    .wr-grid thead th { text-align: center; vertical-align: middle; background: var(--bs-tertiary-bg); font-weight: 600; }

    /* Customer and service stay put while the weeks scroll past. Without this a
       reader loses which line they are on around week three — and here the lines
       are seven deep, not two. */
    .wr-grid .wr-name, .wr-grid .wr-label { position: sticky; background: var(--bs-body-bg); z-index: 2; }
    .wr-grid .wr-name  { left: 0;     min-width: 200px; }
    .wr-grid .wr-label { left: 200px; min-width: 170px; }
    .wr-grid thead .wr-name, .wr-grid thead .wr-label { z-index: 3; background: var(--bs-tertiary-bg); }

    .wr-grid td.wr-amt { text-align: right; font-variant-numeric: tabular-nums; min-width: 92px; }
    /* A zero is noise on a sheet this tall. The yard's own workbook leaves the
       cell blank, and so does this. */
    .wr-grid td.wr-zero { color: var(--bs-secondary-color); }

    /* Eight rows per customer needs a visible seam or nobody can find a block. */
    .wr-grid tbody tr.wr-block-end td { border-bottom-width: 2px; }
    .wr-grid tbody tr.wr-total td { font-weight: 600; background: var(--bs-secondary-bg); }
    .wr-grid tbody tr.wr-quiet .wr-name, .wr-grid tbody tr.wr-quiet .wr-label { color: var(--bs-secondary-color); font-weight: 400; }
    /* A figure that is short because a rate is missing. Amber on the row, not
       only an icon: the reader should see it without hovering every line. */
    .wr-grid tbody tr.wr-unpriced td { background: var(--bs-warning-bg-subtle); }
    @media print { .wr-flag { display: none; } }

    .wr-grid tfoot td { background: var(--bs-tertiary-bg); }
    .wr-grid tfoot tr.wr-cat-head td { font-weight: 700; letter-spacing: .04em; }
    .wr-grid tfoot tr.wr-grand td { font-weight: 700; border-top-width: 2px; background: var(--bs-secondary-bg); }

    .wr-print-head { display: none; }

    @media print {
        .no-print { display: none !important; }
        .page-header, .content-card > .card-header { display: none !important; }
        .card, .content-card { border: 0 !important; box-shadow: none !important; }
        .card-body { padding: 0 !important; }
        .wr-print-head { display: block; margin-bottom: .5rem; }
        .wr-print-head h5 { margin: 0; font-size: 12pt; }
        .wr-print-meta { font-size: 7.5pt; color: #555; }
        .wr-scroll { overflow: visible !important; }
        .wr-grid { font-size: .58rem; width: 100% !important; table-layout: fixed; }
        .wr-grid .wr-name, .wr-grid .wr-label { position: static; }
        .wr-grid thead { display: table-header-group; }
        .wr-grid tfoot { display: table-footer-group; }
        @page { size: A4 landscape; margin: 10mm; }
    }
</style>
@endpush

@section('content')

@php
    /** Blank rather than 0.00 — the yard's own sheet leaves empty cells empty. */
    $money = fn ($v) => abs((float) $v) < 0.005 ? '' : number_format((float) $v, 2);
    $weeks = $data['weeks'];
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-cash-coin me-2 text-primary"></i>Weekly Performance — Revenue</h4>
        <p class="text-muted mb-0 small">Revenue per customer, week by week, in {{ $data['currency'] }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="{{ route('reports.weekly-performance', request()->only('from', 'to', 'week_rule', 'customer_id')) }}"
           class="btn btn-outline-secondary btn-sm" title="The same weeks, counted rather than priced">
            <i class="bi bi-graph-up me-1"></i>Container Count
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        {{-- Both carry the current filters, so what downloads is what is on screen. --}}
        @if(\App\Support\Export\WeeklyRevenueWorkbook::available())
        <a href="{{ route('reports.weekly-revenue.export', request()->query()) }}"
           class="btn btn-outline-success btn-sm" title="The sheet as an Excel workbook">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </a>
        @endif
        <a href="{{ route('reports.weekly-revenue.export.csv', request()->query()) }}"
           class="btn btn-outline-success btn-sm" title="One heading row per column, for scripts and formulas">
            <i class="bi bi-filetype-csv me-1"></i>CSV
        </a>
    </div>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────────── --}}
<div class="card content-card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reports.weekly-revenue') }}">
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
                        <input class="form-check-input" type="checkbox" role="switch" id="onlyEarning"
                               name="only_with_revenue" value="1" {{ $filters['only_with_revenue'] ? 'checked' : '' }}>
                        <label class="form-check-label small" for="onlyEarning">Only with revenue</label>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
                    <a href="{{ route('reports.weekly-revenue') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

@if(empty($weeks))
    <div class="alert alert-warning small">
        That date range produces no weeks. Check that <strong>From</strong> is on or before <strong>To</strong>.
    </div>
@else

{{-- Anything that could not be priced. A zero meaning "no tariff configured"
     and a zero meaning "a quiet week" must never look the same, or missing
     configuration reads as lost business. --}}
@if($data['issues'])
<div class="alert alert-warning small no-print">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Some revenue could not be priced and is missing from the figures below.</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach(array_slice($data['issues'], 0, 8) as $issue)
            <li>{{ $issue }}</li>
        @endforeach
        @if(count($data['issues']) > 8)
            <li class="text-muted">…and {{ count($data['issues']) - 8 }} more.</li>
        @endif
    </ul>
</div>
@endif

{{-- ── The sheet ───────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <strong class="small">{{ $data['title'] }}</strong>
        <span class="small text-muted">
            {{ $data['currency'] }} {{ number_format($data['grand']['total'], 2) }}
            across {{ count($weeks) }} week{{ count($weeks) === 1 ? '' : 's' }}
        </span>
    </div>

    <div class="card-body p-0">
        {{-- Printed only. On screen this is the card header and the filter bar;
             on paper neither exists, and a sheet handed round the yard that does
             not say what it was filtered to is a sheet nobody can check. --}}
        <div class="wr-print-head">
            <h5>{{ $data['title'] }}</h5>
            <div class="wr-print-meta">
                {{ \Carbon\Carbon::parse($data['from'])->format('d M Y') }}
                to {{ \Carbon\Carbon::parse($data['to'])->format('d M Y') }}
                · {{ $weekRules[$data['week_rule']] ?? $data['week_rule'] }}
                · Amounts in {{ $data['currency'] }}
                @if($filters['customer_id'])
                    · Customer: {{ $customers->firstWhere('id', $filters['customer_id'])?->name }}
                @endif
                @if($filters['only_with_revenue'])
                    · Customers with revenue only
                @endif
                · Printed {{ now()->format('d M Y H:i') }}
            </div>
        </div>

        <div class="wr-scroll" data-xscroll>
            <table class="wr-grid mb-0 w-100">
                <thead>
                    <tr>
                        <th class="wr-name" rowspan="2">CUSTOMER</th>
                        <th class="wr-label" rowspan="2">SERVICES</th>
                        <th colspan="{{ count($weeks) }}">WEEKLY PERFORMANCE</th>
                        <th rowspan="2">TOTAL</th>
                    </tr>
                    <tr>
                        @foreach($weeks as $week)
                            {{-- The full range, not a single date. The yard's sheet
                                 heads each column with one date and nobody can tell
                                 whether it means the start or the end of the week. --}}
                            <th>
                                {{ \Carbon\Carbon::parse($week['from'])->format('d M') }}
                                – {{ \Carbon\Carbon::parse($week['to'])->format('d M') }}
                                @if($week['partial'])
                                    <span class="text-muted fw-normal">({{ $week['days'] }}d)</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                @foreach($data['rows'] as $row)
                    @foreach($data['categories'] as $category)
                        <tr class="{{ $row['earned'] ? '' : 'wr-quiet' }} {{ $row['categories'][$category]['issue'] ? 'wr-unpriced' : '' }}">
                            @if($loop->first)
                                <td class="wr-name" rowspan="{{ count($data['categories']) + 1 }}">
                                    {{ $row['customer'] }}
                                    @if($row['code'])
                                        <span class="text-muted small">({{ $row['code'] }})</span>
                                    @endif
                                </td>
                            @endif
                            <td class="wr-label">
                                {{ $data['labels'][$category] }}
                                @if($row['categories'][$category]['issue'])
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1 wr-flag"
                                       data-bs-toggle="tooltip" data-bs-placement="right"
                                       title="{{ $row['categories'][$category]['issue'] }}"></i>
                                @endif
                            </td>
                            @foreach($row['categories'][$category]['weeks'] as $amount)
                                <td class="wr-amt {{ $amount == 0 ? 'wr-zero' : '' }}">{{ $money($amount) }}</td>
                            @endforeach
                            <td class="wr-amt {{ $row['categories'][$category]['total'] == 0 ? 'wr-zero' : '' }}">
                                {{ $money($row['categories'][$category]['total']) }}
                            </td>
                        </tr>
                    @endforeach
                    {{-- The eighth row. Its job is to be checkable: it must equal
                         the seven above it. --}}
                    <tr class="wr-total wr-block-end {{ $row['earned'] ? '' : 'wr-quiet' }}">
                        <td class="wr-label">
                            Total
                            @if($row['total']['issue'])
                                <i class="bi bi-exclamation-triangle-fill text-warning ms-1 wr-flag"
                                   data-bs-toggle="tooltip" data-bs-placement="right"
                                   title="{{ $row['total']['issue'] }}"></i>
                            @endif
                        </td>
                        @foreach($row['total']['weeks'] as $amount)
                            <td class="wr-amt {{ $amount == 0 ? 'wr-zero' : '' }}">{{ $money($amount) }}</td>
                        @endforeach
                        <td class="wr-amt {{ $row['total']['total'] == 0 ? 'wr-zero' : '' }}">{{ $money($row['total']['total']) }}</td>
                    </tr>
                @endforeach

                @if(empty($data['rows']))
                    <tr>
                        <td colspan="{{ count($weeks) + 3 }}" class="text-center text-muted py-3">
                            No customers to show for this filter.
                        </td>
                    </tr>
                @endif
                </tbody>

                <tfoot>
                    {{-- Yard-level, and deliberately empty. Nothing in the system
                         models rental income yet; the row holds its place so the
                         sheet keeps the shape the yard knows. --}}
                    <tr>
                        <td class="wr-name">OTHER INCOME — RENT</td>
                        <td class="wr-label text-muted fst-italic">not yet recorded</td>
                        @foreach($data['rent']['weeks'] as $amount)
                            <td class="wr-amt wr-zero">{{ $money($amount) }}</td>
                        @endforeach
                        <td class="wr-amt wr-zero">{{ $money($data['rent']['total']) }}</td>
                    </tr>

                    {{-- The second, independent path to the grand total: these
                         seven must sum to the same figure as the customer totals
                         plus rent. --}}
                    <tr class="wr-cat-head">
                        <td class="wr-name" rowspan="{{ count($data['categories']) }}">CATEGORY TOTALS</td>
                        <td class="wr-label">{{ $data['labels'][$data['categories'][0]] }}</td>
                        @foreach($data['category_totals'][$data['categories'][0]]['weeks'] as $amount)
                            <td class="wr-amt {{ $amount == 0 ? 'wr-zero' : '' }}">{{ $money($amount) }}</td>
                        @endforeach
                        <td class="wr-amt">{{ $money($data['category_totals'][$data['categories'][0]]['total']) }}</td>
                    </tr>
                    @foreach(array_slice($data['categories'], 1) as $category)
                        <tr>
                            <td class="wr-label">
                                {{ $data['labels'][$category] }}
                                @if($row['categories'][$category]['issue'])
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1 wr-flag"
                                       data-bs-toggle="tooltip" data-bs-placement="right"
                                       title="{{ $row['categories'][$category]['issue'] }}"></i>
                                @endif
                            </td>
                            @foreach($data['category_totals'][$category]['weeks'] as $amount)
                                <td class="wr-amt {{ $amount == 0 ? 'wr-zero' : '' }}">{{ $money($amount) }}</td>
                            @endforeach
                            <td class="wr-amt">{{ $money($data['category_totals'][$category]['total']) }}</td>
                        </tr>
                    @endforeach

                    <tr class="wr-grand">
                        <td class="wr-name">GRAND TOTAL</td>
                        <td class="wr-label">{{ $data['currency'] }}</td>
                        @foreach($data['grand']['weeks'] as $amount)
                            <td class="wr-amt">{{ $money($amount) }}</td>
                        @endforeach
                        <td class="wr-amt">{{ $money($data['grand']['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- The basis note. A report that quietly disagrees with the invoices is
         trusted until someone checks, and distrusted permanently after. --}}
    <div class="card-footer bg-transparent small text-muted">
        <strong>De-mounting, Mounting and Storage are earned revenue</strong> — computed from gate
        movements and the tariffs in force, so they do not wait for invoicing to run.
        <strong>Electricity, PTI, Overtime and Other are billed</strong> — taken from issued documents
        dated in the period, excluding drafts, cancellations and voids.
        Storage &amp; Handling invoices are never read here, because the first three rows already
        compute that revenue from the movements themselves.
        <em>This report will not tie to the invoice ledger or the general ledger.</em>
    </div>
</div>

@endif

@include('partials.x-scroll')

@push('scripts')
<script>
// Bootstrap tooltips are opt-in, and this app initialises them per view rather
// than globally. Without this the markers fall back to the browser's own
// tooltip, which works but appears slowly and cannot be placed.
document.querySelectorAll('.wr-flag[data-bs-toggle="tooltip"]').forEach(function (el) {
    if (typeof bootstrap !== 'undefined') new bootstrap.Tooltip(el, { trigger: 'hover focus' });
});
</script>
@endpush
@endsection
