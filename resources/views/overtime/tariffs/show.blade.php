@extends('layouts.app')

@section('title', 'OT Tariff — ' . $version->version_code)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item"><a href="{{ route('overtime.tariffs.index') }}">OT Tariff</a></li>
    <li class="breadcrumb-item active">{{ $version->version_code }}</li>
@endsection

@section('content')

@php
    $statusColors = ['draft' => 'secondary', 'approved' => 'info', 'active' => 'success', 'retired' => 'dark'];
    $canEdit = auth()->user()->can('ot.settings.edit') && ! $locked;
@endphp

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-cash-coin me-2 text-primary"></i>
            <span class="font-monospace">{{ $version->version_code }}</span>
            <span class="badge bg-{{ $statusColors[$version->approval_status] ?? 'secondary' }} align-middle ms-1">{{ $version->statusLabel() }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $version->name }} · {{ $version->currency }} ·
            {{ $version->effective_from->format('d M Y') }}@if($version->effective_to) → {{ $version->effective_to->format('d M Y') }}@else onwards @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('ot.settings.edit')
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cloneModal">
            <i class="bi bi-copy me-1"></i>Clone for Revision
        </button>
        @endcan
        @can('ot.settings.approve')
            @if(! in_array($version->approval_status, ['active', 'retired'], true))
            <form method="POST" action="{{ route('overtime.tariffs.activate', $version) }}"
                  data-confirm="Activate {{ $version->version_code }}? Any older open-ended active version will be closed the day before this one starts."
                  data-confirm-title="Activate Tariff Version" data-confirm-class="btn-success" data-confirm-label="Activate">
                @csrf @method('PATCH')
                <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Activate</button>
            </form>
            @elseif($version->approval_status === 'active')
            <form method="POST" action="{{ route('overtime.tariffs.retire', $version) }}"
                  data-confirm="Retire {{ $version->version_code }}? It will no longer price new receipts."
                  data-confirm-title="Retire Tariff Version" data-confirm-class="btn-warning" data-confirm-label="Retire">
                @csrf @method('PATCH')
                <button class="btn btn-outline-warning btn-sm"><i class="bi bi-archive me-1"></i>Retire</button>
            </form>
            @endif
        @endcan
        <a href="{{ route('overtime.tariffs.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
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

@if($locked)
<div class="alert alert-warning py-2 small d-flex align-items-center gap-2">
    <i class="bi bi-lock-fill"></i>
    <div><strong>This version is read-only.</strong> {{ $lockReason }}</div>
</div>
@endif

