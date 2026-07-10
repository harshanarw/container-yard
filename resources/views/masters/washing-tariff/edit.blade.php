@extends('layouts.app')

@php $isNew = !$tariff->exists; @endphp

@section('title', $isNew ? 'Add Washing Rate' : 'Edit Washing Rate')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.washing-tariff.index') }}" class="text-decoration-none">Washing Tariff</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? 'Add' : 'Edit' }}</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-droplet me-2 text-primary"></i>{{ $isNew ? 'Add Washing Rate' : 'Edit Washing Rate' }}</h4>
    <p class="text-muted mb-0 small">One flat per-container rate for a wash scope × type × size. Leave customer blank for the default rate and size blank to apply to all sizes.</p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ $isNew ? route('masters.washing-tariff.store') : route('masters.washing-tariff.update', $tariff) }}">
    @csrf
    @unless($isNew) @method('PATCH') @endunless

    <div class="card content-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Applies To</label>
                    <select name="customer_id" class="form-select">
                        <option value="">Default (all customers)</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (string) old('customer_id', $tariff->customer_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">A customer-specific rate overrides the default for that customer.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Scope <span class="text-danger">*</span></label>
                    <select name="wash_scope" class="form-select" required>
                        @foreach(\App\Models\WashingTariff::SCOPES as $k => $label)
                            <option value="{{ $k }}" {{ old('wash_scope', $tariff->wash_scope) === $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Wash Type <span class="text-danger">*</span></label>
                    <select name="wash_type" class="form-select" required>
                        @foreach(\App\Models\WashingTariff::TYPES as $k => $label)
                            <option value="{{ $k }}" {{ old('wash_type', $tariff->wash_type) === $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Container Size</label>
                    <select name="container_size" class="form-select">
                        <option value="">All sizes</option>
                        @foreach(\App\Models\WashingTariff::SIZES as $s)
                            <option value="{{ $s }}" {{ old('container_size', $tariff->container_size) === $s ? 'selected' : '' }}>{{ $s }}'</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                    <input type="text" name="currency" class="form-control text-uppercase" maxlength="3"
                           value="{{ old('currency', $tariff->currency ?? 'USD') }}" required>
                    <div class="form-text">Usually USD (tariff currency).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Rate <span class="text-danger">*</span></label>
                    <input type="number" name="rate" class="form-control" min="0" step="0.01"
                           value="{{ old('rate', $tariff->rate) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Min Charge</label>
                    <input type="number" name="min_charge" class="form-control" min="0" step="0.01"
                           value="{{ old('min_charge', $tariff->min_charge) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Charge Code</label>
                    <select name="charge_code_id" class="form-select">
                        <option value="">—</option>
                        @foreach($chargeCodes as $cc)
                            <option value="{{ $cc->id }}" data-tax="{{ $cc->tax_code_id }}"
                                {{ (string) old('charge_code_id', $tariff->charge_code_id) === (string) $cc->id ? 'selected' : '' }}>
                                {{ $cc->code }} — {{ $cc->description }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Cleaning charge codes (WSH, PSWSH…).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tax Code</label>
                    <select name="tax_code_id" class="form-select">
                        <option value="">—</option>
                        @foreach($taxCodes as $tc)
                            <option value="{{ $tc->id }}" {{ (string) old('tax_code_id', $tariff->tax_code_id) === (string) $tc->id ? 'selected' : '' }}>
                                {{ $tc->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1"
                               {{ old('is_active', $tariff->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Valid From</label>
                    <input type="date" name="valid_from" class="form-control"
                           value="{{ old('valid_from', optional($tariff->valid_from)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Valid To</label>
                    <input type="date" name="valid_to" class="form-control"
                           value="{{ old('valid_to', optional($tariff->valid_to)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes</label>
                    <input type="text" name="notes" class="form-control" maxlength="500"
                           value="{{ old('notes', $tariff->notes) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $isNew ? 'Add Rate' : 'Save Changes' }}</button>
        <a href="{{ route('masters.washing-tariff.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
    // Default the tax code from the selected charge code when none is chosen.
    document.querySelector('[name="charge_code_id"]')?.addEventListener('change', function () {
        const tax = this.selectedOptions[0]?.dataset.tax || '';
        const taxSel = document.querySelector('[name="tax_code_id"]');
        if (taxSel && tax && !taxSel.value) taxSel.value = tax;
    });
</script>
@endpush

@endsection
