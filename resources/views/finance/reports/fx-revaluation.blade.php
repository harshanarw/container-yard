@extends('layouts.app')

@section('title', 'FX Revaluation (Preview)')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">FX Revaluation</li>
@endsection

@section('content')

@php $items = collect($items); $missing = collect($missing); $canRevalue = $items->isNotEmpty() && abs($summary['net_gain']) >= 0.01; @endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-arrow-repeat me-2 text-primary"></i>FX Revaluation
            @if($alreadyPosted)<span class="badge bg-success-subtle text-success align-middle">Posted</span>@else<span class="badge bg-secondary-subtle text-secondary align-middle">Preview</span>@endif
        </h4>
        <p class="text-muted small mb-0">
            Unrealized gain/loss from re-pricing open foreign AR/AP balances as of {{ \Carbon\Carbon::parse($as_of)->format('d M Y') }} · in {{ $base }}
        </p>
    </div>
    <div class="d-flex align-items-end gap-2 flex-wrap">
        <form method="GET" action="{{ route('finance.reports.fx-revaluation') }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label small mb-0 text-muted">As Of</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $as_of }}" style="width:160px">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </form>
        @if($canPost && $canRevalue && !$alreadyPosted)
        <form method="POST" action="{{ route('finance.reports.fx-revaluation.post') }}"
              onsubmit="return confirm('Post the FX revaluation for {{ \Carbon\Carbon::parse($as_of)->format('d M Y') }}? It books an adjustment journal on this date and an automatic reversal the next day.');">
            @csrf
            <input type="hidden" name="as_of" value="{{ $as_of }}">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-journal-check me-1"></i>Post Revaluation</button>
        </form>
        @endif
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($alreadyPosted)
<div class="alert alert-success small d-flex align-items-start gap-2">
    <i class="bi bi-check-circle mt-1"></i>
    <div>An FX revaluation has already been posted for {{ \Carbon\Carbon::parse($as_of)->format('d M Y') }} (with its next-day reversal). To re-run, void those journals first.</div>
</div>
@else
<div class="alert alert-info small d-flex align-items-start gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Revaluation gain/loss is <em>unrealized</em>. Posting books a balanced adjustment journal on the as-of date and an
        <strong>automatic reversing journal the next day</strong> — it only becomes realized FX on settlement. AR/AP control accounts are
        revalued against the unrealized FX gain (4102) / loss (7002) accounts (or their <code>forex_*_unrealized</code> mapping overrides).
    </div>
</div>
@endif

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-4 col-12">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Net Unrealized {{ $summary['net_gain'] >= 0 ? 'Gain' : 'Loss' }}</div>
            <div class="fw-bold fs-4 font-monospace {{ $summary['net_gain'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $base }} {{ number_format(abs($summary['net_gain']), 2) }}
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">AR Revaluation (asset)</div>
            <div class="fw-bold fs-6 font-monospace {{ $summary['ar_delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $base }} {{ number_format($summary['ar_delta'], 2) }}
            </div>
            <div class="text-muted" style="font-size:.72rem;">{{ number_format($summary['ar_booked'], 2) }} → {{ number_format($summary['ar_revalued'], 2) }}</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">AP Revaluation (liability)</div>
            <div class="fw-bold fs-6 font-monospace {{ $summary['ap_delta'] <= 0 ? 'text-success' : 'text-danger' }}">
                {{ $base }} {{ number_format($summary['ap_delta'], 2) }}
            </div>
            <div class="text-muted" style="font-size:.72rem;">{{ number_format($summary['ap_booked'], 2) }} → {{ number_format($summary['ap_revalued'], 2) }}</div>
        </div>
    </div>
</div>

@if($missing->isNotEmpty())
<div class="alert alert-warning small">
    <strong>{{ $missing->count() }} open foreign item(s) skipped</strong> — no exchange rate is configured on/before {{ \Carbon\Carbon::parse($as_of)->format('d M Y') }}:
    {{ $missing->map(fn ($m) => $m['no'].' ('.$m['currency'].')')->implode(', ') }}.
    Add the rate under Finance → Exchange Rates to include them.
</div>
@endif

{{-- Detail --}}
<div class="card content-card">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small">Open Foreign Items</strong>
        <span class="text-muted small">{{ $items->count() }} item(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Side</th>
                    <th>Document</th>
                    <th>No</th>
                    <th>Ccy</th>
                    <th class="text-end">Outstanding (doc)</th>
                    <th class="text-end">Booked Rate</th>
                    <th class="text-end">As-of Rate</th>
                    <th class="text-end">Booked ({{ $base }})</th>
                    <th class="text-end">Revalued ({{ $base }})</th>
                    <th class="text-end">Δ {{ $base }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                <tr>
                    <td><span class="badge bg-{{ $it['side'] === 'AR' ? 'primary' : 'danger' }}-subtle text-{{ $it['side'] === 'AR' ? 'primary' : 'danger' }}">{{ $it['side'] }}</span></td>
                    <td>{{ ucwords(str_replace('-', ' ', $it['type'])) }}</td>
                    <td class="font-monospace">{{ $it['no'] }}</td>
                    <td>{{ $it['currency'] }}</td>
                    <td class="text-end font-monospace">{{ number_format($it['doc_outstanding'], 2) }}</td>
                    <td class="text-end font-monospace text-muted">{{ rtrim(rtrim(number_format($it['booked_rate'], 4, '.', ''), '0'), '.') }}</td>
                    <td class="text-end font-monospace text-muted">{{ rtrim(rtrim(number_format($it['asof_rate'], 4, '.', ''), '0'), '.') }}</td>
                    <td class="text-end font-monospace">{{ number_format($it['booked_base'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($it['revalued_base'], 2) }}</td>
                    <td class="text-end font-monospace {{ $it['delta'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($it['delta'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No open foreign-currency balances to revalue as of {{ \Carbon\Carbon::parse($as_of)->format('d M Y') }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
