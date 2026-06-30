@extends('layouts.app')

@section('title', 'Realized FX Gain/Loss')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">FX Gain/Loss</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-currency-exchange me-2 text-primary"></i>Realized FX Gain / Loss</h4>
        <p class="text-muted small mb-0">
            Exchange gain/loss recognised on settlement, {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · in {{ $base }}
        </p>
    </div>
    <form method="GET" action="{{ route('finance.reports.fx-gain-loss') }}" class="d-flex align-items-end gap-2">
        <div>
            <label class="form-label small mb-0 text-muted">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" style="width:150px">
        </div>
        <div>
            <label class="form-label small mb-0 text-muted">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" style="width:150px">
        </div>
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
    </form>
</div>

@if(!$gainAcc && !$lossAcc)
<div class="alert alert-warning small">
    No foreign-exchange gain (4102) or loss (7002) account is configured. Add them to the Chart of Accounts or map them under Account Mappings → Forex Gain/Loss.
</div>
@endif

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-md-4 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Exchange Gain</div>
            <div class="fw-bold fs-5 font-monospace text-success">{{ $base }} {{ number_format($totalGain, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Exchange Loss</div>
            <div class="fw-bold fs-5 font-monospace text-danger">{{ $base }} {{ number_format($totalLoss, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4 col-12">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Net FX (Gain − Loss)</div>
            <div class="fw-bold fs-5 font-monospace {{ $net >= 0 ? 'text-success' : 'text-danger' }}">{{ $base }} {{ number_format($net, 2) }}</div>
        </div>
    </div>
</div>

@if($bySource->isNotEmpty())
{{-- By source --}}
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2"><strong class="small">By Source</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Source</th>
                    <th class="text-end">Gain</th>
                    <th class="text-end">Loss</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bySource as $s)
                <tr>
                    <td>{{ $s['source'] }}</td>
                    <td class="text-end font-monospace text-success">{{ number_format($s['gain'], 2) }}</td>
                    <td class="text-end font-monospace text-danger">{{ number_format($s['loss'], 2) }}</td>
                    <td class="text-end font-monospace {{ $s['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($s['net'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="text-end">Total ({{ $base }})</td>
                    <td class="text-end font-monospace">{{ number_format($totalGain, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($totalLoss, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($net, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

{{-- Detail --}}
<div class="card content-card">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small">Detail</strong>
        <span class="text-muted small">{{ $rows->count() }} entr{{ $rows->count() === 1 ? 'y' : 'ies' }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Journal</th>
                    <th>Source</th>
                    <th>Narration</th>
                    <th class="text-end">Gain</th>
                    <th class="text-end">Loss</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr>
                    <td class="text-muted">{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('finance.gl.journals.show', $r['journal_id']) }}" class="font-monospace text-decoration-none">{{ $r['journal_no'] }}</a>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-secondary">{{ $r['source'] }}</span></td>
                    <td class="text-muted">{{ $r['narration'] }}</td>
                    <td class="text-end font-monospace {{ $r['gain'] > 0 ? 'text-success' : 'text-muted' }}">{{ $r['gain'] != 0 ? number_format($r['gain'], 2) : '—' }}</td>
                    <td class="text-end font-monospace {{ $r['loss'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $r['loss'] != 0 ? number_format($r['loss'], 2) : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No realized FX gain or loss in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
