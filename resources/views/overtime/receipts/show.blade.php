@extends('layouts.app')

@section('title', 'OT Receipt ' . $receipt->receipt_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Overtime</li>
    <li class="breadcrumb-item"><a href="{{ route('overtime.receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active">{{ $receipt->receipt_no }}</li>
@endsection

@section('content')
@php
$statusColors = [
    'generated' => 'secondary', 'paid' => 'success', 'partially_used' => 'info',
    'fully_used' => 'primary', 'cancelled' => 'dark', 'void' => 'danger',
];
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>{{ $receipt->receipt_no }}
            <span class="badge bg-{{ $statusColors[$receipt->status] ?? 'secondary' }} align-middle">{{ ucfirst(str_replace('_',' ',$receipt->status)) }}</span>
        </h4>
        <p class="text-muted mb-0 small">BL {{ $receipt->bl_number }} · {{ $receipt->customer->name ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('ot.receipt.pdf')
        <a href="{{ route('overtime.receipts.pdf', $receipt) }}" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-printer me-1"></i>Print</a>
        @endcan
        <a href="{{ route('overtime.receipts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card content-card mb-3"><div class="card-header py-2">Details</div><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-4">BL Number</dt><dd class="col-8 font-monospace">{{ $receipt->bl_number }}</dd>
                <dt class="col-4">Customer</dt><dd class="col-8">{{ $receipt->customer->name ?? '—' }}</dd>
                <dt class="col-4">Tariff Rule</dt><dd class="col-8">{{ $receipt->rule->display_name ?? '' }} <span class="text-muted">({{ $receipt->rule->rule_code ?? '' }})</span></dd>
                <dt class="col-4">Operational Date</dt><dd class="col-8">{{ $receipt->operational_date?->format('d M Y') }}</dd>
                <dt class="col-4">Valid Period</dt><dd class="col-8">{{ $receipt->valid_from?->format('d M Y H:i') }} → {{ $receipt->valid_to?->format('d M Y H:i') }}</dd>
                <dt class="col-4">Containers</dt><dd class="col-8">{{ $receipt->used_container_count }} used / {{ $receipt->expected_container_count }} paid</dd>
                @if($receipt->extensionOf)
                <dt class="col-4">Extension of</dt><dd class="col-8"><a href="{{ route('overtime.receipts.show', $receipt->extensionOf) }}">{{ $receipt->extensionOf->receipt_no }}</a></dd>
                @endif
                @if($receipt->remarks)<dt class="col-4">Remarks</dt><dd class="col-8">{{ $receipt->remarks }}</dd>@endif
            </dl>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card content-card mb-3"><div class="card-header py-2">Amount</div><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-6 fw-bold">Total</dt>
                <dd class="col-6 text-end h5 fw-bold text-primary">{{ $receipt->currency }} {{ number_format($receipt->total_amount, 2) }}</dd>
            </dl>
            @if($receipt->status === 'paid' || $receipt->paid_at)
            <div class="small text-success mt-2"><i class="bi bi-check-circle me-1"></i>Paid {{ $receipt->paid_at?->format('d M Y H:i') }} ({{ ucfirst($receipt->payment_method) }})</div>
            @endif
        </div></div>

        @can('ot.receipt.generate')
        @if($receipt->status === 'generated')
        <div class="card content-card mb-3"><div class="card-header py-2">Confirm Payment</div><div class="card-body">
            <form method="POST" action="{{ route('overtime.receipts.confirm', $receipt) }}">
                @csrf @method('PATCH')
                <div class="mb-2">
                    <label class="form-label small mb-1">Payment Method</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="online">Online</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Received Account <span class="text-muted">(bank; leave blank for cash)</span></label>
                    <select name="bank_account_id" class="form-select form-select-sm select2">
                        <option value="">— None / Cash —</option>
                        @foreach($bankAccounts as $ba)
                            <option value="{{ $ba->id }}">{{ $ba->account_name }} ({{ $ba->bank_name }})</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-success btn-sm w-100"><i class="bi bi-cash-coin me-1"></i>Confirm & Post to Ledger</button>
            </form>
        </div></div>
        @endif
        @endcan

        @can('ot.receipt.cancel')
        @if($receipt->status === 'generated')
        <div class="card content-card"><div class="card-body">
            <form method="POST" action="{{ route('overtime.receipts.cancel', $receipt) }}">
                @csrf @method('PATCH')
                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Cancellation reason" required>
                <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-x-circle me-1"></i>Cancel Receipt</button>
            </form>
        </div></div>
        @endif
        @endcan
    </div>
</div>
@endsection
