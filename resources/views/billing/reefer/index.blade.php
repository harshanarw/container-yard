@extends('layouts.app')
@section('title', 'Reefer Electricity Invoices')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-lightning-charge-fill text-primary me-2"></i>Reefer Electricity Invoices</h4>
        <p class="text-muted small mb-0">Electricity billing for laden reefer containers.</p>
    </div>
    <a href="{{ route('billing.reefer.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Invoice
    </a>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total',   'value'=>$stats['total'],  'class'=>'text-secondary'],
        ['label'=>'Draft',   'value'=>$stats['draft'],  'class'=>'text-muted'],
        ['label'=>'Issued',  'value'=>$stats['issued'], 'class'=>'text-info'],
        ['label'=>'Paid',    'value'=>$stats['paid'],   'class'=>'text-success'],
    ] as $s)
    <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center py-3">
            <div class="fs-3 fw-bold {{ $s['class'] }}">{{ $s['value'] }}</div>
            <div class="text-muted small">{{ $s['label'] }}</div>
        </div>
    </div>
    @endforeach
</div>


{{-- Filters --}}
<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice No / Customer" value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="customer_id" class="form-select form-select-sm select2">
            <option value="">All Customers</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>Draft</option>
            <option value="issued"    {{ request('status') === 'issued'    ? 'selected' : '' }}>Issued</option>
            <option value="paid"      {{ request('status') === 'paid'      ? 'selected' : '' }}>Paid</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('billing.reefer.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Period</th>
                    <th class="text-end">Amount</th>
                    <th>Lines</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr>
                    <td class="font-monospace fw-medium">{{ $invoice->invoice_no }}</td>
                    <td>{{ $invoice->customer?->name }}</td>
                    <td class="small">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                    @php $pastDue = $invoice->due_date && $invoice->status === 'issued' && now()->startOfDay()->gt($invoice->due_date); @endphp
                    <td class="small {{ $pastDue ? 'text-danger fw-semibold' : '' }}">
                        @if($invoice->due_date)
                            {{ $invoice->due_date->format('d M Y') }}
                            @if($pastDue)<i class="bi bi-exclamation-circle ms-1" title="Past due"></i>@endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">{{ $invoice->billing_period_from?->format('d M Y') }} – {{ $invoice->billing_period_to?->format('d M Y') }}</td>
                    <td class="text-end font-monospace small">
                        {{ $invoice->invoice_currency }} {{ number_format($invoice->total_amount, 2) }}
                    </td>
                    <td class="text-center">{{ $invoice->lines_count }}</td>
                    <td><span class="badge {{ $invoice->status_badge_class }}">{{ $invoice->status_label }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('billing.reefer.show', $invoice) }}" class="btn btn-sm btn-outline-primary me-1">View</a>
                        <a href="{{ route('billing.reefer.pdf', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-file-pdf"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-file-earmark-x fs-2 d-block mb-2 opacity-25"></i>
                        No reefer electricity invoices found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer d-flex justify-content-end">
        {{ $invoices->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
