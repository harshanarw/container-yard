@extends('layouts.app')

@section('title', 'Bank Reconciliation')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.bank-reconciliation.index') }}">Bank Reconciliation</a></li>
    <li class="breadcrumb-item active">{{ $bankReconciliation->statement_date->format('d M Y') }}</li>
@endsection

@section('content')
@php
    $r = $bankReconciliation;
    $draft = $r->isDraft();
    $money = fn ($n) => number_format((float) $n, 2);
    $entries = $summary['entries'];
    $uncleared = $entries->filter(fn ($e) => is_null($e->bank_reconciliation_id));
@endphp

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>{{ $r->bankAccount->bank_name }} — {{ $r->bankAccount->account_name }}
            @if($r->isCompleted())<span class="badge bg-success-subtle text-success align-middle"><i class="bi bi-lock-fill me-1"></i>Completed</span>
            @else<span class="badge bg-warning-subtle text-warning align-middle">Draft</span>@endif
        </h4>
        <p class="text-muted mb-0 small">
            Statement to {{ $r->statement_date->format('d M Y') }} · GL {{ optional($r->bankAccount->glAccount)->code }} · all amounts in {{ $base }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap d-print-none">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        @if($r->isCompleted())
            @can('finance.bank-reconciliation.edit')
            <form method="POST" action="{{ route('finance.bank-reconciliation.reopen', $r) }}" onsubmit="return confirm('Re-open this reconciliation for editing?')">
                @csrf<button class="btn btn-outline-warning btn-sm"><i class="bi bi-unlock me-1"></i>Re-open</button>
            </form>
            @endcan
        @endif
        <a href="{{ route('finance.bank-reconciliation.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Statement Closing</div>
        <div class="fw-bold fs-5 font-monospace">{{ $base }} {{ $money($summary['statement_balance']) }}</div>
        <div class="text-muted" style="font-size:.72rem;">opening {{ $money($summary['opening_balance']) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Cleared Balance</div>
        <div class="fw-bold fs-5 font-monospace">{{ $base }} {{ $money($summary['cleared_balance']) }}</div>
        <div class="text-muted" style="font-size:.72rem;">{{ $summary['cleared_count'] }} cleared</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3 {{ $summary['is_balanced'] ? 'border-success' : 'border-danger' }}">
        <div class="text-muted small">Difference</div>
        <div class="fw-bold fs-4 font-monospace {{ $summary['is_balanced'] ? 'text-success' : 'text-danger' }}">{{ $base }} {{ $money($summary['difference']) }}</div>
        <div class="text-muted" style="font-size:.72rem;">{{ $summary['is_balanced'] ? 'Balanced' : 'Not balanced yet' }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Ledger Balance</div>
        <div class="fw-bold fs-5 font-monospace">{{ $base }} {{ $money($summary['book_balance']) }}</div>
        <div class="text-muted" style="font-size:.72rem;">GL as of {{ $r->statement_date->format('d M') }}</div>
    </div></div>
</div>

<div class="row g-3">
    {{-- Reconciliation statement + completion --}}
    <div class="col-lg-4">
        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2"><strong class="small">Reconciliation Statement</strong></div>
            <div class="card-body py-2">
                <table class="table table-sm mb-0 small">
                    <tbody>
                        <tr><td>Balance per bank statement</td><td class="text-end font-monospace">{{ $money($summary['statement_balance']) }}</td></tr>
                        <tr><td>Add: Deposits in transit</td><td class="text-end font-monospace">{{ $money($summary['deposits_in_transit']) }}</td></tr>
                        <tr><td>Less: Unpresented cheques</td><td class="text-end font-monospace">({{ $money($summary['unpresented_cheques']) }})</td></tr>
                        <tr class="fw-bold border-top"><td>Adjusted bank balance</td><td class="text-end font-monospace">{{ $money($summary['adjusted_bank_balance']) }}</td></tr>
                        <tr class="text-muted"><td>Balance per ledger</td><td class="text-end font-monospace">{{ $money($summary['book_balance']) }}</td></tr>
                        <tr class="fw-bold {{ abs($summary['tie_out_difference']) < 0.01 ? 'text-success' : 'text-danger' }}">
                            <td>Tie-out difference</td><td class="text-end font-monospace">{{ $money($summary['tie_out_difference']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($draft)
        <div class="card content-card mb-3">
            <div class="card-body py-3 text-center">
                @if($summary['is_balanced'])
                    <p class="small text-success mb-2"><i class="bi bi-check-circle me-1"></i>The difference is zero — you can finish.</p>
                    @can('finance.bank-reconciliation.edit')
                    <form method="POST" action="{{ route('finance.bank-reconciliation.complete', $r) }}" onsubmit="return confirm('Complete and lock this reconciliation?')">
                        @csrf<button class="btn btn-success w-100"><i class="bi bi-lock-fill me-1"></i>Complete Reconciliation</button>
                    </form>
                    @endcan
                @else
                    <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Clear items until the difference is <strong>{{ $base }} 0.00</strong> to complete.</p>
                @endif
            </div>
        </div>
        @can('finance.bank-reconciliation.delete')
        <form method="POST" action="{{ route('finance.bank-reconciliation.destroy', $r) }}" onsubmit="return confirm('Delete this draft reconciliation and release its cleared transactions?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>Delete Reconciliation</button>
        </form>
        @endcan
        @else
        <div class="alert alert-success small"><i class="bi bi-lock-fill me-1"></i>Completed {{ optional($r->reconciled_at)->format('d M Y H:i') }} by {{ optional($r->reconciledBy)->name }}.</div>
        @endif
    </div>

    {{-- Book transactions --}}
    <div class="col-lg-8">
        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                <strong class="small"><i class="bi bi-journal-text me-1"></i>Book Transactions (ledger)</strong>
                <span class="text-muted small">{{ $summary['cleared_count'] }} cleared / {{ $entries->count() }} total</span>
            </div>
            <div class="table-responsive" style="max-height:460px;overflow-y:auto;">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <th>Date</th>
                            <th>Journal</th>
                            <th>Narration</th>
                            <th class="text-end">Deposit</th>
                            <th class="text-end">Withdrawal</th>
                            <th class="text-center">Cleared</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $e)
                        @php $isCleared = (int) $e->bank_reconciliation_id === $r->id && $e->cleared_at; @endphp
                        <tr class="{{ $isCleared ? 'table-success' : '' }}">
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($e->j_date)->format('d M Y') }}</td>
                            <td class="font-monospace" style="font-size:.72rem;">{{ $e->j_no }}<div class="text-muted">{{ ucfirst($e->j_type) }}</div></td>
                            <td>{{ $e->narration ?: $e->j_narration }}</td>
                            <td class="text-end font-monospace">{{ (float) $e->debit > 0 ? $money($e->debit) : '' }}</td>
                            <td class="text-end font-monospace">{{ (float) $e->credit > 0 ? $money($e->credit) : '' }}</td>
                            <td class="text-center">
                                @if($draft)
                                @can('finance.bank-reconciliation.edit')
                                <form method="POST" action="{{ route('finance.bank-reconciliation.toggle-clear', $r) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="gl_entry_id" value="{{ $e->id }}">
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="Toggle cleared">
                                        <i class="bi {{ $isCleared ? 'bi-check-square-fill text-success' : 'bi-square text-muted' }} fs-6"></i>
                                    </button>
                                </form>
                                @endcan
                                @else
                                    <i class="bi {{ $isCleared ? 'bi-check-square-fill text-success' : 'bi-square text-muted' }}"></i>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No posted ledger entries on this bank account up to {{ $r->statement_date->format('d M Y') }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Bank statement lines --}}
<div class="card content-card">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong class="small"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Bank Statement</strong>
        @if($draft)
        @can('finance.bank-reconciliation.edit')
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="{{ route('finance.bank-reconciliation.import', $r) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                @csrf
                <select name="format" class="form-select form-select-sm" style="width:auto;" required>
                    @foreach($presets as $key => $p)
                        <option value="{{ $key }}" {{ $key === 'generic' ? 'selected' : '' }}>{{ $p['label'] }}</option>
                    @endforeach
                </select>
                <input type="file" name="statement_file" class="form-control form-control-sm" accept=".csv,.txt" style="width:auto;" required>
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Import CSV</button>
            </form>
            <form method="POST" action="{{ route('finance.bank-reconciliation.auto-match', $r) }}">
                @csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-magic me-1"></i>Auto-match</button>
            </form>
        </div>
        @endcan
        @endif
    </div>

    @if($statementLines->isEmpty())
        <div class="card-body text-center text-muted py-4 small">
            No statement lines imported. Upload a CSV export from your bank, or reconcile using the ledger checkboxes above.
        </div>
    @else
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-end">Deposit</th>
                    <th class="text-end">Withdrawal</th>
                    <th class="text-center">Status</th>
                    @if($draft)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($statementLines as $line)
                <tr class="{{ $line->isMatched() ? 'table-success' : '' }}">
                    <td class="text-nowrap">{{ $line->txn_date->format('d M Y') }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="font-monospace" style="font-size:.72rem;">{{ $line->reference }}</td>
                    <td class="text-end font-monospace">{{ (float) $line->deposit > 0 ? $money($line->deposit) : '' }}</td>
                    <td class="text-end font-monospace">{{ (float) $line->withdrawal > 0 ? $money($line->withdrawal) : '' }}</td>
                    <td class="text-center">
                        @if($line->isMatched())
                            <span class="badge bg-success-subtle text-success border">Matched</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border">Unmatched</span>
                        @endif
                    </td>
                    @if($draft)
                    <td class="text-end text-nowrap">
                        @can('finance.bank-reconciliation.edit')
                        @if($line->isMatched())
                            <form method="POST" action="{{ route('finance.bank-reconciliation.lines.unmatch', [$r, $line]) }}" class="d-inline">
                                @csrf<button class="btn btn-sm btn-outline-warning py-0"><i class="bi bi-x-circle"></i></button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('finance.bank-reconciliation.lines.match', [$r, $line]) }}" class="d-inline">
                                @csrf
                                <div class="input-group input-group-sm" style="width:260px;display:inline-flex;">
                                    <select name="gl_entry_id" class="form-select form-select-sm" required>
                                        <option value="">Match to ledger entry…</option>
                                        @foreach($uncleared as $e)
                                            <option value="{{ $e->id }}">{{ \Carbon\Carbon::parse($e->j_date)->format('d/m') }} · {{ $money((float) $e->debit - (float) $e->credit) }} · {{ \Illuminate\Support\Str::limit($e->narration ?: $e->j_narration, 24) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-success py-0"><i class="bi bi-link"></i></button>
                                </div>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 js-adjust"
                                data-action="{{ route('finance.bank-reconciliation.lines.adjust', [$r, $line]) }}"
                                data-desc="{{ $line->description }}"
                                data-amount="{{ $money((float) $line->deposit - (float) $line->withdrawal) }}"
                                data-bs-toggle="modal" data-bs-target="#adjustModal" title="Book adjustment">
                                <i class="bi bi-journal-plus"></i>
                            </button>
                            <form method="POST" action="{{ route('finance.bank-reconciliation.lines.destroy', [$r, $line]) }}" class="d-inline" onsubmit="return confirm('Remove this statement line?')">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                        @endcan
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Adjustment modal --}}
@if($draft)
@can('finance.bank-reconciliation.edit')
<div class="modal fade" id="adjustModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="adjustForm" class="modal-content">
      @csrf
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-plus me-1"></i>Book Adjustment Journal</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">Post the offsetting entry for a bank charge, interest or direct debit that is not yet in the ledger. The bank leg is created and cleared automatically.</p>
        <div class="mb-2"><strong id="adjDesc" class="small"></strong> · <span id="adjAmount" class="font-monospace small"></span> {{ $base }}</div>
        <label class="form-label small fw-semibold">Contra Account <span class="text-danger">*</span></label>
        <select name="contra_account_id" class="form-select" required>
            <option value="">— Select account —</option>
            @foreach($accounts as $a)
                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Deposit → credited to this account (e.g. interest income). Withdrawal → debited to this account (e.g. bank charges).</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-journal-check me-1"></i>Post & Match</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.js-adjust').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('adjustForm').action = this.dataset.action;
        document.getElementById('adjDesc').textContent = this.dataset.desc || 'Adjustment';
        document.getElementById('adjAmount').textContent = this.dataset.amount;
    });
});
</script>
@endpush
@endcan
@endif

@endsection
