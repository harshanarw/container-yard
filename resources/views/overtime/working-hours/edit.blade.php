@extends('layouts.app')

@php $isNew = ! $set->exists; @endphp

@section('title', $isNew ? 'New Working-Hour Set' : 'Edit Working Hours')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item"><a href="{{ route('overtime.working-hours.index') }}">Working Hours</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? 'New Set' : $set->name }}</li>
@endsection

@section('content')

<div class="page-header mb-3">
    <h4><i class="bi bi-clock me-2 text-primary"></i>{{ $isNew ? 'New Working-Hour Set' : 'Edit Working Hours' }}</h4>
    <p class="text-muted mb-0 small">
        Untick a day to mark it closed — the engine then treats every hour of that day as overtime.
    </p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-circle me-1"></i>Please correct the following:
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ $isNew ? route('overtime.working-hours.store') : route('overtime.working-hours.update', $set) }}">
    @csrf
    @unless($isNew) @method('PATCH') @endunless

    <div class="card content-card mb-3">
        <div class="card-header py-2"><i class="bi bi-tag me-2 text-primary"></i>Set Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                           value="{{ old('name', $set->name) }}" required maxlength="100" placeholder="e.g. Default Working Hours">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select form-select-sm">
                        @foreach(\App\Models\WorkingHourSet::STATUSES as $key => $label)
                            <option value="{{ $key }}" {{ old('status', $set->status) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Effective From</label>
                    <input type="date" name="effective_from" class="form-control form-control-sm"
                           value="{{ old('effective_from', $set->effective_from?->toDateString()) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Effective To</label>
                    <input type="date" name="effective_to" class="form-control form-control-sm"
                           value="{{ old('effective_to', $set->effective_to?->toDateString()) }}">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="isDefault" name="is_default" value="1"
                               {{ old('is_default', $set->is_default) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="isDefault">Default</label>
                    </div>
                </div>
            </div>
            <div class="form-text mt-2">
                The set flagged <strong>Default</strong> (and Active) is the one the overtime engine reads. Flagging this one
                un-flags any other.
            </div>
        </div>
    </div>

    <div class="card content-card mb-3">
        <div class="card-header py-2"><i class="bi bi-calendar-week me-2 text-primary"></i>Weekly Schedule</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:16%">Day</th>
                            <th style="width:12%" class="text-center">Working Day</th>
                            <th style="width:18%">Normal Start</th>
                            <th style="width:18%">Normal End</th>
                            <th>After-Hours Policy</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach(\App\Models\WeeklyWorkingHour::DAYS as $key => $label)
                        @php
                            $row     = $days[$key] ?? [];
                            $regular = (bool) old("days.$key.is_regular_working_day", $row['is_regular_working_day'] ?? false);
                            $start   = old("days.$key.normal_start_time", $row['normal_start_time'] ?? null);
                            $end     = old("days.$key.normal_end_time", $row['normal_end_time'] ?? null);
                            $policy  = old("days.$key.after_hours_policy", $row['after_hours_policy'] ?? 'ot_required');
                        @endphp
                        <tr data-day-row="{{ $key }}">
                            <td class="ps-3 fw-semibold small">{{ $label }}</td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input js-working-day" type="checkbox" role="switch"
                                           name="days[{{ $key }}][is_regular_working_day]" value="1" {{ $regular ? 'checked' : '' }}>
                                </div>
                            </td>
                            <td>
                                <input type="time" name="days[{{ $key }}][normal_start_time]"
                                       class="form-control form-control-sm js-day-time @error("days.$key.normal_start_time") is-invalid @enderror"
                                       value="{{ $start }}" {{ $regular ? '' : 'disabled' }}>
                                @error("days.$key.normal_start_time")<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="time" name="days[{{ $key }}][normal_end_time]"
                                       class="form-control form-control-sm js-day-time @error("days.$key.normal_end_time") is-invalid @enderror"
                                       value="{{ $end }}" {{ $regular ? '' : 'disabled' }}>
                                @error("days.$key.normal_end_time")<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="days[{{ $key }}][after_hours_policy]" class="form-select form-select-sm">
                                    @foreach(\App\Models\WeeklyWorkingHour::AFTER_HOURS_POLICIES as $pk => $pl)
                                        <option value="{{ $pk }}" {{ $policy === $pk ? 'selected' : '' }}>{{ $pl }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>{{ $isNew ? 'Create Set' : 'Save Changes' }}</button>
        <a href="{{ route('overtime.working-hours.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
(function () {
    // A closed day has no window to enter — grey the time inputs out so the state is
    // obvious. The server nulls them regardless, so this is purely a clarity aid.
    document.querySelectorAll('.js-working-day').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var row = toggle.closest('tr');
            row.querySelectorAll('.js-day-time').forEach(function (input) {
                input.disabled = ! toggle.checked;
            });
        });
    });
})();
</script>
@endpush

@endsection