{{-- Version header --}}
<div class="card content-card mb-3">
    <div class="card-header py-2"><i class="bi bi-tag me-2 text-primary"></i>Version Details</div>
    <div class="card-body">
        <form method="POST" action="{{ route('overtime.tariffs.update', $version) }}">
            @csrf @method('PATCH')
            <fieldset @if(! $canEdit) disabled @endif>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Version Code <span class="text-danger">*</span></label>
                        <input type="text" name="version_code" class="form-control form-control-sm font-monospace"
                               value="{{ old('version_code', $version->version_code) }}" required maxlength="40">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $version->name) }}" required maxlength="150">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Currency <span class="text-danger">*</span></label>
                        <input type="text" name="currency" class="form-control form-control-sm text-uppercase"
                               value="{{ old('currency', $version->currency) }}" required maxlength="3" minlength="3">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Status <span class="text-danger">*</span></label>
                        <select name="approval_status" class="form-select form-select-sm">
                            @foreach(\App\Models\OtTariffVersion::APPROVAL_STATUSES as $k => $l)
                                <option value="{{ $k }}" {{ old('approval_status', $version->approval_status) === $k ? 'selected' : '' }}
                                        @if($k === 'active' && ! auth()->user()->can('ot.settings.approve')) disabled @endif>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Effective From <span class="text-danger">*</span></label>
                        <input type="date" name="effective_from" class="form-control form-control-sm"
                               value="{{ old('effective_from', $version->effective_from->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Effective To</label>
                        <input type="date" name="effective_to" class="form-control form-control-sm"
                               value="{{ old('effective_to', $version->effective_to?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Source Reference</label>
                        <input type="text" name="source_reference" class="form-control form-control-sm"
                               value="{{ old('source_reference', $version->source_reference) }}" maxlength="255">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="vActive" name="active" value="1"
                                   {{ old('active', $version->active) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="vActive">Active</label>
                        </div>
                    </div>
                </div>
                @if($canEdit)
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Save Version</button>
                </div>
                @endif
            </fieldset>
        </form>
    </div>
</div>

{{-- Rate rules --}}
<div class="card content-card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2 text-primary"></i>Rate Rules ({{ $version->rules->count() }})</span>
        @if($canEdit)
        <button type="button" class="btn btn-primary btn-sm js-rule-add" data-bs-toggle="modal" data-bs-target="#ruleModal">
            <i class="bi bi-plus-circle me-1"></i>Add Rate Rule
        </button>
        @endif
    </div>
    <div class="card-body p-0">
        @forelse($byCategory as $category => $rules)
        <div class="border-bottom">
            <div class="px-3 py-2 bg-light small fw-semibold">
                {{ \App\Models\OtTariffRule::DAY_CATEGORIES[$category] ?? $category }}
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Code</th>
                            <th>Period</th>
                            <th>Description</th>
                            <th>Window</th>
                            <th>Direction</th>
                            <th>Charge Basis</th>
                            <th class="text-end">Rate</th>
                            <th class="text-center">Pri.</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rules as $r)
                    <tr class="{{ $r->active ? '' : 'opacity-50' }}">
                        <td class="ps-3 font-monospace small">{{ $r->rule_code }}</td>
                        <td><span class="badge bg-secondary">{{ strtoupper($r->period_code) }}</span></td>
                        <td class="small">{{ $r->display_name }}</td>
                        <td class="small font-monospace text-nowrap">{{ $r->windowLabel() }}</td>
                        <td class="small text-muted">{{ \App\Models\OtTariffRule::MOVEMENT_TYPES[$r->movement_type] ?? $r->movement_type }}</td>
                        <td class="small text-muted">{{ \App\Models\OtTariffRule::CHARGE_BASES[$r->charge_basis] ?? $r->charge_basis }}</td>
                        <td class="text-end fw-semibold text-nowrap">{{ $r->currency }} {{ number_format((float) $r->rate_amount, 2) }}</td>
                        <td class="text-center small">{{ $r->priority }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $r->active ? 'success' : 'secondary' }}">{{ $r->active ? 'Active' : 'Off' }}</span>
                        </td>
                        <td class="text-end pe-3 text-nowrap">
                            @if($canEdit)
                            {{-- HTML-escaped payload: rule descriptions can contain quotes/apostrophes. --}}
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-1 js-rule-edit" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#ruleModal"
                                    data-rule="{{ json_encode($r->only([
                                        'id', 'rule_code', 'display_name', 'movement_type', 'day_category', 'period_code',
                                        'start_time', 'end_time', 'ends_next_day', 'rate_amount', 'charge_basis',
                                        'allow_receipt_extension', 'billing_mode_on_extension', 'priority', 'active',
                                    ])) }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('overtime.tariffs.rules.toggle', [$version, $r]) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-{{ $r->active ? 'warning' : 'success' }} btn-xs py-0 px-1"
                                        title="{{ $r->active ? 'Deactivate' : 'Activate' }}">
                                    <i class="bi bi-{{ $r->active ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('overtime.tariffs.rules.destroy', [$version, $r]) }}" class="d-inline"
                                  data-confirm="Delete rate rule {{ $r->rule_code }}?"
                                  data-confirm-title="Delete Rate Rule" data-confirm-class="btn-danger" data-confirm-label="Delete">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-xs py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                            @else
                            <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-4 small">
            <i class="bi bi-inbox me-1"></i>No rate rules yet.
            @if($canEdit) Add one to make this version usable. @endif
        </div>
        @endforelse
    </div>
</div>

