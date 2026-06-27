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
        @can('finance.vouchers.pdf')
        <a href="{{ route('finance.vouchers.pdf', $voucher) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print A4
        </a>
        <a href="{{ route('finance.vouchers.pdf', ['voucher' => $voucher, 'size' => 'half']) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>Half Page
        </a>
        @endcan
        @can('finance.vouchers.email')
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
            <i class="bi bi-envelope me-1"></i>Email
        </button>
        @endcan
        <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@can('finance.vouchers.email')
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('finance.vouchers.email', $voucher) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-envelope me-1 text-primary"></i>Email Voucher {{ $voucher->voucher_no }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">To <span class="text-danger">*</span></label>
                        <input type="email" name="to_email" class="form-control form-control-sm" required
                               value="{{ $voucher->supplier->email ?? '' }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">CC</label>
                        <input type="email" name="cc_email" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Attachment Format</label>
                        <select name="format" class="form-select form-select-sm">
                            <option value="a4" selected>Full Page (A4)</option>
                            <option value="half">Half Page (slip)</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Message</label>
                        <textarea name="message" rows="3" class="form-control form-control-sm" maxlength="1000" placeholder="Optional note to include in the email"></textarea>
                    </div>
                    <div class="form-text small">The selected voucher PDF is attached automatically (computer-generated copy — no signature lines).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

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
                        <td class="text-muted">Supplier / Contact</td>
                        <td>
                            @if($voucher->supplier)
                            <a href="{{ route('customers.show', $voucher->customer_id) }}" class="text-decoration-none">{{ $voucher->supplier->name }}</a>
                            @else
                            <span class="fst-italic text-muted">One-off payee</span>
                            @endif
                        </td>
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
                        <td class="text-muted">Value ({{ \App\Models\CompanySetting::baseCurrency() }})</td>
                        <td class="fw-semibold font-monospace">{{ number_format($voucher->base_amount ?? ($voucher->amount * $voucher->exchange_rate), 2) }}</td>
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

{{-- AP Allocations (contact-linked vouchers only) --}}
@if($voucher->customer_id)
<div class="card content-card mt-3">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong class="small"><i class="bi bi-link-45deg me-1"></i>Apply to Supplier Invoices</strong>
        <span class="small">
            <span class="text-muted">Voucher:</span> <span class="font-monospace">{{ number_format($voucher->amount, 2) }}</span>
            · <span class="text-muted">Allocated:</span> <span class="font-monospace">{{ number_format($totalAllocated, 2) }}</span>
            · <span class="text-muted">Unallocated:</span>
            <span class="font-monospace fw-semibold {{ $unallocatedAmount > 0 ? 'text-success' : 'text-muted' }}">{{ number_format($unallocatedAmount, 2) }}</span>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Invoice</th>
                    <th class="text-end">Invoice Total</th>
                    <th class="text-end">Allocated</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($voucher->allocations as $alloc)
                <tr>
                    <td class="font-monospace">
                        @if($alloc->invoice)
                        <a href="{{ route('finance.ap.invoices.show', $alloc->invoice) }}" class="text-decoration-none">{{ $alloc->invoice->invoice_no }}</a>
                        @else <span class="text-muted">#{{ $alloc->supplier_invoice_id }}</span> @endif
                    </td>
                    <td class="text-end font-monospace">{{ $alloc->invoice ? number_format($alloc->invoice->total_amount, 2) : '—' }}</td>
                    <td class="text-end font-monospace fw-semibold">{{ number_format($alloc->allocated_amount, 2) }}</td>
                    <td class="text-muted">{{ $alloc->notes ?: '—' }}</td>
                    <td class="text-end">
                        @can('finance.vouchers.create')
                        @if($voucher->isDraft())
                        <form method="POST" action="{{ route('finance.vouchers.allocations.destroy', [$voucher, $alloc]) }}" class="d-inline"
                              onsubmit="return confirm('Remove this allocation?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No allocations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('finance.vouchers.create')
    @if($voucher->isDraft())
        @if($pendingInvoices->isNotEmpty() && $unallocatedAmount > 0)
        <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('finance.vouchers.allocations.store', $voucher) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label small mb-1">Supplier Invoice</label>
                    <select name="supplier_invoice_id" id="allocInvoice" class="form-select form-select-sm" required>
                        <option value="">— Select invoice —</option>
                        @foreach($pendingInvoices as $pi)
                        <option value="{{ $pi['id'] }}" data-outstanding="{{ $pi['outstanding'] }}">{{ $pi['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="allocated_amount" id="allocAmount" class="form-control form-control-sm text-end font-monospace" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm" maxlength="255">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                </div>
            </form>
        </div>
        @elseif($unallocatedAmount <= 0)
        <div class="card-footer bg-transparent small text-muted"><i class="bi bi-check-circle me-1 text-success"></i>Voucher fully allocated.</div>
        @else
        <div class="card-footer bg-transparent small text-muted">No outstanding invoices for this supplier.</div>
        @endif
    @endif
    @endcan
</div>

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('allocInvoice');
    const amt = document.getElementById('allocAmount');
    const unallocated = {{ $unallocatedAmount }};
    sel?.addEventListener('change', function () {
        const out = parseFloat(this.options[this.selectedIndex]?.dataset.outstanding || 0);
        if (out > 0) amt.value = Math.min(out, unallocated).toFixed(2);
    });
})();
</script>
@endpush
@endif

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
