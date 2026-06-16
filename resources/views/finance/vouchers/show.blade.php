@extends('layouts.app')

@section('title', 'Voucher ' . $voucher->voucher_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.vouchers.index') }}">Payment Vouchers</a></li>
    <li class="breadcrumb-item active">{{ $voucher->voucher_no }}</li>
@endsection

@section('content')

@php
    $statusColors = ['draft'=>'secondary','confirmed'=>'success','voided'=>'danger'];
    $statusColor = $statusColors[$voucher->status] ?? 'secondary';
    $methods = ['cash'=>'Cash','cheque'=>'Cheque','bank_transfer'=>'Bank Transfer','online'=>'Online'];
@endphp

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi bi-cash-coin me-2 text-primary"></i>{{ $voucher->voucher_no }}
            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} ms-2 fs-6 text-capitalize">{{ $voucher->status }}</span>
        </h4>
        <p class="text-muted mb-0 small">{{ $voucher->payee_name }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('finance.vouchers.confirm')
        @if($voucher->isDraft())
        <form method="POST" action="{{ route('finance.vouchers.confirm', $voucher) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Confirm and post voucher {{ $voucher->voucher_no }} to GL?')">
                <i class="bi bi-check2-circle me-1"></i>Confirm & Post
            </button>
        </form>
        @endif
        @endcan
        @can('finance.vouchers.void')
        @if($voucher->isConfirmed())
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
            <i class="bi bi-x-circle me-1"></i>Void
        </button>
        @endif
        @endcan
        <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    {{-- Voucher Details --}}
    <div class="col-md-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2">
                <strong class="small">Voucher Details</strong>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted w-40">Voucher No</td>
                        <td class="fw-semibold font-monospace">{{ $voucher->voucher_no }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date</td>
                        <td>{{ \Carbon\Carbon::parse($voucher->voucher_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payee</td>
                        <td class="fw-semibold">{{ $voucher->payee_name }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount</td>
                        <td class="fw-semibold">{{ number_format($voucher->amount, 2) }} {{ $voucher->currency }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Exchange Rate</td>
                        <td class="font-monospace">{{ $voucher->exchange_rate }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payment Method</td>
                        <td>{{ $methods[$voucher->payment_method] ?? $voucher->payment_method }}</td>
                    </tr>
                    @if($voucher->cheque_no)
                    <tr>
                        <td class="text-muted">Cheque No</td>
                        <td class="font-monospace">{{ $voucher->cheque_no }}</td>
                    </tr>
                    @endif
                    @if($voucher->reference_no)
                    <tr>
                        <td class="text-muted">Reference No</td>
                        <td class="font-monospace">{{ $voucher->reference_no }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Bank Account</td>
                        <td>{{ $voucher->bankAccount ? $voucher->bankAccount->account_name . ' — ' . $voucher->bankAccount->bank_name : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Expense Account</td>
                        <td>
                            @if($voucher->expenseAccount)
                                <span class="font-monospace text-muted">{{ $voucher->expenseAccount->code }}</span>
                                {{ $voucher->expenseAccount->name }}
                            @else
                                <span class="fst-italic text-muted">Not specified</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Narration</td>
                        <td>{{ $voucher->narration }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created By</td>
                        <td>{{ $voucher->createdBy->name ?? '—' }}</td>
                    </tr>
                    @if($voucher->voidedBy)
                    <tr>
                        <td class="text-muted">Voided By</td>
                        <td class="text-danger">{{ $voucher->voidedBy->name }} on {{ $voucher->voided_at?->format('d M Y') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- GL Journal --}}
    <div class="col-md-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2">
                <strong class="small">GL Journal</strong>
            </div>
            <div class="card-body p-0">
                @if($voucher->journal)
                <div class="px-3 pt-2 pb-1 small text-muted">
                    Journal: <span class="fw-semibold font-monospace">{{ $voucher->journal->journal_no }}</span>
                    &nbsp;|&nbsp;
                    <span class="badge bg-{{ $voucher->journal->isPosted() ? 'success' : 'secondary' }}-subtle text-{{ $voucher->journal->isPosted() ? 'success' : 'secondary' }}">
                        {{ ucfirst($voucher->journal->status) }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($voucher->journal->entries as $entry)
                            <tr>
                                <td>
                                    <span class="font-monospace text-muted">{{ $entry->account->code }}</span>
                                    {{ $entry->account->name }}
                                </td>
                                <td class="text-end font-monospace">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                                <td class="text-end font-monospace">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4 small">
                    <i class="bi bi-journal-x d-block fs-3 mb-1 opacity-25"></i>
                    Not yet posted to GL.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Void Modal --}}
@can('finance.vouchers.void')
@if($voucher->isConfirmed())
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Void Voucher {{ $voucher->voucher_no }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('finance.vouchers.void', $voucher) }}">
                @csrf
                <div class="modal-body">
                    <p class="small text-muted">This will create a reversal journal entry. This action cannot be undone.</p>
                    <div>
                        <label class="form-label small fw-semibold">Reason for voiding</label>
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="255" placeholder="Optional reason...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Void Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

@endsection
