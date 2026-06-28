@extends('layouts.app')

@section('title', $journal->journal_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.gl.journals.index') }}">GL Journals</a></li>
    <li class="breadcrumb-item active">{{ $journal->journal_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi bi-journal-text me-2 text-primary"></i>{{ $journal->journal_no }}
            <span class="badge bg-{{ \App\Models\GlJournal::statusBadge($journal->status) }}-subtle text-{{ \App\Models\GlJournal::statusBadge($journal->status) }} ms-2 fs-6 fw-normal align-middle">
                {{ ucfirst($journal->status) }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">{{ $journal->narration }}</p>
    </div>
    <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    {{-- Left: Journal Info --}}
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header fw-semibold small"><i class="bi bi-info-circle me-2 text-secondary"></i>Journal Details</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="text-muted small fw-semibold mb-1">Date</div>
                        <div class="small">{{ $journal->journal_date->format('d M Y') }}</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small fw-semibold mb-1">Type</div>
                        <div>
                            <span class="badge bg-{{ \App\Models\GlJournal::typeBadge($journal->journal_type) }}-subtle text-{{ \App\Models\GlJournal::typeBadge($journal->journal_type) }}" style="font-size:.75rem;">
                                {{ ucwords(str_replace("_"," ",$journal->journal_type)) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small fw-semibold mb-1">Period</div>
                        <div class="small">{{ $journal->period?->name ?? '—' }}
                            @if($journal->period?->financialYear)
                                <span class="text-muted">({{ $journal->period->financialYear->code }})</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small fw-semibold mb-1">Narration</div>
                        <div class="small">{{ $journal->narration }}</div>
                    </div>
                    @if($journal->reference_type)
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Reference Type</div>
                        <div class="small font-monospace">{{ class_basename($journal->reference_type) }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Reference ID</div>
                        <div class="small font-monospace">{{ $journal->reference_id }}</div>
                    </div>
                    @endif
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Created By</div>
                        <div class="small">{{ $journal->createdBy?->name ?? '—' }}</div>
                    </div>
                    @if($journal->isPosted())
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Posted By</div>
                        <div class="small">{{ $journal->postedBy?->name ?? '—' }}
                            @if($journal->posted_at)
                                <span class="text-muted">· {{ $journal->posted_at->format('d M Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($journal->isVoided())
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Voided By</div>
                        <div class="small">{{ $journal->voidedBy?->name ?? '—' }}
                            @if($journal->voided_at)
                                <span class="text-muted">· {{ $journal->voided_at->format('d M Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Entries Table --}}
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th>Narration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journal->entries as $entry)
                            <tr>
                                <td class="font-monospace small">{{ $entry->account->code }}</td>
                                <td class="small">{{ $entry->account->name }}</td>
                                <td class="text-end font-monospace small">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                </td>
                                <td class="text-end font-monospace small">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                </td>
                                <td class="small text-muted">{{ $entry->narration }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="2" class="text-end">Totals</td>
                                <td class="text-end font-monospace">{{ number_format($journal->total_debit, 2) }}</td>
                                <td class="text-end font-monospace">{{ number_format($journal->total_credit, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Actions --}}
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header fw-semibold small"><i class="bi bi-gear me-2 text-secondary"></i>Actions</div>
            <div class="card-body d-grid gap-2">

                @if($journal->isDraft())
                @can('finance.gl.post')
                <form method="POST" action="{{ route('finance.gl.journals.post', $journal) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm w-100"
                            onclick="return confirm('Post this journal? This will make it permanent.')">
                        <i class="bi bi-check-circle me-1"></i>Post Journal
                    </button>
                </form>
                @endcan
                @endif

                @if($journal->isPosted())
                @can('finance.gl.void')
                <div class="border rounded p-2">
                    <div class="text-muted small fw-semibold mb-2">Void Journal</div>
                    <form method="POST" action="{{ route('finance.gl.journals.void', $journal) }}">
                        @csrf
                        <div class="mb-2">
                            <input type="text" name="reason" class="form-control form-control-sm"
                                   placeholder="Reason (optional)" maxlength="255">
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm w-100"
                                onclick="return confirm('Void this journal? A reversal entry will be created.')">
                            <i class="bi bi-x-circle me-1"></i>Void Journal
                        </button>
                    </form>
                </div>
                @endcan
                @endif

                @if($journal->isVoided())
                <div class="alert alert-danger py-2 mb-0 small">
                    <i class="bi bi-x-octagon me-1"></i>This journal has been voided.
                </div>
                @endif

                <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to List
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
