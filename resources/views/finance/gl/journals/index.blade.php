@extends('layouts.app')

@section('title', 'GL Journals')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">GL Journals</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-journal-bookmark me-2 text-primary"></i>GL Journals</h4>
        <p class="text-muted mb-0 small">View and manage general ledger journal entries</p>
    </div>
    @can('finance.gl.create')
    <a href="{{ route('finance.gl.journals.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Journal
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filter Form --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach(['invoice','receipt','payment','journal','adjustment','opening','closing'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach(['draft','posted','voided'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                @if(request()->hasAny(['from','to','type','status']))
                <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Journal No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Narration</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journals as $journal)
                    <tr>
                        <td class="font-monospace fw-semibold small">
                            {{ $journal->journal_no }}
                            @if($journal->ird_invoice_no)
                            <div class="text-muted fw-normal" style="font-size:.68rem" title="IRD Tax Invoice No">
                                <i class="bi bi-receipt me-1"></i>{{ $journal->ird_invoice_no }}
                            </div>
                            @endif
                        </td>
                        <td class="small">{{ $journal->journal_date->format('d M Y') }}</td>
                        <td>
                            <span class="badge bg-{{ \App\Models\GlJournal::typeBadge($journal->journal_type) }}-subtle text-{{ \App\Models\GlJournal::typeBadge($journal->journal_type) }}" style="font-size:.7rem;">
                                {{ ucwords(str_replace("_"," ",$journal->journal_type)) }}
                            </span>
                        </td>
                        <td class="small">{{ Str::limit($journal->narration, 60) }}</td>
                        <td class="text-end font-monospace small">{{ number_format($journal->total_debit, 2) }}</td>
                        <td class="text-end font-monospace small">{{ number_format($journal->total_credit, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ \App\Models\GlJournal::statusBadge($journal->status) }}-subtle text-{{ \App\Models\GlJournal::statusBadge($journal->status) }}" style="font-size:.7rem;">
                                {{ ucfirst($journal->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('finance.gl.journals.show', $journal) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4 small">No journals found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($journals->hasPages())
    <div class="card-footer bg-transparent">
        {{ $journals->links() }}
    </div>
    @endif
</div>

@endsection
