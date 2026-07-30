@extends('layouts.app')

@section('title', 'Holiday Calendar')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item active">Holiday Calendar</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-calendar-event me-2 text-primary"></i>Holiday Calendar</h4>
        <p class="text-muted mb-0 small">
            Mercantile and public holidays. A holiday overrides the weekly working hours and bills under the
            Sunday / Mercantile Holiday overtime category.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-1">
            <select name="year" class="form-select form-select-sm" style="width:6.5rem" onchange="this.form.submit()">
                @foreach($years as $y)
                    <option value="{{ $y }}" {{ (int) $y === $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        @can('ot.settings.edit')
        <button type="button" class="btn btn-primary btn-sm js-holiday-add" data-bs-toggle="modal" data-bs-target="#holidayModal">
            <i class="bi bi-plus-circle me-1"></i>Add Holiday
        </button>
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-circle me-1"></i>Please correct the following:
    <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- Year-at-a-glance calendar --}}
<div class="card content-card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar3 me-2 text-primary"></i>{{ $year }} at a Glance</span>
        <span class="small text-muted">
            <span class="badge bg-danger-subtle text-danger border">Holiday</span>
            <span class="badge bg-secondary-subtle text-secondary border ms-1">Sunday</span>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @for($m = 1; $m <= 12; $m++)
                @php
                    $first  = \Illuminate\Support\Carbon::create($year, $m, 1);
                    $offset = ($first->dayOfWeekIso - 1);          // Mon = 0
                    $count  = $first->daysInMonth;
                @endphp
                <div class="col-6 col-md-4 col-xl-3 col-xxl-2">
                    <div class="border rounded p-2 h-100">
                        <div class="fw-semibold small mb-1">{{ $first->format('F') }}</div>
                        <table class="table table-borderless table-sm mb-0" style="font-size:.7rem">
                            <thead>
                                <tr class="text-muted">
                                    @foreach(['M','T','W','T','F','S','S'] as $d)<th class="text-center p-0">{{ $d }}</th>@endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                @for($i = 0; $i < $offset; $i++)<td class="p-0"></td>@endfor
                                @for($d = 1; $d <= $count; $d++)
                                    @php
                                        $date    = \Illuminate\Support\Carbon::create($year, $m, $d);
                                        $key     = $date->toDateString();
                                        $holiday = $byDate->get($key);
                                        $isSun   = $date->isSunday();
                                    @endphp
                                    <td class="text-center p-0">
                                        @if($holiday && $holiday->active)
                                            <span class="badge bg-danger-subtle text-danger border w-100 px-0"
                                                  title="{{ $holiday->holiday_name }} — {{ $holiday->overrideLabel() }}">{{ $d }}</span>
                                        @elseif($holiday)
                                            <span class="badge bg-light text-muted border w-100 px-0 text-decoration-line-through"
                                                  title="{{ $holiday->holiday_name }} (inactive)">{{ $d }}</span>
                                        @elseif($isSun)
                                            <span class="text-secondary">{{ $d }}</span>
                                        @else
                                            {{ $d }}
                                        @endif
                                    </td>
                                    @if($date->dayOfWeekIso === 7 && $d < $count)</tr><tr>@endif
                                @endfor
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endfor
        </div>
    </div>
</div>

