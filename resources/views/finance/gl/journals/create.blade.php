@extends('layouts.app')

@section('title', 'New Manual Journal')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.gl.journals.index') }}">GL Journals</a></li>
    <li class="breadcrumb-item active">New Journal</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-journal-plus me-2 text-primary"></i>New Manual Journal</h4>
        <p class="text-muted mb-0 small">Create a double-entry journal. Debit total must equal credit total.</p>
    </div>
    <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0 small">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('finance.gl.journals.store') }}" id="journalForm">
    @csrf

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card content-card">
                <div class="card-header fw-semibold small"><i class="bi bi-calendar3 me-2 text-secondary"></i>Journal Header</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Journal Date <span class="text-danger">*</span></label>
                        <input type="date" name="journal_date" class="form-control form-control-sm @error('journal_date') is-invalid @enderror"
                               value="{{ old('journal_date', date('Y-m-d')) }}" required>
                        @error('journal_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Narration <span class="text-danger">*</span></label>
                        <input type="text" name="narration" class="form-control form-control-sm @error('narration') is-invalid @enderror"
                               value="{{ old('narration') }}" required maxlength="255" placeholder="Journal description">
                        @error('narration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="post_immediately" id="post_immediately" value="1"
                               {{ old('post_immediately') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="post_immediately">
                            Post Immediately
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            {{-- Totals summary --}}
            <div class="card content-card mb-3">
                <div class="card-body py-2">
                    <div class="d-flex gap-4 align-items-center">
                        <div>
                            <span class="text-muted small">Total Debit:</span>
                            <strong class="ms-1 font-monospace" id="totalDebitDisplay">0.00</strong>
                        </div>
                        <div>
                            <span class="text-muted small">Total Credit:</span>
                            <strong class="ms-1 font-monospace" id="totalCreditDisplay">0.00</strong>
                        </div>
                        <div>
                            <span class="text-muted small">Difference:</span>
                            <strong class="ms-1 font-monospace" id="differenceDisplay">0.00</strong>
                        </div>
                        <div class="ms-auto">
                            <span id="balanceStatus" class="badge bg-secondary-subtle text-secondary" style="font-size:.75rem;">Enter amounts</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lines table --}}
    <div class="card content-card mb-3">
        <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2 text-secondary"></i>Journal Lines</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Row
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="linesTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40%;">Account</th>
                            <th style="width:15%;">Debit</th>
                            <th style="width:15%;">Credit</th>
                            <th>Narration</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="linesBody">
                        {{-- Rows added by JS --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
            <i class="bi bi-save me-1"></i>Save Journal
        </button>
        <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

{{-- Account data for JS — embedded as JSON for @push('scripts') to pick up --}}
@php
$accountsByClass = $accounts->groupBy('classification');
$classOrder = ['asset','liability','equity','income','expense'];
@endphp
<script>
window._journalAccounts   = @json($accountsByClass);
window._journalClassOrder = @json($classOrder);
</script>

@push('scripts')
<script>
$(function () {
    var accountsByClass = window._journalAccounts;
    var classOrder      = window._journalClassOrder;
    var lineCount = 0;

    function buildAccountOptions() {
        var html = '<option value="">— Select account —</option>';
        classOrder.forEach(function (cls) {
            if (!accountsByClass[cls]) return;
            html += '<optgroup label="' + cls.charAt(0).toUpperCase() + cls.slice(1) + '">';
            accountsByClass[cls].forEach(function (acc) {
                html += '<option value="' + acc.id + '">' + acc.code + ' — ' + acc.name + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function addRow(debitVal, creditVal, narrationVal, accountId) {
        var idx = lineCount++;
        var tr = document.createElement('tr');
        tr.setAttribute('data-line', idx);
        tr.innerHTML =
            '<td>' +
                '<select name="lines[' + idx + '][account_id]" class="form-select form-select-sm account-select" required>' +
                buildAccountOptions() +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="number" name="lines[' + idx + '][debit]" class="form-control form-control-sm debit-input" ' +
                       'value="' + (debitVal || '') + '" min="0" step="0.0001" placeholder="0.00">' +
            '</td>' +
            '<td>' +
                '<input type="number" name="lines[' + idx + '][credit]" class="form-control form-control-sm credit-input" ' +
                       'value="' + (creditVal || '') + '" min="0" step="0.0001" placeholder="0.00">' +
            '</td>' +
            '<td>' +
                '<input type="text" name="lines[' + idx + '][narration]" class="form-control form-control-sm" ' +
                       'value="' + (narrationVal || '') + '" maxlength="255" placeholder="Optional">' +
            '</td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row">' +
                    '<i class="bi bi-trash3"></i>' +
                '</button>' +
            '</td>';
        document.getElementById('linesBody').appendChild(tr);

        // Initialize Select2 on the account select in this new row
        var $sel = $(tr).find('.account-select');
        $sel.select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select account —' });

        if (accountId) {
            $sel.val(accountId).trigger('change');
        }

        tr.querySelector('.remove-row').addEventListener('click', function () {
            $sel.select2('destroy');
            tr.remove();
            recalculate();
        });
        tr.querySelector('.debit-input').addEventListener('input', recalculate);
        tr.querySelector('.credit-input').addEventListener('input', recalculate);
        recalculate();
    }

    function recalculate() {
        var totalDebit = 0, totalCredit = 0;
        document.querySelectorAll('#linesBody tr').forEach(function (row) {
            var d = parseFloat(row.querySelector('.debit-input').value) || 0;
            var c = parseFloat(row.querySelector('.credit-input').value) || 0;
            totalDebit  += d;
            totalCredit += c;
        });
        var diff = Math.abs(totalDebit - totalCredit);
        var balanced = totalDebit > 0 && diff < 0.00005;

        document.getElementById('totalDebitDisplay').textContent  = totalDebit.toFixed(2);
        document.getElementById('totalCreditDisplay').textContent = totalCredit.toFixed(2);

        var diffEl = document.getElementById('differenceDisplay');
        diffEl.textContent = diff.toFixed(2);
        diffEl.style.color = balanced ? 'inherit' : '#dc3545';

        var statusEl = document.getElementById('balanceStatus');
        if (balanced) {
            statusEl.className = 'badge bg-success-subtle text-success';
            statusEl.textContent = 'Balanced';
        } else if (totalDebit === 0 && totalCredit === 0) {
            statusEl.className = 'badge bg-secondary-subtle text-secondary';
            statusEl.textContent = 'Enter amounts';
        } else {
            statusEl.className = 'badge bg-danger-subtle text-danger';
            statusEl.textContent = 'Not balanced';
        }

        document.getElementById('submitBtn').disabled = !balanced;
    }

    document.getElementById('addRowBtn').addEventListener('click', function () {
        addRow();
    });

    // Start with 2 rows
    addRow();
    addRow();

    // Pre-populate from old input if validation failed
    @if(old('lines'))
    document.getElementById('linesBody').innerHTML = '';
    lineCount = 0;
    @foreach(old('lines', []) as $i => $line)
    addRow('{{ old("lines.{$i}.debit") }}', '{{ old("lines.{$i}.credit") }}', '{{ old("lines.{$i}.narration") }}', '{{ old("lines.{$i}.account_id") }}');
    @endforeach
    @endif
});
</script>
@endpush

@endsection
