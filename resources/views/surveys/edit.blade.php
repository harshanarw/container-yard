@extends('layouts.app')

@section('title', 'Edit Survey — ' . $inquiry->inquiry_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}" class="text-decoration-none">Container Surveys</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surveys.show', $inquiry) }}" class="text-decoration-none">{{ $inquiry->inquiry_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection


@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Survey — {{ $inquiry->inquiry_no }}</h4>
        <p class="text-muted mb-0 small">
            Container <span class="font-monospace fw-semibold">{{ $inquiry->container_no }}</span>
            &nbsp;·&nbsp; {{ $inquiry->size }}ft {{ $inquiry->type_code }}
            &nbsp;·&nbsp; {{ $inquiry->customer?->name }}
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('surveys.show', $inquiry) }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-eye me-1"></i>View
        </a>
        <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the errors below:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div id="jsErrorBag" class="alert alert-danger alert-dismissible fade show mb-3 d-none" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<form method="POST" action="{{ route('surveys.update', $inquiry) }}"
      enctype="multipart/form-data" id="editSurveyForm">
    @csrf
    @method('PUT')

    <div class="row g-3">

        {{-- ════════ LEFT: main ════════ --}}
        <div class="col-lg-8">

            {{-- Container Info (read-only) --}}
            <div class="card content-card mb-3 border-primary">
                <div class="card-header bg-primary-subtle py-2 small fw-semibold">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Container Details (read-only)
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Container No.</div>
                            <div class="font-monospace fw-bold fs-6">{{ $inquiry->container_no }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Equipment Type</div>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                @if($inquiry->equipmentType)
                                    <span class="badge bg-primary fw-bold" style="font-size:.8rem;">
                                        {{ $inquiry->equipmentType->eqt_code }}
                                    </span>
                                @endif
                                <span class="badge bg-light border text-dark">{{ $inquiry->size }}'</span>
                                <span class="badge bg-info-subtle text-info">{{ $inquiry->type_code }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Customer</div>
                            <div class="fw-semibold">{{ $inquiry->customer?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Survey Type</div>
                            <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $inquiry->inquiry_type)) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Gate-In Reference</div>
                            <div class="font-monospace">{{ $inquiry->gate_in_ref ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Survey No.</div>
                            <div class="fw-semibold">{{ $inquiry->inquiry_no }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Editable Survey Fields --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-sliders me-2 text-primary"></i>Inspection Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned Inspector</label>
                            <select name="inspector_id" class="form-select">
                                <option value="">— Select Inspector —</option>
                                @foreach($inspectors as $ins)
                                <option value="{{ $ins->id }}"
                                    {{ old('inspector_id', $inquiry->inspector_id) == $ins->id ? 'selected' : '' }}>
                                    {{ $ins->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control"
                                   value="{{ old('inspection_date', $inquiry->inspection_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal"   {{ old('priority', $inquiry->priority) === 'normal'   ? 'selected' : '' }}>Normal</option>
                                <option value="urgent"   {{ old('priority', $inquiry->priority) === 'urgent'   ? 'selected' : '' }}>Urgent</option>
                                <option value="critical" {{ old('priority', $inquiry->priority) === 'critical' ? 'selected' : '' }}>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach(['open'=>'Open','in_progress'=>'In Progress','estimate_sent'=>'Estimate Sent','approved'=>'Approved','closed'=>'Closed'] as $val => $label)
                                <option value="{{ $val }}" {{ old('status', $inquiry->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimated Repair Cost (LKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">LKR</span>
                                <input type="number" name="estimated_repair_cost" class="form-control"
                                       step="0.01" min="0" placeholder="0.00"
                                       value="{{ old('estimated_repair_cost', $inquiry->estimated_repair_cost) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Damage Assessment --}}
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="small fw-semibold"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Damage Assessment</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDamageRow">
                        <i class="bi bi-plus-circle me-1"></i>Add Row
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="damageTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="min-width:120px">Location</th>
                                    <th style="min-width:110px">Component</th>
                                    <th style="min-width:110px">Damage</th>
                                    <th style="min-width:110px">Repair</th>
                                    <th style="min-width:80px">Resp.</th>
                                    <th style="min-width:90px">Severity</th>
                                    <th style="min-width:130px">Dim. L / W (cm)</th>
                                    <th style="min-width:60px">Qty</th>
                                    <th>Description</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="damageRows">
                                @forelse($inquiry->damages as $di => $dmg)
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[{{ $di }}][location_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrLocationCodes as $c)
                                            <option value="{{ $c->id }}" {{ $dmg->location_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][component_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}" {{ $dmg->component_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][damage_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrDamageCodes as $c)
                                            <option value="{{ $c->id }}" {{ $dmg->damage_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][repair_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrRepairCodes as $c)
                                            <option value="{{ $c->id }}" {{ $dmg->repair_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][responsibility_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrResponsibilityCodes as $c)
                                            <option value="{{ $c->id }}" {{ $dmg->responsibility_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][severity]" class="form-select form-select-sm">
                                            <option value="minor"    {{ $dmg->severity === 'minor'    ? 'selected' : '' }}>Minor</option>
                                            <option value="moderate" {{ $dmg->severity === 'moderate' ? 'selected' : '' }}>Moderate</option>
                                            <option value="severe"   {{ $dmg->severity === 'severe'   ? 'selected' : '' }}>Severe</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <input type="number" name="damages[{{ $di }}][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px" value="{{ $dmg->dim_length }}">
                                            <input type="number" name="damages[{{ $di }}][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px" value="{{ $dmg->dim_width }}">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="damages[{{ $di }}][quantity]" class="form-control form-control-sm" value="{{ $dmg->quantity ?? 1 }}" step="0.5" min="0.5" style="width:58px">
                                    </td>
                                    <td>
                                        <input type="text" name="damages[{{ $di }}][description]" class="form-control form-control-sm" placeholder="Details…" value="{{ $dmg->description }}">
                                    </td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[0][location_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrLocationCodes as $c)
                                            <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][component_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrDamageCodes as $c)
                                            <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][repair_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrRepairCodes as $c)
                                            <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][responsibility_code_id]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach($mrResponsibilityCodes as $c)
                                            <option value="{{ $c->id }}">{{ $c->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][severity]" class="form-select form-select-sm">
                                            <option value="minor">Minor</option>
                                            <option value="moderate">Moderate</option>
                                            <option value="severe">Severe</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <input type="number" name="damages[0][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
                                            <input type="number" name="damages[0][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
                                        </div>
                                    </td>
                                    <td><input type="number" name="damages[0][quantity]" class="form-control form-control-sm" value="1" step="0.5" min="0.5" style="width:58px"></td>
                                    <td><input type="text" name="damages[0][description]" class="form-control form-control-sm" placeholder="Details…"></td>
                                    <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Inspector's Notes --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Inspector's Findings
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Overall Condition</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor','condemned'=>'Condemned'] as $val => $lbl)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="overall_condition"
                                           value="{{ $val }}" id="cond_{{ $val }}"
                                           {{ old('overall_condition', $inquiry->overall_condition) === $val ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="cond_{{ $val }}">{{ $lbl }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Findings</label>
                            <textarea name="findings" class="form-control" rows="4"
                                      placeholder="Describe findings…">{{ old('findings', $inquiry->findings) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Recommended Action</label>
                            <select name="recommended_action" class="form-select">
                                @foreach(['repair'=>'Repair Required','monitor'=>'Monitor Only','scrap'=>'Scrap/Condemn','no_action'=>'No Action Required'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('recommended_action', $inquiry->recommended_action) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Photo Evidence --}}
            <x-document-manager
                model-type="App\Models\Inquiry"
                :model-id="$inquiry->id"
                :folder="'surveys/' . $inquiry->id"
                title="Photo Evidence"
                accept="image/*"
                :max-files="20"
            />

        </div>{{-- /col-lg-8 --}}

        {{-- ════════ RIGHT: sidebar ════════ --}}
        <div class="col-lg-4">

            {{-- Checklist --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-check2-square me-2 text-primary"></i>Inspection Checklist
                </div>
                <div class="card-body">
                    @php $checklistMap = $inquiry->checklists->keyBy('checklist_item'); @endphp
                    @forelse($checklistItems as $item)
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox"
                               name="checklist[]" value="{{ $item->code }}"
                               id="chk_{{ $item->code }}"
                               {{ optional($checklistMap->get($item->code))->is_checked ? 'checked' : '' }}>
                        <label class="form-check-label small" for="chk_{{ $item->code }}"
                               @if($item->description) title="{{ $item->description }}" @endif>
                            {{ $item->label }}
                        </label>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No checklist items configured. Add items via Masters → Inspection Checklist.</p>
                    @endforelse
                </div>
            </div>

            {{-- Save actions --}}
            <div class="card content-card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                    <a href="{{ route('surveys.show', $inquiry) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>

        </div>{{-- /col-lg-4 --}}

    </div>
</form>

@endsection

@push('scripts')
<script>
    // ── Damage rows ───────────────────────────────────────────
    let damageRowIndex = {{ $inquiry->damages->count() ?: 1 }};

    const mrLocOpts  = `<option value="">—</option>` + @json($mrLocationCodes->map(fn($c) => ['id'=>$c->id,'label'=>$c->code.' '.$c->name]))->reduce((a,c) => a+`<option value="${c.id}">${c.label}</option>`,'');
    const mrCmpOpts  = `<option value="">—</option>` + @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'label'=>$c->code.' '.$c->name]))->reduce((a,c) => a+`<option value="${c.id}">${c.label}</option>`,'');
    const mrDmgOpts  = `<option value="">—</option>` + @json($mrDamageCodes->map(fn($c) => ['id'=>$c->id,'label'=>$c->code.' '.$c->name]))->reduce((a,c) => a+`<option value="${c.id}">${c.label}</option>`,'');
    const mrRepOpts  = `<option value="">—</option>` + @json($mrRepairCodes->map(fn($c) => ['id'=>$c->id,'label'=>$c->code.' '.$c->name]))->reduce((a,c) => a+`<option value="${c.id}">${c.label}</option>`,'');
    const mrRespOpts = `<option value="">—</option>` + @json($mrResponsibilityCodes->map(fn($c) => ['id'=>$c->id,'label'=>$c->code]))->reduce((a,c) => a+`<option value="${c.id}">${c.label}</option>`,'');

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const i = damageRowIndex++;
        const row = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3"><select name="damages[${i}][location_code_id]" class="form-select form-select-sm">${mrLocOpts}</select></td>
            <td><select name="damages[${i}][component_code_id]" class="form-select form-select-sm">${mrCmpOpts}</select></td>
            <td><select name="damages[${i}][damage_code_id]" class="form-select form-select-sm">${mrDmgOpts}</select></td>
            <td><select name="damages[${i}][repair_code_id]" class="form-select form-select-sm">${mrRepOpts}</select></td>
            <td><select name="damages[${i}][responsibility_code_id]" class="form-select form-select-sm">${mrRespOpts}</select></td>
            <td>
                <select name="damages[${i}][severity]" class="form-select form-select-sm">
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </td>
            <td>
                <div class="d-flex gap-1">
                    <input type="number" name="damages[${i}][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
                    <input type="number" name="damages[${i}][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
                </div>
            </td>
            <td><input type="number" name="damages[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.5" min="0.5" style="width:58px"></td>
            <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…"></td>
            <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('damageRows').appendChild(row);
    });

    document.getElementById('damageRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.damage-row');
            if (rows.length > 1) e.target.closest('.damage-row').remove();
        }
    });

    // Submit via fetch — handles JSON validation error responses
    const _form      = document.getElementById('editSurveyForm');
    const _submitBtn = _form.querySelector('[type="submit"]');
    const _origHtml  = _submitBtn ? _submitBtn.innerHTML : '';
    const _errBag    = document.getElementById('jsErrorBag');
    _form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (_errBag) _errBag.classList.add('d-none');
        if (_submitBtn) { _submitBtn.disabled = true; _submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving…'; }
        const fd = new FormData(_form);
        fetch(_form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (response.status === 422 && data.errors) {
                        var msgs = Object.values(data.errors).flat();
                        if (_errBag) {
                            _errBag.innerHTML = '<strong>Please fix the following errors:</strong><ul class="mb-0 mt-1 ps-3">' +
                                msgs.map(function (m) { return '<li>' + m + '</li>'; }).join('') + '</ul>';
                            _errBag.classList.remove('d-none');
                            _errBag.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                        if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; }
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; }
                    }
                });
            })
            .catch(function () { if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; } });
    });
</script>
@endpush
