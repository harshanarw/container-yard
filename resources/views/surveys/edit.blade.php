@extends('layouts.app')

@section('title', 'Edit Survey — ' . $inquiry->inquiry_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}" class="text-decoration-none">Container Surveys</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surveys.show', $inquiry) }}" class="text-decoration-none">{{ $inquiry->inquiry_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@push('styles')
<style>
    .dim-no-spin::-webkit-inner-spin-button,
    .dim-no-spin::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    .dim-no-spin { -moz-appearance: textfield; appearance: textfield; }
    .dim-unit-lbl { font-size: .72rem; color: #6c757d; }
    .dim-axis-lbl { font-size: .72rem; font-weight: 700; color: #0d6efd; min-width: 10px; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Survey — {{ $inquiry->inquiry_no }}</h4>
        <p class="text-muted mb-0 small">
            Container <span class="font-monospace fw-semibold">{{ $inquiry->container_no }}</span>
            &nbsp;·&nbsp; {{ $inquiry->size }}ft {{ $inquiry->type_code }}
            &nbsp;·&nbsp; {{ $inquiry->customer?->name }}
            &nbsp;·&nbsp; @include('partials.job-badge', ['job' => $inquiry->yardJob, 'mode' => 'inline'])
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
                    @include('partials.job-badge', ['job' => $inquiry->yardJob, 'mode' => 'card'])
                    <hr class="my-3">
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
                            <select name="inspector_id" class="form-select select2">
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
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" id="pullFromRulesBtn">
                            <i class="bi bi-journal-check me-1"></i>Pull From Rules
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addDamageRow">
                            <i class="bi bi-plus-circle me-1"></i>Add Row
                        </button>
                    </div>
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
                                    <th style="min-width:130px">Dim. L / W ({{ $dimUom === 'ft_in' ? 'ft/in' : 'cm' }})</th>
                                    <th style="min-width:60px">Qty</th>
                                    <th>Description</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="damageRows">
                                @forelse($inquiry->damages as $di => $dmg)
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[{{ $di }}][location_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrLocationCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ $dmg->location_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][component_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ $dmg->component_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][damage_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrDamageCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ $dmg->damage_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][repair_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrRepairCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ $dmg->repair_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][responsibility_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrResponsibilityCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->code }}" {{ $dmg->responsibility_code_id == $c->id ? 'selected' : '' }}>{{ $c->code }}</option>
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
                                        @php
                                            $storedUom  = $dmg->dim_uom ?? 'cm';
                                            $storedL    = (float)($dmg->dim_length ?? 0);
                                            $storedW    = (float)($dmg->dim_width  ?? 0);
                                            // Only back-populate ft/in values when they were saved as ft_in;
                                            // old records with dim_uom=null/'cm' show blank inputs in the new format.
                                            $popInches  = $storedUom === 'ft_in';
                                        @endphp
                                        @if($dimUom === 'ft_in')
                                        <div class="dim-cell d-flex flex-column gap-1" style="min-width:145px;">
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-l" placeholder="0" min="0" step="1" style="width:42px" title="Length feet"
                                                       value="{{ $popInches && $storedL > 0 ? (int)floor($storedL / 12) : '' }}">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Length inches"
                                                       value="{{ $popInches && $storedL > 0 ? round(fmod($storedL, 12), 2) : '' }}">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">L</span>
                                                <input type="hidden" name="damages[{{ $di }}][dim_length]" class="dim-hidden-l" value="{{ $popInches ? ($storedL ?: '') : '' }}">
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-w" placeholder="0" min="0" step="1" style="width:42px" title="Width feet"
                                                       value="{{ $popInches && $storedW > 0 ? (int)floor($storedW / 12) : '' }}">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Width inches"
                                                       value="{{ $popInches && $storedW > 0 ? round(fmod($storedW, 12), 2) : '' }}">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">W</span>
                                                <input type="hidden" name="damages[{{ $di }}][dim_width]" class="dim-hidden-w" value="{{ $popInches ? ($storedW ?: '') : '' }}">
                                            </div>
                                        </div>
                                        @else
                                        <div class="d-flex gap-1">
                                            <input type="number" name="damages[{{ $di }}][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px" value="{{ $storedL ?: '' }}">
                                            <input type="number" name="damages[{{ $di }}][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px" value="{{ $storedW ?: '' }}">
                                        </div>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number" name="damages[{{ $di }}][quantity]" class="form-control form-control-sm" value="{{ $dmg->quantity ?? 1 }}" step="0.01" min="0.01" style="width:64px">
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
                                        <select name="damages[0][location_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrLocationCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][component_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrDamageCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][repair_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrRepairCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][responsibility_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrResponsibilityCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->code }}">{{ $c->code }}</option>
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
                                        @if($dimUom === 'ft_in')
                                        <div class="dim-cell d-flex flex-column gap-1" style="min-width:145px;">
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-l" placeholder="0" min="0" step="1" style="width:42px">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">L</span>
                                                <input type="hidden" name="damages[0][dim_length]" class="dim-hidden-l">
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-w" placeholder="0" min="0" step="1" style="width:42px">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">W</span>
                                                <input type="hidden" name="damages[0][dim_width]" class="dim-hidden-w">
                                            </div>
                                        </div>
                                        @else
                                        <div class="d-flex gap-1">
                                            <input type="number" name="damages[0][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
                                            <input type="number" name="damages[0][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
                                        </div>
                                        @endif
                                    </td>
                                    <td><input type="number" name="damages[0][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px"></td>
                                    <td><input type="text" name="damages[0][description]" class="form-control form-control-sm" placeholder="Details…"></td>
                                    <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Washing / Cleaning --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-droplet me-2 text-info"></i>Washing / Cleaning
                </div>
                <div class="card-body">
                    @php $washOn = old('wash_required', $inquiry->wash_required); @endphp
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" value="1"
                               id="washRequired" name="wash_required" {{ $washOn ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="washRequired">Washing required</label>
                        <div class="form-text">Flags this box for cleaning. Pulls into the estimate as washing line(s) — even if there are no repair damages.</div>
                    </div>
                    <div class="row g-3" id="washFields" style="{{ $washOn ? '' : 'display:none' }}">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Scope</label>
                            <select name="wash_scope" class="form-select">
                                <option value="internal" {{ old('wash_scope', $inquiry->wash_scope) === 'internal' ? 'selected' : '' }}>Internal only</option>
                                <option value="external" {{ old('wash_scope', $inquiry->wash_scope) === 'external' ? 'selected' : '' }}>External only</option>
                                <option value="both"     {{ old('wash_scope', $inquiry->wash_scope ?: 'both') === 'both' ? 'selected' : '' }}>Both (internal + external)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Wash Type</label>
                            <select name="wash_type" class="form-select">
                                @foreach(\App\Models\WashingTariff::TYPES as $k => $label)
                                <option value="{{ $k }}" {{ old('wash_type', $inquiry->wash_type ?: 'standard') === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
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

{{-- Pull From Rules Modal --}}
<div class="modal fade" id="pullFromRulesModal" tabindex="-1" aria-labelledby="pullFromRulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="pullFromRulesModalLabel">
                    <i class="bi bi-journal-check me-2 text-primary"></i>Pull From Assessment Rules
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" id="ruleSearchQ" class="form-control form-control-sm" placeholder="Search rule name…">
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterLoc" class="form-select form-select-sm select2-modal s2-code" data-s2-sel="name">
                                <option value="">All Locations</option>
                                @foreach($mrLocationCodes as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterCmp" class="form-select form-select-sm select2-modal s2-code" data-s2-sel="name">
                                <option value="">All Components</option>
                                @foreach($mrComponentCodes as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterDmg" class="form-select form-select-sm select2-modal s2-code" data-s2-sel="name">
                                <option value="">All Damage Types</option>
                                @foreach($mrDamageCodes as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="clearRuleFilters">
                                <i class="bi bi-x-circle me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th class="ps-3" style="width:36px">
                                    <input type="checkbox" class="form-check-input" id="selectAllRules" title="Select all">
                                </th>
                                <th>Rule Name</th>
                                <th style="width:100px">Location</th>
                                <th style="width:130px">Component</th>
                                <th style="width:130px">Damage</th>
                                <th style="width:130px">Repair</th>
                                <th style="width:85px">Severity</th>
                            </tr>
                        </thead>
                        <tbody id="ruleModalBody">
                            <tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="me-auto small text-muted" id="ruleSelectedCount">0 selected</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="addSelectedRulesBtn" disabled>
                    <i class="bi bi-plus-circle me-1"></i>Add Selected Lines
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const DIM_UOM = '{{ $dimUom }}';

    // Washing: reveal scope/type only when washing is required.
    (function () {
        const chk = document.getElementById('washRequired');
        const box = document.getElementById('washFields');
        if (chk && box) {
            chk.addEventListener('change', () => { box.style.display = chk.checked ? '' : 'none'; });
        }
    })();

    function syncDimHidden(cell) {
        const ftL  = parseFloat(cell.querySelector('.dim-ft-l')?.value) || 0;
        const inL  = parseFloat(cell.querySelector('.dim-in-l')?.value) || 0;
        const ftW  = parseFloat(cell.querySelector('.dim-ft-w')?.value) || 0;
        const inW  = parseFloat(cell.querySelector('.dim-in-w')?.value) || 0;
        const hidL = cell.querySelector('.dim-hidden-l');
        const hidW = cell.querySelector('.dim-hidden-w');
        if (hidL) hidL.value = ftL * 12 + inL || '';
        if (hidW) hidW.value = ftW * 12 + inW || '';
    }

    function initDimInputs(row) {
        row.querySelectorAll('.dim-cell').forEach(cell => {
            cell.querySelectorAll('input[type=number]').forEach(inp => {
                inp.addEventListener('input', () => syncDimHidden(cell));
            });
        });
    }

    function buildDimCell(i) {
        if (DIM_UOM === 'ft_in') {
            return `<div class="dim-cell d-flex flex-column gap-1" style="min-width:145px;">
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-l" placeholder="0" min="0" step="1" style="width:42px" title="Length feet">
                    <span class="dim-unit-lbl">ft</span>
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Length inches">
                    <span class="dim-unit-lbl">in</span>
                    <span class="dim-axis-lbl">L</span>
                    <input type="hidden" name="damages[${i}][dim_length]" class="dim-hidden-l">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-w" placeholder="0" min="0" step="1" style="width:42px" title="Width feet">
                    <span class="dim-unit-lbl">ft</span>
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Width inches">
                    <span class="dim-unit-lbl">in</span>
                    <span class="dim-axis-lbl">W</span>
                    <input type="hidden" name="damages[${i}][dim_width]" class="dim-hidden-w">
                </div>
            </div>`;
        }
        return `<div class="d-flex gap-1">
            <input type="number" name="damages[${i}][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
            <input type="number" name="damages[${i}][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
        </div>`;
    }

    // Wire up ft/in inputs on existing rows rendered from PHP
    document.querySelectorAll('#damageRows .damage-row').forEach(row => initDimInputs(row));

    // ── Damage rows ───────────────────────────────────────────
    let damageRowIndex = {{ $inquiry->damages->count() ?: 1 }};

    const mrLocOpts  = @json($mrLocationCodes->map(fn($c)  => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrCmpOpts  = @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrDmgOpts  = @json($mrDamageCodes->map(fn($c)    => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrRepOpts  = @json($mrRepairCodes->map(fn($c)    => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrResOpts  = @json($mrResponsibilityCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));

    function buildSel(name, opts, codeOnly) {
        let html = `<select name="${name}" class="form-select form-select-sm s2 s2-code"><option value="">—</option>`;
        opts.forEach(o => { html += `<option value="${o.id}" data-code="${o.code}" data-name="${codeOnly ? o.code : o.name}">${o.code}${codeOnly ? '' : ' '+o.name}</option>`; });
        return html + '</select>';
    }

    function initRowSelects(tr) {
        $(tr).find('select.s2').each(function() { window.initS2Code($(this), { width: '100%' }); });
    }

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const i = damageRowIndex++;
        const row = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3">${buildSel('damages['+i+'][location_code_id]', mrLocOpts)}</td>
            <td>${buildSel('damages['+i+'][component_code_id]', mrCmpOpts)}</td>
            <td>${buildSel('damages['+i+'][damage_code_id]', mrDmgOpts)}</td>
            <td>${buildSel('damages['+i+'][repair_code_id]', mrRepOpts)}</td>
            <td>${buildSel('damages['+i+'][responsibility_code_id]', mrResOpts, true)}</td>
            <td>
                <select name="damages[${i}][severity]" class="form-select form-select-sm">
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </td>
            <td>${buildDimCell(i)}</td>
            <td><input type="number" name="damages[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px"></td>
            <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…"></td>
            <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('damageRows').appendChild(row);
        initRowSelects(row);
        initDimInputs(row);
    });

    document.getElementById('damageRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.damage-row');
            if (rows.length > 1) e.target.closest('.damage-row').remove();
        }
    });

    // ── Pull From Rules ───────────────────────────────────────
    (function () {
        const modal      = new bootstrap.Modal(document.getElementById('pullFromRulesModal'));
        const bodyEl     = document.getElementById('ruleModalBody');
        const countEl    = document.getElementById('ruleSelectedCount');
        const addBtn     = document.getElementById('addSelectedRulesBtn');
        const selectAll  = document.getElementById('selectAllRules');
        let debounceT    = null;

        document.getElementById('pullFromRulesBtn').addEventListener('click', function () {
            modal.show();
            fetchRules();
        });

        // Init the code-chip Select2 filters once the modal is fully shown
        // (initialising while hidden mis-sizes; guard avoids re-init on reopen).
        $('#pullFromRulesModal').on('shown.bs.modal', function () {
            $(this).find('.select2-modal').each(function () {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    window.initS2Code($(this), { width: '100%' });
                }
            });
        });

        function fetchRules() {
            const params = new URLSearchParams({
                q:                  document.getElementById('ruleSearchQ').value,
                location_code_id:   document.getElementById('ruleFilterLoc').value,
                component_code_id:  document.getElementById('ruleFilterCmp').value,
                damage_code_id:     document.getElementById('ruleFilterDmg').value,
            });
            bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</td></tr>';
            fetch('/masters/damage-assessment-rules/search?' + params, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => renderRules(data.rules || []))
                .catch(() => { bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Failed to load rules.</td></tr>'; });
        }

        function renderRules(rules) {
            if (!rules.length) {
                bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-journal-x me-1"></i>No matching rules found.</td></tr>';
                updateCount(); return;
            }
            const sevClass = s => s === 'severe' ? 'bg-danger' : s === 'moderate' ? 'bg-warning text-dark' : 'bg-light text-dark border';
            bodyEl.innerHTML = rules.map(r => `
                <tr class="rule-row">
                    <td class="ps-3"><input type="checkbox" class="form-check-input rule-chk" value="${r.id}" data-rule='${JSON.stringify(r).replace(/'/g, "&#39;")}'></td>
                    <td class="small fw-semibold">${escHtml(r.name)}</td>
                    <td class="small">${r.location_code ? `<span class="badge bg-secondary-subtle text-secondary border font-monospace">${escHtml(r.location_code)}</span>` : '<span class="text-muted fst-italic">Any</span>'}</td>
                    <td class="small"><span class="badge bg-primary-subtle text-primary border font-monospace">${escHtml(r.component_code)}</span> <span class="text-muted">${escHtml(r.component_name)}</span></td>
                    <td class="small"><span class="badge bg-warning-subtle text-warning-emphasis border font-monospace">${escHtml(r.damage_code)}</span> <span class="text-muted">${escHtml(r.damage_name)}</span></td>
                    <td class="small"><span class="badge bg-info-subtle text-info-emphasis border font-monospace">${escHtml(r.repair_code)}</span> <span class="text-muted">${escHtml(r.repair_name)}</span></td>
                    <td class="small">${r.default_severity ? `<span class="badge ${sevClass(r.default_severity)}">${r.default_severity.charAt(0).toUpperCase() + r.default_severity.slice(1)}</span>` : '<span class="text-muted">—</span>'}</td>
                </tr>`).join('');
            updateCount();
        }

        function escHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

        function updateCount() {
            const n = document.querySelectorAll('.rule-chk:checked').length;
            countEl.textContent = n > 0 ? n + ' selected' : '0 selected';
            addBtn.disabled = n === 0;
            addBtn.innerHTML = n > 0 ? `<i class="bi bi-plus-circle me-1"></i>Add ${n} Selected Line${n !== 1 ? 's' : ''}` : '<i class="bi bi-plus-circle me-1"></i>Add Selected Lines';
        }

        ['ruleSearchQ'].forEach(id => {
            document.getElementById(id).addEventListener('input', function () {
                clearTimeout(debounceT);
                debounceT = setTimeout(fetchRules, 280);
            });
        });
        // Rule filters are select2 — bind via jQuery so select2 changes fire.
        ['ruleFilterLoc', 'ruleFilterCmp', 'ruleFilterDmg'].forEach(id => {
            $('#' + id).on('change', fetchRules);
        });

        // Clear filters (clear the select2 widgets via change.select2 so the
        // display resets without each firing its own fetch; fetchRules runs once).
        document.getElementById('clearRuleFilters').addEventListener('click', function () {
            document.getElementById('ruleSearchQ').value = '';
            $('#ruleFilterLoc, #ruleFilterCmp, #ruleFilterDmg').val('').trigger('change.select2');
            fetchRules();
        });

        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.rule-chk').forEach(c => c.checked = this.checked);
            updateCount();
        });

        bodyEl.addEventListener('change', function (e) {
            if (!e.target.classList.contains('rule-chk')) return;
            updateCount();
            const all = document.querySelectorAll('.rule-chk');
            const chk = document.querySelectorAll('.rule-chk:checked');
            selectAll.indeterminate = chk.length > 0 && chk.length < all.length;
            selectAll.checked = chk.length === all.length && all.length > 0;
        });

        addBtn.addEventListener('click', function () {
            const rules = Array.from(document.querySelectorAll('.rule-chk:checked'))
                               .map(chk => JSON.parse(chk.dataset.rule.replace(/&#39;/g, "'")));
            if (!rules.length) return;

            // If the first damage row is completely blank, fill it instead of appending
            const firstRow = document.querySelector('#damageRows .damage-row');
            let startIdx = 0;
            if (firstRow && isRowBlank(firstRow)) {
                fillExistingRow(firstRow, rules[0]);
                startIdx = 1;
            }
            for (let i = startIdx; i < rules.length; i++) {
                addRuleAsRow(rules[i]);
            }

            modal.hide();
            selectAll.checked = false;
            selectAll.indeterminate = false;
            document.querySelectorAll('.rule-chk').forEach(c => c.checked = false);
            updateCount();
        });

        function isRowBlank(row) {
            return ['component_code_id','damage_code_id','repair_code_id'].every(f => {
                const s = row.querySelector(`select[name*="[${f}]"]`);
                return !s || !s.value;
            });
        }

        function fillExistingRow(row, r) {
            const sevSel = row.querySelector('select[name*="[severity]"]');
            if (sevSel && r.default_severity) sevSel.value = r.default_severity;
            const descInput = row.querySelector('input[name*="[description]"]');
            if (descInput) descInput.value = r.description || '';
            if (r.location_code_id) { const s = row.querySelector('select[name*="[location_code_id]"]'); if (s) { s.value = r.location_code_id; $(s).trigger('change'); } }
            const cs = row.querySelector('select[name*="[component_code_id]"]'); if (cs) { cs.value = r.component_code_id; $(cs).trigger('change'); }
            const ds = row.querySelector('select[name*="[damage_code_id]"]');    if (ds) { ds.value = r.damage_code_id;    $(ds).trigger('change'); }
            const rs = row.querySelector('select[name*="[repair_code_id]"]');    if (rs) { rs.value = r.repair_code_id;    $(rs).trigger('change'); }
            row.style.transition = 'background-color 0.6s';
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 1400);
        }

        function addRuleAsRow(r) {
            const i   = damageRowIndex++;
            const row = document.createElement('tr');
            row.className = 'damage-row';
            const sevOpts = ['minor','moderate','severe'].map(s =>
                `<option value="${s}"${r.default_severity === s ? ' selected' : ''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`
            ).join('');
            row.innerHTML = `
                <td class="ps-3">${buildSel('damages['+i+'][location_code_id]', mrLocOpts)}</td>
                <td>${buildSel('damages['+i+'][component_code_id]', mrCmpOpts)}</td>
                <td>${buildSel('damages['+i+'][damage_code_id]', mrDmgOpts)}</td>
                <td>${buildSel('damages['+i+'][repair_code_id]', mrRepOpts)}</td>
                <td>${buildSel('damages['+i+'][responsibility_code_id]', mrResOpts, true)}</td>
                <td><select name="damages[${i}][severity]" class="form-select form-select-sm">${sevOpts}</select></td>
                <td>${buildDimCell(i)}</td>
                <td><input type="number" name="damages[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px"></td>
                <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…" value="${escHtml(r.description || '')}"></td>
                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>`;
            document.getElementById('damageRows').appendChild(row);
            initRowSelects(row);
            initDimInputs(row);
            if (r.location_code_id) { const s = row.querySelector(`[name="damages[${i}][location_code_id]"]`); s.value = r.location_code_id; $(s).trigger('change'); }
            const cs = row.querySelector(`[name="damages[${i}][component_code_id]"]`); cs.value = r.component_code_id; $(cs).trigger('change');
            const ds = row.querySelector(`[name="damages[${i}][damage_code_id]"]`);    ds.value = r.damage_code_id;    $(ds).trigger('change');
            const rs = row.querySelector(`[name="damages[${i}][repair_code_id]"]`);    rs.value = r.repair_code_id;    $(rs).trigger('change');
            row.style.transition = 'background-color 0.6s';
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 1400);
        }
    })();

    // Initialize Select2 on all Blade-rendered damage rows
    $(function () {
        document.querySelectorAll('#damageRows .damage-row').forEach(function (row) {
            initRowSelects(row);
        });
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
