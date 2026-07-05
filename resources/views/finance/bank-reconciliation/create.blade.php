@extends('layouts.app')

@section('title', 'New Bank Reconciliation')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.bank-reconciliation.index') }}">Bank Reconciliation</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Start a Reconciliation</h4>
    <p class="text-muted mb-0 small">Enter the closing details from your bank statement, then clear the transactions that appear on it.</p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card content-card" style="max-width:720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('finance.bank-reconciliation.store') }}" class="row g-3">
            @csrf
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Bank Account <span class="text-danger">*</span></label>
                <select name="bank_account_id" class="form-select select2" required
                        onchange="window.location='{{ route('finance.bank-reconciliation.create') }}?bank_account_id='+this.value">
                    <option value="">— Select bank account —</option>
                    @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}" {{ (string) $selectedId === (string) $ba->id ? 'selected' : '' }}
                            @if(!$ba->gl_account_id) disabled @endif>
                            {{ $ba->bank_name }} — {{ $ba->account_name }} ({{ $ba->currency }})
                            @if(!$ba->gl_account_id) — no GL account @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Only accounts linked to a GL account can be reconciled.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Statement Date <span class="text-danger">*</span></label>
                <input type="date" name="statement_date" class="form-control" value="{{ old('statement_date', now()->endOfMonth()->toDateString()) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Opening Balance (per statement)</label>
                <input type="number" step="0.01" name="opening_balance" class="form-control font-monospace"
                       value="{{ old('opening_balance', $openingHint ?? 0) }}" required>
                <div class="form-text">
                    @if($openingHint !== null)
                        Carried from the previous completed statement.
                    @else
                        The statement's beginning balance (0 for the first reconciliation).
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Closing Balance (per statement) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" name="closing_balance" class="form-control font-monospace" value="{{ old('closing_balance') }}" required>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Start Reconciliation</button>
                <a href="{{ route('finance.bank-reconciliation.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
