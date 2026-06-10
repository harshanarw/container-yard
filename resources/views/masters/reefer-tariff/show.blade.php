@extends('layouts.app')
@section('title', 'Edit Reefer Tariff')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('masters.reefer-tariff.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold">{{ $reeferTariff->tariff_name }}</h4>
        <p class="text-muted small mb-0">
            {{ $reeferTariff->customer?->name ?? 'System Default' }} &mdash; {{ $reeferTariff->rate_label }}
        </p>
    </div>
</div>


<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Edit Tariff</div>
            <div class="card-body">
                <form action="{{ route('masters.reefer-tariff.update', $reeferTariff) }}" method="POST">
                    @csrf @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <small class="text-muted">(blank = default)</small></label>
                            <select name="customer_id" class="form-select select2">
                                <option value="">— System Default —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ $reeferTariff->customer_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tariff Name <span class="text-danger">*</span></label>
                            <input type="text" name="tariff_name" class="form-control" required value="{{ old('tariff_name', $reeferTariff->tariff_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Billing Mode</label>
                            <select name="billing_mode" class="form-select" id="editBillingMode">
                                <option value="daily"  {{ $reeferTariff->billing_mode === 'daily'  ? 'selected' : '' }}>Daily</option>
                                <option value="hourly" {{ $reeferTariff->billing_mode === 'hourly' ? 'selected' : '' }}>Hourly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Currency</label>
                            <input type="text" name="currency" class="form-control font-monospace" value="{{ old('currency', $reeferTariff->currency) }}" maxlength="3" required>
                        </div>
                        <div class="col-md-4" id="editHourlyRateWrap">
                            <label class="form-label">Hourly Rate</label>
                            <input type="number" name="hourly_rate" class="form-control" step="0.01" min="0" value="{{ old('hourly_rate', $reeferTariff->hourly_rate) }}">
                        </div>
                        <div class="col-md-4" id="editDailyRateWrap">
                            <label class="form-label">Daily Rate</label>
                            <input type="number" name="daily_rate" class="form-control" step="0.01" min="0" value="{{ old('daily_rate', $reeferTariff->daily_rate) }}">
                        </div>
                        <div class="col-md-4" id="editFreeHoursWrap">
                            <label class="form-label">Free Hours</label>
                            <input type="number" name="free_hours" class="form-control" value="{{ old('free_hours', $reeferTariff->free_hours) }}" min="0" max="168">
                        </div>
                        <div class="col-md-4" id="editFreeDaysWrap">
                            <label class="form-label">Free Days</label>
                            <input type="number" name="free_days" class="form-control" value="{{ old('free_days', $reeferTariff->free_days) }}" min="0" max="365">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Charge</label>
                            <input type="number" name="minimum_charge" class="form-control" step="0.01" min="0" value="{{ old('minimum_charge', $reeferTariff->minimum_charge) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Valid From</label>
                            <input type="date" name="valid_from" class="form-control" required value="{{ old('valid_from', $reeferTariff->valid_from?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valid To</label>
                            <input type="date" name="valid_to" class="form-control" value="{{ old('valid_to', $reeferTariff->valid_to?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1" {{ $reeferTariff->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="editIsActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes', $reeferTariff->notes) }}</textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Tariff Info</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5 text-muted">Created by</dt>
                    <dd class="col-sm-7">{{ $reeferTariff->createdBy?->name ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Created at</dt>
                    <dd class="col-sm-7">{{ $reeferTariff->created_at?->format('d M Y H:i') }}</dd>
                    <dt class="col-sm-5 text-muted">Updated by</dt>
                    <dd class="col-sm-7">{{ $reeferTariff->updatedBy?->name ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Updated at</dt>
                    <dd class="col-sm-7">{{ $reeferTariff->updated_at?->format('d M Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modeSelect  = document.getElementById('editBillingMode');
    const hourlyRate  = document.getElementById('editHourlyRateWrap');
    const dailyRate   = document.getElementById('editDailyRateWrap');
    const freeHours   = document.getElementById('editFreeHoursWrap');
    const freeDays    = document.getElementById('editFreeDaysWrap');

    function toggleMode() {
        const isHourly = modeSelect.value === 'hourly';
        hourlyRate.style.display = isHourly ? '' : 'none';
        dailyRate.style.display  = isHourly ? 'none' : '';
        freeHours.style.display  = isHourly ? '' : 'none';
        freeDays.style.display   = isHourly ? 'none' : '';
    }

    modeSelect?.addEventListener('change', toggleMode);
    toggleMode();
})();
</script>
@endpush
