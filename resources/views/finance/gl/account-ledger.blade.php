@extends('layouts.app')

@section('title', 'Account Ledger')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Account Ledger</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-list-columns-reverse me-2 text-primary"></i>Account Ledger</h4>
    <p class="text-muted mb-0 small">View all posted entries for a selected account with running balance</p>
</div>

{{-- Filter Form --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-5 col-md-4">
                <label class="form-label form-label-sm fw-semibold mb-1">Account <span class="text-danger">*</span></label>
                <select name="account_id" class="form-select form-select-sm select2" required>
                    <option value="">— Select Account —</option>
                    @php $accountsByClass = $accounts->groupBy('classification'); @endphp
                    @foreach(['asset','liability','equity','income','expense'] as $cls)
                        @if($accountsByClass->has($cls))
                        <optgroup label="{{ ucfirst($cls) }}">
                            @foreach($accountsByClass[$cls] as $acc)
                            <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                            @endforeach
                        </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="{{ request('from', \Carbon\Carbon::now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="{{ request('to', \Carbon\Carbon::now()->toDateString()) }}">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Currency</label>
                <select name="currency" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($currencies as $c)
                    <option value="{{ $c }}" @selected($currencyFilter === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i>View Ledger
                </button>
            </div>
        </form>
    </div>
</div>

@if($account)

{{-- Account Info Card --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-4 align-items-center flex-wrap">
            <div>
                <span class="text-muted small">Account:</span>
                <strong class="ms-1 font-monospace">{{ $account->code }}</strong>
                <span class="ms-1">{{ $account->name }}</span>
            </div>
            <div>
                <span class="badge bg-{{ \App\Models\Account::classificationBadge($account->classification) }}-subtle text-{{ \App\Models\Account::classificationBadge($account->classification) }}" style="font-size:.75rem;">
                    {{ \App\Models\Account::classificationLabel($account->classification) }}
                </span>
            </div>
            <div>
                <span class="text-muted small">Normal Balance:</span>
                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:.75rem;">{{ ucfirst($account->normal_balance) }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Journal No</th>
                        <th>Narration</th>
                        <th class="text-end">Debit ({{ $base }})</th>
                        <th class="text-end">Credit ({{ $base }})</th>
                        <th class="text-end">Balance ({{ $base }})</th>
                        <th>Cur</th>
                        <th class="text-end">Txn Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Opening balance row --}}
                    <tr class="table-secondary">
                        <td colspan="3" class="fw-semibold small text-muted">Opening Balance (before {{ request('from', \Carbon\Carbon::now()->startOfMonth()->toDateString()) }})@if($currencyFilter) · {{ $currencyFilter }} only@endif</td>
                        <td class="text-end font-monospace small text-muted">—</td>
                        <td class="text-end font-monospace small text-muted">—</td>
                        <td class="text-end font-monospace fw-semibold small">{{ number_format($openingBalance, 2) }}</td>
                        <td></td>
                        <td></td>
                    </tr>

                    @php $balance = $openingBalance; @endphp

                    @forelse($entries as $entry)
                    @php
                        $debit  = (float)$entry->debit;
                        $credit = (float)$entry->credit;
                        if ($account->normal_balance === 'debit') {
                            $balance += $debit - $credit;
                        } else {
                            $balance += $credit - $debit;
                        }
                    @endphp
                    <tr>
                        <td class="small">{{ $entry->journal->journal_date->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('finance.gl.journals.show', $entry->journal_id) }}"
                               class="font-monospace small text-decoration-none">{{ $entry->journal->journal_no }}</a>
                        </td>
                        <td class="small text-muted">{{ $entry->narration ?: $entry->journal->narration }}</td>
                        <td class="text-end font-monospace small">{{ $debit > 0 ? number_format($debit, 2) : '—' }}</td>
                        <td class="text-end font-monospace small">{{ $credit > 0 ? number_format($credit, 2) : '—' }}</td>
                        <td class="text-end font-monospace small {{ $balance < 0 ? 'text-danger' : '' }}">
                            {{ number_format($balance, 2) }}
                        </td>
                        <td class="small {{ $entry->currency === $base ? 'text-muted' : '' }}">{{ $entry->currency }}</td>
                        <td class="text-end font-monospace small {{ $entry->currency === $base ? 'text-muted' : '' }}">
                            @php $txn = ((float) $entry->txn_debit) ?: ((float) $entry->txn_credit); @endphp
                            {{ number_format($txn, 2) }}
                            @if($entry->currency !== $base)
                            <span class="text-muted" style="font-size:.7rem;">@ {{ rtrim(rtrim(number_format((float) $entry->exchange_rate, 4, '.', ''), '0'), '.') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3 small">No transactions in this period@if($currencyFilter) in {{ $currencyFilter }}@endif.</td>
                    </tr>
                    @endforelse

                    {{-- Closing balance --}}
                    <tr class="table-light fw-semibold">
                        <td colspan="3">Closing Balance</td>
                        <td class="text-end font-monospace">{{ number_format($entries->sum('debit'), 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($entries->sum('credit'), 2) }}</td>
                        <td class="text-end font-monospace {{ $balance < 0 ? 'text-danger' : '' }}">{{ number_format($balance, 2) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@else
<div class="card content-card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-list-columns-reverse fs-1 opacity-25 d-block mb-2"></i>
        Select an account above to view its ledger.
    </div>
</div>
@endif

@endsection
