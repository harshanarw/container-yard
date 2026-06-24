@extends('layouts.app')

@section('title', 'Number Sequences')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.index') }}" class="text-decoration-none">Settings</a></li>
    <li class="breadcrumb-item active">Number Sequences</li>
@endsection

@push('styles')
<style>
    .seq-card { transition: box-shadow .15s; }
    .seq-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .preview-badge { font-family: monospace; font-size: .82rem; letter-spacing: .02em; }
    .reset-period-badge { font-size: .72rem; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-hash me-2 text-primary"></i>Number Sequences</h4>
        <p class="text-muted mb-0 small">
            Configure how system-generated reference numbers are formatted.
            The company prefix set in
            <a href="{{ route('settings.company.index') }}" class="text-decoration-none">Company Settings</a>
            is automatically prepended when enabled.
        </p>
    </div>
    <a href="{{ route('settings.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Company prefix reminder --}}
@php
    $companyPrefix = strtoupper(trim(\App\Models\CompanySetting::current()->company_prefix ?? ''));
@endphp
@if(!$companyPrefix)
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Company prefix is not set.</strong>
    Numbers will be generated without a company prefix until you
    <a href="{{ route('settings.company.index') }}" class="alert-link">configure it in Company Settings</a>.
</div>
@else
<div class="alert alert-info mb-3 py-2 small">
    <i class="bi bi-building me-1"></i>
    Company prefix: <strong class="font-monospace">{{ $companyPrefix }}</strong>
    &nbsp;·&nbsp;
    Sequences with "Use company prefix" enabled will be formatted as
    <code>{{ $companyPrefix }}-{MODULE}-{…}-{NNNN}</code>
</div>
@endif

<div class="row g-3">
@foreach($sequences as $seq)
<div class="col-12 col-lg-6">
    <div class="card content-card seq-card h-100">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-123 text-primary"></i>
                <span class="fw-semibold small">{{ $seq->label }}</span>
                <code class="text-muted" style="font-size:.72rem;">{{ $seq->module_code }}</code>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($seq->is_system)
                    <span class="badge bg-secondary-subtle text-secondary reset-period-badge">System</span>
                @endif
                <span class="badge {{ $seq->reset_period === 'never' ? 'bg-light text-muted' : 'bg-primary-subtle text-primary' }} reset-period-badge">
                    {{ ucfirst($seq->reset_period) }} reset
                </span>
            </div>
        </div>
        <div class="card-body py-3">

            {{-- Current counter / preview --}}
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="text-muted" style="font-size:.72rem;">CURRENT COUNTER</div>
                    <div class="fw-semibold font-monospace">{{ number_format($seq->last_number) }}</div>
                    @if($seq->current_period)
                        <div class="text-muted" style="font-size:.68rem;">Period: {{ $seq->current_period }}</div>
                    @endif
                </div>
                <div class="text-end">
                    <div class="text-muted" style="font-size:.72rem;">NEXT NUMBER PREVIEW</div>
                    <span class="badge bg-success-subtle text-success preview-badge"
                          id="preview-{{ $seq->id }}"
                          data-seq-id="{{ $seq->id }}"
                          data-preview-url="{{ route('settings.number-sequences.preview', $seq) }}">
                        Loading…
                    </span>
                </div>
            </div>

            {{-- Edit form --}}
            <form method="POST" action="{{ route('settings.number-sequences.update', $seq) }}"
                  id="form-{{ $seq->id }}">
                @csrf
                @method('PUT')
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-1">Label</label>
                        <input type="text" name="label" class="form-control form-control-sm"
                               value="{{ old('label', $seq->label) }}" required maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small mb-1">Module Prefix <span class="text-danger">*</span></label>
                        <input type="text" name="prefix" class="form-control form-control-sm text-uppercase"
                               value="{{ old('prefix', $seq->prefix) }}"
                               required maxlength="20" placeholder="e.g. RE"
                               oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small mb-1">Separator</label>
                        <input type="text" name="separator" class="form-control form-control-sm text-center"
                               value="{{ old('separator', $seq->separator) }}"
                               required maxlength="1" placeholder="-">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small mb-1">Date in Number</label>
                        <select name="date_format" class="form-select form-select-sm">
                            <option value="" {{ !$seq->date_format ? 'selected' : '' }}>None</option>
                            <option value="Ym"  {{ $seq->date_format === 'Ym'  ? 'selected' : '' }}>YYYYMM (monthly)</option>
                            <option value="Y"   {{ $seq->date_format === 'Y'   ? 'selected' : '' }}>YYYY (yearly)</option>
                            <option value="ym"  {{ $seq->date_format === 'ym'  ? 'selected' : '' }}>YYMM (monthly, short)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small mb-1">Counter Reset</label>
                        <select name="reset_period" class="form-select form-select-sm">
                            <option value="never"   {{ $seq->reset_period === 'never'   ? 'selected' : '' }}>Never</option>
                            <option value="monthly" {{ $seq->reset_period === 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="yearly"  {{ $seq->reset_period === 'yearly'  ? 'selected' : '' }}>Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small mb-1">Digit Padding</label>
                        <select name="seq_padding" class="form-select form-select-sm">
                            @foreach([3,4,5,6,7,8] as $p)
                            <option value="{{ $p }}" {{ $seq->seq_padding == $p ? 'selected' : '' }}>
                                {{ $p }} digits ({{ str_repeat('0', $p - 1) }}1)
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="use_company_prefix"
                                   value="1" id="ucp-{{ $seq->id }}"
                                   {{ $seq->use_company_prefix ? 'checked' : '' }}>
                            <label class="form-check-label small" for="ucp-{{ $seq->id }}">
                                Use company prefix
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Save
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="refreshPreview({{ $seq->id }})">
                        <i class="bi bi-eye me-1"></i>Preview
                    </button>
                    @if(auth()->user()->isSystemAdmin())
                    <button type="button" class="btn btn-outline-danger btn-sm ms-auto"
                            onclick="confirmReset({{ $seq->id }}, {{ json_encode($seq->label) }})">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Counter
                    </button>
                    @endif
                </div>
            </form>

        </div>
    </div>
</div>
@endforeach
</div>

{{-- Hidden reset forms (one per sequence) --}}
@foreach($sequences as $seq)
<form method="POST" action="{{ route('settings.number-sequences.reset', $seq) }}"
      id="reset-form-{{ $seq->id }}" class="d-none">
    @csrf
</form>
@endforeach

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // Load all previews on page load
    document.querySelectorAll('[data-preview-url]').forEach(function (el) {
        loadPreview(el);
    });

    window.refreshPreview = function (seqId) {
        var el = document.getElementById('preview-' + seqId);
        if (el) loadPreview(el);
    };

    function loadPreview(el) {
        var url = el.dataset.previewUrl;
        if (!url) return;
        el.textContent = '…';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { el.textContent = d.preview || '—'; })
            .catch(function ()  { el.textContent = 'error'; });
    }

    window.confirmReset = function (seqId, label) {
        if (!confirm('Reset the counter for "' + label + '" back to 0?\n\nThis action cannot be undone. The next generated number will start from 1.')) {
            return;
        }
        document.getElementById('reset-form-' + seqId).submit();
    };

    // Auto-refresh preview when any form field changes
    document.querySelectorAll('[id^="form-"]').forEach(function (form) {
        var seqId = form.id.replace('form-', '');
        form.querySelectorAll('input, select').forEach(function (input) {
            // skip label and separator — they don't affect the number format
            if (input.name === 'label') return;
            input.addEventListener('change', function () { refreshPreview(seqId); });
        });
    });
}());
</script>
@endpush
