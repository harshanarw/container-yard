@extends('layouts.app')

@section('title', 'Container Hire Charges')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Accounts Payable</li>
    <li class="breadcrumb-item active">Container Hire Charges</li>
@endsection

@section('content')

@php
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-arrow-down-left-circle me-2 text-primary"></i>Container Hire Charges</h4>
        <p class="text-muted mb-0 small">
            What the yard owes its shipping lines for containers it holds on hire
        </p>
    </div>
    <a href="{{ route('finance.ap.invoices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-receipt me-1"></i>Supplier Invoices
    </a>
</div>

{{-- Period --}}
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('finance.ap.hire-billing.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-medium mb-1">From <span class="text-danger">*</span></label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-medium mb-1">To <span class="text-danger">*</span></label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-medium mb-1">Shipping Line / Lessor</label>
                <select name="lessor_id" class="form-select form-select-sm select2">
                    <option value="">All lessors</option>
                    @foreach($lessors as $l)
                        <option value="{{ $l->id }}" {{ (string) $lessorId === (string) $l->id ? 'selected' : '' }}>
                            {{ $l->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i>Show Charges
                </button>
                <a href="{{ route('finance.ap.hire-billing.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

@if(! $ran)
    {{-- Deliberately empty until asked: the preview prices every open lease
         against its whole billed history, which is not work to do on a bare
         page load. --}}
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-range fs-1 d-block mb-2 opacity-25"></i>
            <div class="fw-medium">Choose a period and press <strong>Show Charges</strong>.</div>
            <div class="small mt-1">Defaults to last month — a rental bill arrives after the month it covers.</div>
        </div>
    </div>
@elseif(! count($rows))
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-3 d-block mb-1 opacity-25"></i>
            No containers were on hire from a lessor during this period.
        </div>
    </div>
@else

@if(! $chargeCode)
    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        The <strong>LHIRE</strong> charge code is missing, so an invoice cannot be costed.
        Re-run the charge-code seeder.
    </div>
@endif

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100"><div class="card-body py-3">
            <div class="text-muted small">Leases in period</div>
            <div class="fs-4 fw-bold">{{ $summary['leases'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100"><div class="card-body py-3">
            <div class="text-muted small">Billable</div>
            <div class="fs-4 fw-bold">{{ $summary['billable'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100"><div class="card-body py-3">
            <div class="text-muted small">Days</div>
            <div class="fs-4 fw-bold">{{ $summary['days'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100"><div class="card-body py-3">
            <div class="text-muted small">Payable</div>
            <div class="fs-4 fw-bold text-danger">{{ $money($summary['amount']) }}</div>
        </div></div>
    </div>
</div>

<form method="POST" action="{{ route('finance.ap.hire-billing.store') }}">
    @csrf
    <input type="hidden" name="from" value="{{ $from }}">
    <input type="hidden" name="to" value="{{ $to }}">

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:2.5rem;">
                            <input type="checkbox" class="form-check-input" id="tickAll">
                        </th>
                        <th>Container</th>
                        <th>Lessor</th>
                        <th>Lease Job</th>
                        <th>Period Billed</th>
                        <th class="text-end">Days</th>
                        <th>Rates Applied</th>
                        <th class="text-end">Payable</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    @php $lease = $row['lease']; @endphp
                    <tr class="{{ $row['billable'] ? '' : 'opacity-75' }}">
                        <td>
                            @if($row['billable'])
                                <input type="checkbox" name="lease_ids[]" value="{{ $lease->id }}"
                                       class="form-check-input tick" checked>
                            @endif
                        </td>
                        <td class="font-monospace fw-medium">{{ $lease->container?->container_no ?? '-' }}</td>
                        <td class="small">{{ $lease->lessor?->name ?? '-' }}</td>
                        <td class="small font-monospace">{{ $lease->yardJob?->job_no ?? '-' }}</td>
                        <td class="small text-nowrap">
                            {{ \Illuminate\Support\Carbon::parse($row['from'])->format('d M') }}
                            &ndash;
                            {{ \Illuminate\Support\Carbon::parse($row['to'])->format('d M Y') }}
                            @if($row['is_interim'])
                                <span class="badge bg-info-subtle text-info border ms-1" style="font-size:.66rem;">Interim</span>
                            @endif
                        </td>
                        <td class="text-end small">
                            {{ $row['days'] }}
                            @if($row['days_before'] > 0)
                                {{-- Tiers run from the start of the lease, so which days
                                     these are decides the rate. --}}
                                <span class="text-muted" style="font-size:.7rem;"
                                      title="Days already billed on this lease before this period">
                                    (after {{ $row['days_before'] }})
                                </span>
                            @endif
                        </td>
                        <td class="small">
                            {{ \App\Services\Billing\HireTierPricing::describe($row['pricing']) }}
                        </td>
                        <td class="text-end fw-semibold">
                            {{ $money($row['amount']) }}
                            <div class="text-muted" style="font-size:.7rem;">{{ $lease->hireCurrency() }}</div>
                        </td>
                    </tr>
                    @if($row['warnings'])
                    <tr class="{{ $row['billable'] ? '' : 'opacity-75' }}">
                        <td></td>
                        <td colspan="7" class="pt-0">
                            @foreach($row['warnings'] as $w)
                                <div class="small text-warning-emphasis">
                                    <i class="bi bi-exclamation-triangle me-1"></i>{{ $w }}
                                </div>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="7" class="text-end">Total payable</th>
                        <th class="text-end">{{ $money($summary['amount']) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-end justify-content-between gap-2">
            <div>
                <label class="form-label small fw-medium mb-1">Invoice date</label>
                <input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ $to }}" style="max-width:12rem;">
                <div class="form-text" style="font-size:.72rem;">
                    One draft per shipping line — that is how they bill, and the document this is
                    reconciled against.
                </div>
            </div>
            <button type="submit" class="btn btn-primary" {{ $summary['billable'] ? '' : 'disabled' }}>
                <i class="bi bi-file-earmark-plus me-1"></i>Raise Draft Supplier Invoices
            </button>
        </div>
    </div>
</form>

<script>
    document.getElementById('tickAll')?.addEventListener('change', function () {
        document.querySelectorAll('.tick').forEach(cb => { cb.checked = this.checked; });
    });
</script>

@endif

@endsection
