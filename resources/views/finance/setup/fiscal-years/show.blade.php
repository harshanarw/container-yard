@extends('layouts.app')

@section('title', $fiscalYear->code)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.setup.fiscal-years.index') }}">Financial Years</a></li>
    <li class="breadcrumb-item active">{{ $fiscalYear->code }}</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi bi-calendar3 me-2 text-primary"></i>{{ $fiscalYear->code }}
            <span class="badge bg-{{ \App\Models\FinancialYear::statusBadge($fiscalYear->status) }}-subtle text-{{ \App\Models\FinancialYear::statusBadge($fiscalYear->status) }} ms-2 fs-6 fw-normal align-middle">
                {{ ucfirst($fiscalYear->status) }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">{{ $fiscalYear->description }} &nbsp;·&nbsp; {{ $fiscalYear->start_date->format('d M Y') }} – {{ $fiscalYear->end_date->format('d M Y') }}</p>
    </div>
    <a href="{{ route('finance.setup.fiscal-years.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-3">
    {{-- Left: Edit status card --}}
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header fw-semibold"><i class="bi bi-pencil me-2 text-secondary"></i>Edit Year</div>
            <div class="card-body">
                @can('finance.setup.edit')
                <form method="POST" action="{{ route('finance.setup.fiscal-years.update', $fiscalYear) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Description</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                               value="{{ $fiscalYear->description }}" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="draft"    {{ $fiscalYear->status === 'draft'    ? 'selected' : '' }}>Draft</option>
                            <option value="open"     {{ $fiscalYear->status === 'open'     ? 'selected' : '' }}>Open</option>
                            <option value="closed"   {{ $fiscalYear->status === 'closed'   ? 'selected' : '' }}>Closed</option>
                            <option value="archived" {{ $fiscalYear->status === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3">{{ $fiscalYear->notes }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </form>
                @else
                <div class="text-muted small">No edit permission.</div>
                @endcan
            </div>
        </div>

        <div class="card content-card mt-3">
            <div class="card-header fw-semibold small"><i class="bi bi-info-circle me-2 text-secondary"></i>Summary</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted small">Created</td><td class="small">{{ $fiscalYear->created_at->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted small">By</td><td class="small">{{ $fiscalYear->createdBy?->name ?? '—' }}</td></tr>
                    <tr>
                        <td class="text-muted small">Open Periods</td>
                        <td class="small">{{ $fiscalYear->periods->where('status','open')->count() }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Closed Periods</td>
                        <td class="small">{{ $fiscalYear->periods->whereIn('status',['closed','locked'])->count() }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Periods table --}}
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar-week me-2 text-primary"></i>Accounting Periods</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $fiscalYear->periods->count() }} periods</span>
            </div>
            <div class="px-3 pt-2 pb-1 small text-muted border-bottom" style="background:#fbfcfd">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Close workflow:</strong>
                <span class="badge bg-success-subtle text-success">Open</span>
                <i class="bi bi-arrow-right mx-1"></i>
                <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                <small>(stops posting)</small>
                <i class="bi bi-arrow-right mx-1"></i>
                <span class="badge bg-dark-subtle text-dark">P&amp;L Closed</span>
                <small>(posts closing journal, locks period)</small>.
                Periods must be P&amp;L-closed in order; closing the final period transfers Current Year P/L into Retained Earnings and closes the fiscal year.
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">#</th>
                            <th>Period</th>
                            <th>Dates</th>
                            <th class="text-center">Status</th>
                            <th class="text-muted small">Closed By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fiscalYear->periods as $period)
                        <tr>
                            <td class="text-center text-muted small">{{ $period->period_no }}</td>
                            <td class="fw-semibold">{{ $period->name }}</td>
                            <td class="text-muted small">
                                {{ $period->start_date->format('d M') }} – {{ $period->end_date->format('d M Y') }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ \App\Models\AccountingPeriod::statusBadge($period->status) }}-subtle text-{{ \App\Models\AccountingPeriod::statusBadge($period->status) }}"
                                      title="{{ $period->status === 'locked' ? 'P&L closed — period is locked' : ($period->status === 'closed' ? 'Period-end done — ready for P&L close' : 'Open for posting') }}">
                                    {{ $period->status === 'locked' ? 'P&L Closed' : ucfirst($period->status) }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                @if($period->closedBy)
                                    {{ $period->closedBy->name }}<br>
                                    <span style="font-size:.7rem;">{{ $period->closed_at->format('d M Y') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                @php
                                    $plJournal = $period->status === 'locked'
                                        ? \App\Models\GlJournal::where('journal_type', 'closing')
                                            ->where('status', 'posted')
                                            ->where('reference_type', \App\Models\AccountingPeriod::class)
                                            ->where('reference_id', $period->id)
                                            ->first()
                                        : null;
                                @endphp

                                @if($period->status === 'open')
                                    @can('finance.periods.close')
                                    <form method="POST" action="{{ route('finance.setup.fiscal-years.period.close', [$fiscalYear, $period]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                onclick="return confirm('Close period {{ $period->name }}? This stops new postings to this period.')">
                                            <i class="bi bi-lock me-1"></i>Close Period
                                        </button>
                                    </form>
                                    @endcan
                                @elseif($period->status === 'closed')
                                    @can('finance.periods.close')
                                    <form method="POST" action="{{ route('finance.setup.fiscal-years.period.close-pl', [$fiscalYear, $period]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary py-0 px-2"
                                                onclick="return confirm('Run the P&L close for {{ $period->name }}?\n\nThis posts the period closing journal (income/expense → Current Year P/L) and locks the period.{{ $loop->last ? '\n\nThis is the FINAL period — the year-end transfer to Retained Earnings will also run and the fiscal year will be closed.' : '' }}')">
                                            <i class="bi bi-journal-check me-1"></i>Close P&amp;L
                                        </button>
                                    </form>
                                    @endcan
                                    @can('finance.periods.reopen')
                                    <form method="POST" action="{{ route('finance.setup.fiscal-years.period.reopen', [$fiscalYear, $period]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2"
                                                onclick="return confirm('Reopen period {{ $period->name }}? This allows new postings again.')">
                                            <i class="bi bi-unlock me-1"></i>Reopen
                                        </button>
                                    </form>
                                    @endcan
                                @else {{-- locked: P&L closed --}}
                                    @if($plJournal)
                                    <a href="{{ route('finance.gl.journals.show', $plJournal) }}"
                                       class="btn btn-sm btn-outline-info py-0 px-2" title="View closing journal">
                                        <i class="bi bi-journal-text me-1"></i>{{ $plJournal->journal_no }}
                                    </a>
                                    @endif
                                    @can('finance.periods.reopen')
                                    <form method="POST" action="{{ route('finance.setup.fiscal-years.period.reverse-pl', [$fiscalYear, $period]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2"
                                                onclick="return confirm('Reverse the P&L close for {{ $period->name }}?\n\nThis voids the closing journal(s) and unlocks the period back to Closed.{{ $loop->last ? '\n\nThe year-end Retained Earnings transfer will also be reversed and the fiscal year reopened.' : '' }}')">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                                        </button>
                                    </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