@if($canEdit)
{{-- Rule add / edit modal --}}
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="ruleForm" action="{{ route('overtime.tariffs.rules.store', $version) }}">
            @csrf
            <input type="hidden" name="_method" id="ruleMethod" value="POST">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="ruleModalTitle">Add Rate Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Rule Code <span class="text-danger">*</span></label>
                            <input type="text" name="rule_code" id="rCode" class="form-control form-control-sm font-monospace"
                                   required maxlength="40" placeholder="OT-WD-A">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small mb-1">Description <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" id="rName" class="form-control form-control-sm"
                                   required maxlength="150" placeholder="Weekday 17:00–24:00">
                            <div class="form-text">Shown to the operator when they pick the OT period, and printed on the receipt.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Day Category <span class="text-danger">*</span></label>
                            <select name="day_category" id="rCategory" class="form-select form-select-sm">
                                @foreach(\App\Models\OtTariffRule::DAY_CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Period <span class="text-danger">*</span></label>
                            <select name="period_code" id="rPeriod" class="form-select form-select-sm">
                                @foreach(\App\Models\OtTariffRule::PERIODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Direction <span class="text-danger">*</span></label>
                            <select name="movement_type" id="rMovement" class="form-select form-select-sm">
                                @foreach(\App\Models\OtTariffRule::MOVEMENT_TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Window Start <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="rStart" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Window End <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="rEnd" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" name="ends_next_day" id="rNextDay" value="1">
                                <label class="form-check-label small" for="rNextDay">Window ends the next day</label>
                                <div class="form-text">
                                    Tick this when the window runs past midnight. A <strong>24:00</strong> end is entered as
                                    <strong>00:00</strong> with this ticked.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Rate ({{ $version->currency }}) <span class="text-danger">*</span></label>
                            <input type="number" name="rate_amount" id="rRate" class="form-control form-control-sm text-end"
                                   step="0.01" min="0" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Charge Basis <span class="text-danger">*</span></label>
                            <select name="charge_basis" id="rBasis" class="form-select form-select-sm">
                                @foreach(\App\Models\OtTariffRule::CHARGE_BASES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Priority <span class="text-danger">*</span></label>
                            <input type="number" name="priority" id="rPriority" class="form-control form-control-sm" min="0" max="999" required value="1">
                            <div class="form-text">Lower first.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">On Extension <span class="text-danger">*</span></label>
                            <select name="billing_mode_on_extension" id="rExtMode" class="form-select form-select-sm">
                                @foreach(\App\Models\OtTariffRule::EXTENSION_MODES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                            <div class="form-text">How a receipt extended past its window is re-charged.</div>
                        </div>
                        <div class="col-md-3 d-flex align-items-start">
                            <div class="form-check form-switch mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" role="switch" name="allow_receipt_extension" id="rAllowExt" value="1" checked>
                                <label class="form-check-label small" for="rAllowExt">Allow extension</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-start">
                            <div class="form-check form-switch mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" role="switch" name="active" id="rActive" value="1" checked>
                                <label class="form-check-label small" for="rActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Save Rule</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@can('ot.settings.edit')
{{-- Clone modal --}}
<div class="modal fade" id="cloneModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('overtime.tariffs.clone', $version) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Clone for Revision</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Copies all {{ $version->rules->count() }} rate rule(s) from
                        <span class="font-monospace">{{ $version->version_code }}</span> into a new <strong>draft</strong>
                        version. The original stays untouched, so receipts already issued keep their rates.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small mb-1">New Version Code <span class="text-danger">*</span></label>
                        <input type="text" name="version_code" class="form-control form-control-sm font-monospace" required maxlength="40"
                               value="{{ $version->version_code }}-REV">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required maxlength="150"
                               value="{{ $version->name }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Effective From <span class="text-danger">*</span></label>
                        <input type="date" name="effective_from" class="form-control form-control-sm" required
                               value="{{ now()->addDay()->toDateString() }}">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1">Across-the-board Rate Change (%)</label>
                        <input type="number" name="rate_change_pct" class="form-control form-control-sm text-end" step="0.01"
                               min="-100" max="1000" value="0">
                        <div class="form-text">
                            Applies to every copied rate — e.g. <strong>10</strong> raises all rates by 10%. Leave 0 to copy
                            them unchanged; you can still edit each rule afterwards.
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-copy me-1"></i>Create Draft</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@if($canEdit)
@push('scripts')
<script>
(function () {
    var storeUrl  = @json(route('overtime.tariffs.rules.store', $version));
    var updateUrl = @json(route('overtime.tariffs.rules.update', ['otTariffVersion' => $version->id, 'rule' => '__ID__']));

    function fill(r) {
        document.getElementById('ruleModalTitle').textContent = r ? 'Edit Rate Rule' : 'Add Rate Rule';
        document.getElementById('ruleForm').action  = r ? updateUrl.replace('__ID__', r.id) : storeUrl;
        document.getElementById('ruleMethod').value = r ? 'PATCH' : 'POST';

        document.getElementById('rCode').value     = r ? r.rule_code : '';
        document.getElementById('rName').value     = r ? r.display_name : '';
        document.getElementById('rCategory').value = r ? r.day_category : 'weekday';
        document.getElementById('rPeriod').value   = r ? r.period_code : 'a';
        document.getElementById('rMovement').value = r ? r.movement_type : 'gate_in';
        // TIME columns come back as HH:MM:SS; the time input needs HH:MM.
        document.getElementById('rStart').value    = r ? String(r.start_time).substring(0, 5) : '';
        document.getElementById('rEnd').value      = r ? String(r.end_time).substring(0, 5) : '';
        document.getElementById('rRate').value     = r ? r.rate_amount : '';
        document.getElementById('rBasis').value    = r ? r.charge_basis : 'per_bl_receipt';
        document.getElementById('rPriority').value = r ? r.priority : 1;
        document.getElementById('rExtMode').value  = r ? r.billing_mode_on_extension : 'full_new_charge';
        document.getElementById('rNextDay').checked  = r ? !!r.ends_next_day : false;
        document.getElementById('rAllowExt').checked = r ? !!r.allow_receipt_extension : true;
        document.getElementById('rActive').checked    = r ? !!r.active : true;
    }

    document.querySelectorAll('.js-rule-add').forEach(function (btn) {
        btn.addEventListener('click', function () { fill(null); });
    });

    document.querySelectorAll('.js-rule-edit').forEach(function (btn) {
        btn.addEventListener('click', function () { fill(JSON.parse(btn.dataset.rule)); });
    });
})();
</script>
@endpush
@endif

@endsection