{{-- Detail list --}}
<div class="card content-card">
    <div class="card-header py-2">
        <i class="bi bi-list-ul me-2 text-primary"></i>{{ $holidays->count() }} Holiday(s) in {{ $year }}
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Holiday</th>
                        <th>Type</th>
                        <th class="text-center">Mercantile</th>
                        <th>Working Hours</th>
                        <th>OT Category</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($holidays as $h)
                <tr class="{{ $h->active ? '' : 'opacity-50' }}">
                    <td class="ps-3 small text-nowrap">
                        <span class="fw-semibold">{{ $h->holiday_date->format('d M') }}</span>
                        <span class="text-muted">{{ $h->holiday_date->format('D') }}</span>
                    </td>
                    <td class="small">
                        {{ $h->holiday_name }}
                        @if($h->remarks)<i class="bi bi-info-circle text-muted ms-1" title="{{ $h->remarks }}"></i>@endif
                    </td>
                    <td class="small">{{ $h->typeLabel() }}</td>
                    <td class="text-center">
                        @if($h->is_mercantile)<i class="bi bi-check-circle-fill text-success"></i>@else<span class="text-muted">—</span>@endif
                    </td>
                    <td class="small">{{ $h->overrideLabel() }}</td>
                    <td class="small text-muted">
                        {{ \App\Models\OtTariffRule::DAY_CATEGORIES[$h->effectiveDayCategory()] ?? $h->effectiveDayCategory() }}
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $h->active ? 'success' : 'secondary' }}">{{ $h->active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="text-end pe-3 text-nowrap">
                        @can('ot.settings.edit')
                        {{-- Payload goes through {{ }} so it is HTML-escaped: holiday names
                             legitimately contain apostrophes (e.g. "Workers' Day"). --}}
                        <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 js-holiday-edit" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#holidayModal"
                                data-holiday="{{ json_encode($h->only([
                                    'id', 'holiday_name', 'holiday_type', 'is_mercantile', 'working_hour_override',
                                    'custom_start_time', 'custom_end_time', 'ot_day_category_override', 'active', 'remarks',
                                ]) + ['holiday_date' => $h->holiday_date->toDateString()]) }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('overtime.holidays.toggle', $h) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline-{{ $h->active ? 'warning' : 'success' }} btn-xs py-0 px-1"
                                    title="{{ $h->active ? 'Deactivate' : 'Activate' }}">
                                <i class="bi bi-{{ $h->active ? 'pause' : 'play' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('overtime.holidays.destroy', $h) }}" class="d-inline"
                              data-confirm="Remove &quot;{{ $h->holiday_name }}&quot; from the calendar?"
                              data-confirm-title="Delete Holiday" data-confirm-class="btn-danger" data-confirm-label="Delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-xs py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x me-1"></i>No holidays configured for {{ $year }}.
                </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('ot.settings.edit')
{{-- Add / Edit modal --}}
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="holidayForm" action="{{ route('overtime.holidays.store') }}">
            @csrf
            <input type="hidden" name="_method" id="holidayMethod" value="POST">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="holidayModalTitle">Add Holiday</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
                            <input type="date" name="holiday_date" id="hDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" name="holiday_name" id="hName" class="form-control form-control-sm" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Type <span class="text-danger">*</span></label>
                            <select name="holiday_type" id="hType" class="form-select form-select-sm">
                                @foreach(\App\Models\Holiday::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Working Hours <span class="text-danger">*</span></label>
                            <select name="working_hour_override" id="hOverride" class="form-select form-select-sm">
                                @foreach(\App\Models\Holiday::OVERRIDES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">OT Category Override</label>
                            <select name="ot_day_category_override" id="hCategory" class="form-select form-select-sm">
                                <option value="">Derive from type</option>
                                @foreach(\App\Models\Holiday::DAY_CATEGORY_OVERRIDES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-none" id="hCustomStartWrap">
                            <label class="form-label small mb-1">Custom Start <span class="text-danger">*</span></label>
                            <input type="time" name="custom_start_time" id="hStart" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 d-none" id="hCustomEndWrap">
                            <label class="form-label small mb-1">Custom End <span class="text-danger">*</span></label>
                            <input type="time" name="custom_end_time" id="hEnd" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Remarks</label>
                            <textarea name="remarks" id="hRemarks" class="form-control form-control-sm" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_mercantile" id="hMercantile" value="1" checked>
                                <label class="form-check-label small" for="hMercantile">Mercantile holiday</label>
                            </div>
                            <div class="form-text">Mercantile holidays bill at the Sunday / Mercantile Holiday OT rate.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="active" id="hActive" value="1" checked>
                                <label class="form-check-label small" for="hActive">Active</label>
                            </div>
                            <div class="form-text">Inactive entries are ignored by the overtime engine.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Save Holiday</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var storeUrl  = @json(route('overtime.holidays.store'));
    var updateUrl = @json(route('overtime.holidays.update', ['holiday' => '__ID__']));
    var override  = document.getElementById('hOverride');

    function toggleCustom() {
        var custom = override.value === 'custom';
        document.getElementById('hCustomStartWrap').classList.toggle('d-none', !custom);
        document.getElementById('hCustomEndWrap').classList.toggle('d-none', !custom);
    }

    function fill(h) {
        document.getElementById('holidayModalTitle').textContent = h ? 'Edit Holiday' : 'Add Holiday';
        document.getElementById('holidayForm').action  = h ? updateUrl.replace('__ID__', h.id) : storeUrl;
        document.getElementById('holidayMethod').value = h ? 'PATCH' : 'POST';

        document.getElementById('hDate').value     = h ? h.holiday_date : '';
        document.getElementById('hName').value     = h ? h.holiday_name : '';
        document.getElementById('hType').value     = h ? h.holiday_type : 'mercantile';
        override.value                             = h ? h.working_hour_override : 'closed';
        document.getElementById('hCategory').value = h && h.ot_day_category_override ? h.ot_day_category_override : '';
        // TIME columns come back as HH:MM:SS; the time input needs HH:MM.
        document.getElementById('hStart').value    = h && h.custom_start_time ? String(h.custom_start_time).substring(0, 5) : '';
        document.getElementById('hEnd').value      = h && h.custom_end_time ? String(h.custom_end_time).substring(0, 5) : '';
        document.getElementById('hRemarks').value  = h && h.remarks ? h.remarks : '';
        document.getElementById('hMercantile').checked = h ? !!h.is_mercantile : true;
        document.getElementById('hActive').checked     = h ? !!h.active : true;

        toggleCustom();
    }

    override.addEventListener('change', toggleCustom);

    document.querySelectorAll('.js-holiday-add').forEach(function (btn) {
        btn.addEventListener('click', function () { fill(null); });
    });

    document.querySelectorAll('.js-holiday-edit').forEach(function (btn) {
        btn.addEventListener('click', function () { fill(JSON.parse(btn.dataset.holiday)); });
    });
})();
</script>
@endpush
@endcan

@endsection
