@extends('layouts.app')

@section('title', 'Billing Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Billing</li>
@endsection

@push('styles')
<style>
    @media print {
        #sidebar, #topbar, .no-print { display: none !important; }
        #main-content { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>Billing Report</h4>
        <p class="text-muted mb-0 small">Storage charges and revenue summary</p>
    </div>
    <div class="btn-group btn-group-sm no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-receipt-cutoff"></i></div>
                <div>
                    <div class="text-muted small">Total Records</div>
                    <div class="fs-4 fw-bold">{{ $summary['total_records'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-currency-dollar"></i></div>
                <div>
                    <div class="text-muted small">Total Revenue</div>
                    <div class="fs-4 fw-bold">
                        {{ number_format($summary['total_revenue'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info"><i class="bi bi-calendar-range"></i></div>
                <div>
                    <div class="text-muted small">Total Days Billed</div>
                    <div class="fs-4 fw-bold">{{ number_format($summary['total_days']) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="text-muted small">Avg. Stay (days)</div>
                    <div class="fs-4 fw-bold">{{ number_format($summary['avg_stay'] ?? 0, 1) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card content-card mb-3 no-print">
    <div class="card-header py-2 fw-semibold small"><i class="bi bi-funnel me-1"></i>Filter Report</div>
    <div class="card-body py-2">
        <form method="GET" action="{{ route('reports.billing') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2">
                        <option value="">All Customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->code }} – {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Gate-In From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Gate-Out To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Apply
                    </button>
                    <a href="{{ route('reports.billing') }}" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Billing Table --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold small"><i class="bi bi-table me-1"></i>Storage Billing Records</span>
        <span class="text-muted small">{{ $storageRecords->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="billingTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Container No.</th>
                        <th>Customer</th>
                        <th>Gate In</th>
                        <th>Gate Out</th>
                        <th class="text-center">Total Days</th>
                        <th class="text-center">Free Days</th>
                        <th class="text-center">Chargeable Days</th>
                        <th class="text-end">Daily Rate</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end pe-3">Total Charge</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($storageRecords as $i => $record)
                    <tr>
                        <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                        <td>
                            @if($record->container)
                                <span class="font-monospace fw-semibold">{{ $record->container->container_no }}</span>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $record->container->size }}ft {{ $record->container->type_code }}
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($record->customer)
                                <span class="badge bg-dark text-white">{{ $record->customer->code }}</span>
                                <div class="text-muted" style="font-size:.72rem;">{{ $record->customer->name }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $record->gate_in_date ? $record->gate_in_date->format('d M Y') : '—' }}</td>
                        <td>{{ $record->gate_out_date ? $record->gate_out_date->format('d M Y') : '—' }}</td>
                        <td class="text-center">{{ $record->total_days }}</td>
                        <td class="text-center text-success">{{ $record->free_days }}</td>
                        <td class="text-center">
                            <span class="{{ $record->chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ $record->chargeable_days }}
                            </span>
                        </td>
                        <td class="text-end">
                            {{ number_format($record->daily_rate, 2) }}
                        </td>
                        <td class="text-end">{{ number_format($record->subtotal, 2) }}</td>
                        <td class="text-end text-muted">
                            @if($record->tax_amount > 0)
                                {{ number_format($record->tax_amount, 2) }}
                                <small class="text-muted">({{ $record->tax_percentage }}%)</small>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end pe-3 fw-bold">
                            {{ number_format($record->total_charge, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No billing records found. Records appear here after containers gate out.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($storageRecords->isNotEmpty())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td class="ps-3"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="text-end">Grand Total:</td>
                        <td class="text-end">{{ number_format($storageRecords->sum('subtotal'), 2) }}</td>
                        <td class="text-end">{{ number_format($storageRecords->sum('tax_amount'), 2) }}</td>
                        <td class="text-end pe-3 text-success fs-6">
                            {{ number_format($summary['total_revenue'], 2) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @if($storageRecords->isNotEmpty())
    <div class="card-footer bg-white py-2 small text-muted">
        Report generated on {{ now()->format('d M Y, H:i') }}
        @if(request()->hasAny(['customer_id','date_from','date_to']))
            &nbsp;·&nbsp; Filtered view
        @endif
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#billingTable').DataTable({
        pageLength: 25,
        order: [[3, 'desc']],
        dom: '<"d-flex align-items-center gap-2 mb-2"lf>t<"d-flex justify-content-between mt-2"ip>',
        language: { search: '', searchPlaceholder: 'Search table…' },
        columnDefs: [{ orderable: false, targets: [9, 10, 11] }],
    });
});
</script>
@endpush
