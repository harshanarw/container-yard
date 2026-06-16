@extends('layouts.app')

@section('title', 'Trial Balance')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Trial Balance</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>Trial Balance</h4>
        <p class="text-muted mb-0 small">Summary of all posted debit and credit balances per account</p>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

{{-- Filter Form --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </form>
    </div>
</div>

@if(round($totalDebit, 4) !== round($totalCredit, 4))
<div class="alert alert-danger d-flex gap-2 align-items-center">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div>Trial balance does not balance — check for unposted entries or data integrity issues.
        <strong>Difference: {{ number_format(abs($totalDebit - $totalCredit), 2) }}</strong>
    </div>
</div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:120px;">Code</th>
                        <th>Account Name</th>
                        <th style="width:130px;">Classification</th>
                        <th class="text-end" style="width:150px;">Debit</th>
                        <th class="text-end" style="width:150px;">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $classOrder = ['asset','liability','equity','income','expense'];
                        $shown = 0;
                    @endphp

                    @foreach($classOrder as $cls)
                        @if($grouped->has($cls))
                        @php
                            $classAccounts = $grouped[$cls];
                            $clsDebit  = $classAccounts->sum('total_debit');
                            $clsCredit = $classAccounts->sum('total_credit');
                        @endphp
                        {{-- Classification sub-header --}}
                        <tr class="table-secondary">
                            <td colspan="3" class="fw-semibold small text-uppercase letter-spacing-1">
                                <span class="badge bg-{{ \App\Models\Account::classificationBadge($cls) }}-subtle text-{{ \App\Models\Account::classificationBadge($cls) }} me-2" style="font-size:.7rem;">{{ \App\Models\Account::classificationLabel($cls) }}</span>
                            </td>
                            <td class="text-end font-monospace fw-semibold small">{{ number_format($clsDebit, 2) }}</td>
                            <td class="text-end font-monospace fw-semibold small">{{ number_format($clsCredit, 2) }}</td>
                        </tr>
                        @foreach($classAccounts->sortBy('code') as $row)
                        @php $shown++; @endphp
                        <tr>
                            <td class="font-monospace small ps-4">{{ $row->code }}</td>
                            <td class="small">{{ $row->name }}</td>
                            <td>
                                <span class="badge bg-{{ \App\Models\Account::classificationBadge($row->classification) }}-subtle text-{{ \App\Models\Account::classificationBadge($row->classification) }}" style="font-size:.7rem;">
                                    {{ \App\Models\Account::classificationLabel($row->classification) }}
                                </span>
                            </td>
                            <td class="text-end font-monospace small">{{ number_format($row->total_debit ?? 0, 2) }}</td>
                            <td class="text-end font-monospace small">{{ number_format($row->total_credit ?? 0, 2) }}</td>
                        </tr>
                        @endforeach
                        @endif
                    @endforeach

                    @if($shown === 0)
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4 small">No posted entries found in this date range.</td>
                    </tr>
                    @endif
                </tbody>
                <tfoot class="table-dark fw-semibold">
                    <tr>
                        <td colspan="3" class="text-end">TOTAL</td>
                        <td class="text-end font-monospace">{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format($totalCredit, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@endsection
