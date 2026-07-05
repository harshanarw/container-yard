@extends('layouts.app')

@section('title', 'VAT / SSCL Return')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">VAT / SSCL Return</li>
@endsection

@section('content')
@php
    $base = $data['base'];
    $s    = $data['summary'];
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-percent me-2 text-primary"></i>VAT / SSCL Return</h4>
        <p class="text-muted small mb-0">
            Output tax on sales vs recoverable input VAT for
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · in {{ $base }}
        </p>
    </div>
    <div class="d-flex align-items-end gap-2 flex-wrap d-print-none">
        <form method="GET" action="{{ route('finance.reports.vat-sscl-return') }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label small mb-0 text-muted">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" style="width:150px">
            </div>
            <div>
                <label class="form-label small mb-0 text-muted">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" style="width:150px">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </form>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="card content-card text-center py-3 {{ $s['net_vat_payable'] >= 0 ? 'border-primary' : 'border-success' }}">
            <div class="text-muted small">Net VAT {{ $s['net_vat_payable'] >= 0 ? 'Payable' : 'Refundable' }}</div>
            <div class="fw-bold fs-4 font-monospace {{ $s['net_vat_payable'] >= 0 ? 'text-primary' : 'text-success' }}">
                {{ $base }} {{ $money(abs($s['net_vat_payable'])) }}
            </div>
            <div class="text-muted" style="font-size:.72rem;">Output {{ $money($s['output_vat']) }} − Input {{ $money($s['input_vat']) }}</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Output VAT</div>
            <div class="fw-bold fs-5 font-monospace">{{ $base }} {{ $money($s['output_vat']) }}</div>
            <div class="text-muted" style="font-size:.72rem;">on sales</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Input VAT</div>
            <div class="fw-bold fs-5 font-monospace">{{ $base }} {{ $money($s['input_vat']) }}</div>
            <div class="text-muted" style="font-size:.72rem;">recoverable on purchases</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card content-card text-center py-3 border-warning">
            <div class="text-muted small">SSCL Payable</div>
            <div class="fw-bold fs-5 font-monospace text-warning">{{ $base }} {{ $money($s['sscl_payable']) }}</div>
            <div class="text-muted" style="font-size:.72rem;">on turnover (not creditable)</div>
        </div>
    </div>
</div>

{{-- Output tax --}}
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2"><strong class="small"><i class="bi bi-arrow-up-right-circle me-1 text-primary"></i>Output Tax — Sales</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Source</th>
                    <th class="text-end">Documents</th>
                    <th class="text-end">Taxable Value ({{ $base }})</th>
                    <th class="text-end">SSCL ({{ $base }})</th>
                    <th class="text-end pe-3">VAT ({{ $base }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['output']['rows'] as $r)
                <tr>
                    <td>{{ $r['label'] }}</td>
                    <td class="text-end text-muted">{{ $r['count'] }}</td>
                    <td class="text-end font-monospace {{ $r['taxable'] < 0 ? 'text-danger' : '' }}">{{ $money($r['taxable']) }}</td>
                    <td class="text-end font-monospace {{ $r['sscl'] < 0 ? 'text-danger' : '' }}">{{ $money($r['sscl']) }}</td>
                    <td class="text-end pe-3 font-monospace {{ $r['vat'] < 0 ? 'text-danger' : '' }}">{{ $money($r['vat']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="2" class="text-end">Total output</td>
                    <td class="text-end font-monospace">{{ $money($data['output']['taxable']) }}</td>
                    <td class="text-end font-monospace">{{ $money($data['output']['sscl']) }}</td>
                    <td class="text-end pe-3 font-monospace">{{ $money($data['output']['vat']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Input tax --}}
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2"><strong class="small"><i class="bi bi-arrow-down-left-circle me-1 text-success"></i>Input Tax — Purchases</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Source</th>
                    <th class="text-end">Documents</th>
                    <th class="text-end">Taxable Value ({{ $base }})</th>
                    <th class="text-end">SSCL ({{ $base }}) <span class="text-muted fw-normal">·&nbsp;info</span></th>
                    <th class="text-end pe-3">Input VAT ({{ $base }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['input']['rows'] as $r)
                <tr>
                    <td>{{ $r['label'] }}</td>
                    <td class="text-end text-muted">{{ $r['count'] }}</td>
                    <td class="text-end font-monospace {{ $r['taxable'] < 0 ? 'text-danger' : '' }}">{{ $money($r['taxable']) }}</td>
                    <td class="text-end font-monospace text-muted">{{ $money($r['sscl']) }}</td>
                    <td class="text-end pe-3 font-monospace {{ $r['vat'] < 0 ? 'text-danger' : '' }}">{{ $money($r['vat']) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="2" class="text-end">Total input</td>
                    <td class="text-end font-monospace">{{ $money($data['input']['taxable']) }}</td>
                    <td class="text-end font-monospace text-muted">{{ $money($data['input']['sscl']) }}</td>
                    <td class="text-end pe-3 font-monospace">{{ $money($data['input']['vat']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Settlement --}}
<div class="row g-3">
    <div class="col-md-6">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">VAT Settlement</strong></div>
            <div class="card-body py-2">
                <table class="table table-sm mb-0 small">
                    <tbody>
                        <tr><td>Output VAT (sales)</td><td class="text-end font-monospace">{{ $money($s['output_vat']) }}</td></tr>
                        <tr><td>Less: Input VAT (purchases)</td><td class="text-end font-monospace">({{ $money($s['input_vat']) }})</td></tr>
                        <tr class="fw-bold border-top">
                            <td>Net VAT {{ $s['net_vat_payable'] >= 0 ? 'Payable' : 'Refundable' }}</td>
                            <td class="text-end font-monospace {{ $s['net_vat_payable'] >= 0 ? 'text-primary' : 'text-success' }}">{{ $money(abs($s['net_vat_payable'])) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">SSCL Settlement</strong></div>
            <div class="card-body py-2">
                <table class="table table-sm mb-0 small">
                    <tbody>
                        <tr><td>Output SSCL (turnover)</td><td class="text-end font-monospace">{{ $money($s['output_sscl']) }}</td></tr>
                        <tr class="text-muted"><td>Input SSCL (paid, not creditable)</td><td class="text-end font-monospace">{{ $money($s['input_sscl']) }}</td></tr>
                        <tr class="fw-bold border-top">
                            <td>SSCL Payable</td>
                            <td class="text-end font-monospace text-warning">{{ $money($s['sscl_payable']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info small mt-3 d-flex align-items-start gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Figures are on an <strong>invoice (accrual) basis</strong> and normalised to {{ $base }}. Only issued/posted documents are
        included. Credit notes reverse VAT only — consistent with how they post to the ledger. <strong>SSCL is a turnover levy and
        is not recoverable</strong>: input SSCL paid on purchases is shown for information but never offsets the SSCL payable.
    </div>
</div>

@endsection
