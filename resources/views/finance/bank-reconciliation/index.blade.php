@extends('layouts.app')

@section('title', 'Bank Reconciliation')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Bank Reconciliation</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Bank Reconciliation</h4>
        <p class="text-muted mb-0 small">Match the ledger to your bank statements and lock each period once balanced.</p>
    </div>
    @can('finance.bank-reconciliation.create')
    <a href="{{ route('finance.bank-reconciliation.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Reconciliation
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card mb-3 d-print-none">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1 fw-semibold">Bank Account</label>
                <select name="bank_account_id" class="form-select form-select-sm select2" onchange="this.form.submit()">
                    <option value="">— All accounts —</option>
                    @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}" {{ (string) request('bank_account_id') === (string) $ba->id ? 'selected' : '' }}>
                            {{ $ba->bank_name }} — {{ $ba->account_name }} ({{ $ba->currency }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Statement Date</th>
                    <th>Bank Account</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">Closing</th>
                    <th class="text-center">Statement Lines</th>
                    <th class="text-center">Status</th>
                    <th>Reconciled By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reconciliations as $r)
                <tr>
                    <td class="text-nowrap">{{ $r->statement_date->format('d M Y') }}</td>
                    <td>{{ $r->bankAccount->bank_name }} — {{ $r->bankAccount->account_name }}</td>
                    <td class="text-end font-monospace">{{ number_format($r->opening_balance, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($r->closing_balance, 2) }}</td>
                    <td class="text-center">{{ $r->statement_lines_count }}</td>
                    <td class="text-center">
                        @if($r->isCompleted())
                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-lock-fill me-1"></i>Completed</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning border">Draft</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ optional($r->reconciledBy)->name ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('finance.bank-reconciliation.show', $r) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-right-circle me-1"></i>{{ $r->isDraft() ? 'Continue' : 'View' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No reconciliations yet. Start one to match your bank statement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $reconciliations->links() }}</div>

@endsection
