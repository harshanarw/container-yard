@extends('layouts.app')

@section('title', 'Receipt ' . $receipt->receipt_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active">{{ $receipt->receipt_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi bi-receipt me-2 text-primary"></i>{{ $receipt->receipt_no }}
            @php $rsc = \App\Models\Receipt::statusBadge($receipt->status); @endphp
        <span class="badge bg-{{ $rsc }}-subtle text-{{ $rsc }} ms-2 fs-6 text-capitalize">{{ $receipt->status }}</span>
        </h4>
        <p class="text-muted mb-0 small">{{ $receipt->customer->name ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('finance.receipts.confirm')
        @if($receipt->isDraft())
        <form method="POST" action="{{ route('finance.receipts.confirm', $receipt) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Confirm and post receipt {{ $receipt->receipt_no }} to GL?')">
                <i class="bi bi-check2-circle me-1"></i>Confirm & Post
            </button>
        </form>
        @endif
        @endcan
        @can('finance.receipts.void')
        @if($receipt->isConfirmed())
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
            <i class="bi bi-x-circle me-1"></i>Void
        </button>
        @endif
        @endcan
        <a href="{{ route('finance.receipts.index') }}" class="btn btn-outline-secondary btn-sm">
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
    {{-- Receipt Details --}}
    <div class="col-md-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2">
                <strong class="small">Receipt Details</strong>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted w-40">Receipt No</td>
                        <td class="fw-semibold font-monospace">{{ $receipt->receipt_no }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date</td>
                        <td>{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer</td>
                        <td class="fw-semibold">{{ $receipt->customer->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount</td>
                        <td class="fw-semibold">{{ number_format($receipt->amount, 2) }} {{ $receipt->currency }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Exchange Rate</td>
                        <td class="font-monospace">{{ $receipt->exchange_rate }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payment Method</td>
                        <td>{{ \App\Models\Receipt::paymentMethodLabel($receipt->payment_method) }}</td>
                    </tr>
                    @if($receipt->cheque_no)
                    <tr>
                        <td class="text-muted">Cheque No</td>
                        <td class="font-monospace">{{ $receipt->cheque_no }}</td>
                    </tr>
                    @endif
                    @if($receipt->reference_no)
                    <tr>
                        <td class="text-muted">Reference No</td>
                        <td class="font-monospace">{{ $receipt->reference_no }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Bank Account</td>
                        <td>{{ $receipt->bankAccount ? $receipt->bankAccount->account_name . ' — ' . $receipt->bankAccount->bank_name : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Narration</td>
                        <td>{{ $receipt->narration }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created By</td>
                        <td>{{ $receipt->createdBy->name ?? '—' }}</td>
                    </tr>
                    @if($receipt->voidedBy)
                    <tr>
                        <td class="text-muted">Voided By</td>
                        <td class="text-danger">{{ $receipt->voidedBy->name }} on {{ $receipt->voided_at?->format('d M Y') }}</td>
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
                @if($receipt->journal)
                <div class="px-3 pt-2 pb-1 small text-muted">
                    Journal: <span class="fw-semibold font-monospace">{{ $receipt->journal->journal_no }}</span>
                    &nbsp;|&nbsp;
                    <span class="badge bg-{{ $receipt->journal->isPosted() ? 'success' : 'secondary' }}-subtle text-{{ $receipt->journal->isPosted() ? 'success' : 'secondary' }}">
                        {{ ucfirst($receipt->journal->status) }}
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
                            @foreach($receipt->journal->entries as $entry)
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

    {{-- Allocations --}}
    <div class="col-12">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                <strong class="small">Invoice Allocations</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice Type</th>
                                <th>Invoice ID</th>
                                <th class="text-end">Allocated Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipt->allocations as $alloc)
                            <tr>
                                <td>{{ ucfirst(str_replace('-', ' ', $alloc->invoice_type)) }}</td>
                                <td class="font-monospace">{{ $alloc->invoice_id }}</td>
                                <td class="text-end font-monospace">{{ number_format($alloc->allocated_amount, 2) }}</td>
                                <td class="text-muted">{{ $alloc->notes ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3 small fst-italic">No allocations yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($receipt->isDraft())
            @can('finance.receipts.create')
            <div class="card-footer bg-transparent">
                <form method="POST" action="{{ route('finance.receipts.allocations.store', $receipt) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Invoice Type</label>
                        <select name="invoice_type" class="form-select form-select-sm" required>
                            <option value="storage">Storage</option>
                            <option value="storage-handling">Storage Handling</option>
                            <option value="reefer">Reefer</option>
                            <option value="repair">Repair</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Invoice ID</label>
                        <input type="number" name="invoice_id" class="form-control form-control-sm" required min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Amount</label>
                        <input type="number" name="allocated_amount" class="form-control form-control-sm" required min="0.01" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                </form>
            </div>
            @endcan
            @endif
        </div>
    </div>
</div>

{{-- Void Modal --}}
@can('finance.receipts.void')
@if($receipt->isConfirmed())
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Void Receipt {{ $receipt->receipt_no }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('finance.receipts.void', $receipt) }}">
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
                    <button type="submit" class="btn btn-danger btn-sm">Void Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

@endsection
