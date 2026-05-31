@extends('layouts.app')

@section('title', 'M&R Tariff — ' . $mrTariff->name)

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.mr-tariff.index') }}">M&amp;R Tariff</a></li>
    <li class="breadcrumb-item active">{{ $mrTariff->name }}</li>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2 small">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

{{-- ── Header card ── --}}
<div class="card content-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span>
            <i class="bi bi-tools me-2 text-primary"></i>
            <strong>{{ $mrTariff->name }}</strong>
            @if(!$mrTariff->is_active)
                <span class="badge bg-secondary ms-2">Inactive</span>
            @endif
        </span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editHeaderModal">
                <i class="bi bi-pencil me-1"></i>Edit Header
            </button>
            <a href="{{ route('masters.mr-tariff.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-md-3">
                <div class="text-muted">Owner / Customer</div>
                <div class="fw-semibold">{{ $mrTariff->customer->name ?? '— Default / All —' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted">Validity</div>
                <div class="fw-semibold">{{ $mrTariff->validity_label }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Currency</div>
                <div class="fw-semibold font-monospace">{{ $mrTariff->currency }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Applicable Sizes</div>
                <div class="fw-semibold">
                    @if($mrTariff->applicable_sizes)
                        {{ implode("', ", $mrTariff->applicable_sizes) }}'
                    @else
                        All
                    @endif
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Rules</div>
                <div class="fw-semibold">{{ $mrTariff->rules->count() }}</div>
            </div>
        </div>
        @if($mrTariff->notes)
        <div class="mt-2 text-muted small border-top pt-2">{{ $mrTariff->notes }}</div>
        @endif
    </div>
</div>

{{-- ── Rate rules table ── --}}
<div class="card content-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-list-ul me-2 text-primary"></i>Rate Rules</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
            <i class="bi bi-plus-circle me-1"></i>Add Rule
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:40px;">#</th>
                        <th>Component</th>
                        <th>Damage</th>
                        <th>Repair</th>
                        <th>Material</th>
                        <th class="text-end">Hrs</th>
                        <th class="text-end">Labour<br><span class="text-muted fw-normal">/hr</span></th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Mat.<br><span class="text-muted fw-normal">/unit</span></th>
                        <th class="text-end">Ancil.</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Min</th>
                        <th style="width:90px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($mrTariff->rules as $rule)
                <tr>
                    <td class="ps-3 text-muted">{{ $loop->iteration }}</td>
                    <td>
                        @if($rule->componentCode)
                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace">{{ $rule->componentCode->code }}</span>
                            <span class="ms-1">{{ $rule->componentCode->name }}</span>
                        @else
                            <span class="text-muted">Any</span>
                        @endif
                    </td>
                    <td>
                        @if($rule->damageCode)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace">{{ $rule->damageCode->code }}</span>
                            <span class="ms-1">{{ $rule->damageCode->name }}</span>
                        @else
                            <span class="text-muted">Any</span>
                        @endif
                    </td>
                    <td>
                        @if($rule->repairCode)
                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">{{ $rule->repairCode->code }}</span>
                            <span class="ms-1">{{ $rule->repairCode->name }}</span>
                        @else
                            <span class="text-muted">Any</span>
                        @endif
                    </td>
                    <td>
                        @if($rule->materialCode)
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle font-monospace">{{ $rule->materialCode->code }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end font-monospace">{{ number_format($rule->std_labor_hours, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($rule->labor_rate, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($rule->material_qty, 3) }}</td>
                    <td class="text-end font-monospace">{{ number_format($rule->material_rate, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($rule->ancillary, 2) }}</td>
                    <td class="text-end fw-semibold font-monospace">{{ number_format($rule->computeAmount(), 2) }}</td>
                    <td class="text-end font-monospace text-muted">{{ number_format($rule->min_charge, 2) }}</td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-xs btn-outline-primary btn-edit-rule"
                                    data-id="{{ $rule->id }}"
                                    data-component="{{ $rule->component_code_id }}"
                                    data-damage="{{ $rule->damage_code_id }}"
                                    data-repair="{{ $rule->repair_code_id }}"
                                    data-material="{{ $rule->material_code_id }}"
                                    data-labor_hours="{{ $rule->std_labor_hours }}"
                                    data-labor_rate="{{ $rule->labor_rate }}"
                                    data-material_qty="{{ $rule->material_qty }}"
                                    data-material_rate="{{ $rule->material_rate }}"
                                    data-ancillary="{{ $rule->ancillary }}"
                                    data-min_charge="{{ $rule->min_charge }}"
                                    data-max_charge="{{ $rule->max_charge }}"
                                    data-notes="{{ $rule->notes }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST"
                                  action="{{ route('masters.mr-tariff.rules.destroy', [$mrTariff, $rule]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger"
                                        onclick="return confirm('Delete this rule?')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="13" class="text-center text-muted py-4">
                        <i class="bi bi-list-ul fs-3 d-block mb-1"></i>
                        No rules yet. Click <strong>Add Rule</strong> to define rates.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2">
        <small class="text-muted">
            {{ $mrTariff->rules->count() }} rule(s) ·
            Currency: <strong class="font-monospace">{{ $mrTariff->currency }}</strong>
        </small>
    </div>
</div>

{{-- ── Edit Header Modal ── --}}
<div class="modal fade" id="editHeaderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.mr-tariff.update', $mrTariff) }}">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Tariff Header</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tariff Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100"
                               value="{{ $mrTariff->name }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Owner / Customer</label>
                        <select name="customer_id" class="form-select s2-code">
                            <option value="">— Default / All Customers —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ $mrTariff->customer_id == $c->id ? 'selected' : '' }}>
                                    {{ $c->code }} — {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valid From <span class="text-danger">*</span></label>
                            <input type="date" name="valid_from" class="form-control" required
                                   value="{{ $mrTariff->valid_from->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control"
                                   value="{{ $mrTariff->valid_to?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <input type="text" name="currency" class="form-control text-uppercase font-monospace"
                                   maxlength="3" required value="{{ $mrTariff->currency }}"
                                   oninput="this.value=this.value.toUpperCase()">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Applicable Sizes</label>
                        <div class="d-flex gap-3">
                            @foreach(['20', '40', '45'] as $sz)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="applicable_sizes[]"
                                       value="{{ $sz }}" id="editSz{{ $sz }}"
                                       {{ in_array($sz, $mrTariff->applicable_sizes ?? ['20','40','45']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="editSz{{ $sz }}">{{ $sz }}'</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500">{{ $mrTariff->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Add Rule Modal ── --}}
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.mr-tariff.rules.store', $mrTariff) }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Rate Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Component</label>
                            <select name="component_code_id" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($componentCodes as $c)
                                    <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} — {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Damage</label>
                            <select name="damage_code_id" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($damageCodes as $d)
                                    <option value="{{ $d->id }}" data-code="{{ $d->code }}" data-name="{{ $d->name }}">{{ $d->code }} — {{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Repair <span class="text-danger">*</span></label>
                            <select name="repair_code_id" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($repairCodes as $r)
                                    <option value="{{ $r->id }}" data-code="{{ $r->code }}" data-name="{{ $r->name }}">{{ $r->code }} — {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Material</label>
                            <select name="material_code_id" class="form-select form-select-sm select2 s2-code">
                                <option value="">— None —</option>
                                @foreach($materialCodes as $m)
                                    <option value="{{ $m->id }}" data-code="{{ $m->code }}" data-name="{{ $m->name }}">{{ $m->code }} — {{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Std Hours</label>
                            <input type="number" name="std_labor_hours" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Labour Rate</label>
                            <input type="number" name="labor_rate" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Material Qty</label>
                            <input type="number" name="material_qty" class="form-control form-control-sm"
                                   step="0.001" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Material Rate</label>
                            <input type="number" name="material_rate" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Ancillary</label>
                            <input type="number" name="ancillary" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Min Charge</label>
                            <input type="number" name="min_charge" class="form-control form-control-sm"
                                   step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Max Charge</label>
                            <input type="number" name="max_charge" class="form-control form-control-sm"
                                   step="0.01" min="0" placeholder="(no cap)">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" class="form-control form-control-sm" maxlength="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Rule Modal ── --}}
<div class="modal fade" id="editRuleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="editRuleForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Rate Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Component</label>
                            <select name="component_code_id" id="erComponent" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($componentCodes as $c)
                                    <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} — {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Damage</label>
                            <select name="damage_code_id" id="erDamage" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($damageCodes as $d)
                                    <option value="{{ $d->id }}" data-code="{{ $d->code }}" data-name="{{ $d->name }}">{{ $d->code }} — {{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Repair</label>
                            <select name="repair_code_id" id="erRepair" class="form-select form-select-sm select2 s2-code">
                                <option value="">— Any —</option>
                                @foreach($repairCodes as $r)
                                    <option value="{{ $r->id }}" data-code="{{ $r->code }}" data-name="{{ $r->name }}">{{ $r->code }} — {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Material</label>
                            <select name="material_code_id" id="erMaterial" class="form-select form-select-sm select2 s2-code">
                                <option value="">— None —</option>
                                @foreach($materialCodes as $m)
                                    <option value="{{ $m->id }}" data-code="{{ $m->code }}" data-name="{{ $m->name }}">{{ $m->code }} — {{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Std Hours</label>
                            <input type="number" name="std_labor_hours" id="erHours"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Labour Rate</label>
                            <input type="number" name="labor_rate" id="erLaborRate"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Material Qty</label>
                            <input type="number" name="material_qty" id="erMatQty"
                                   class="form-control form-control-sm" step="0.001" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Material Rate</label>
                            <input type="number" name="material_rate" id="erMatRate"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Ancillary</label>
                            <input type="number" name="ancillary" id="erAncillary"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Min Charge</label>
                            <input type="number" name="min_charge" id="erMinCharge"
                                   class="form-control form-control-sm" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Max Charge</label>
                            <input type="number" name="max_charge" id="erMaxCharge"
                                   class="form-control form-control-sm" step="0.01" min="0" placeholder="(no cap)">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" id="erNotes" class="form-control form-control-sm" maxlength="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-edit-rule').forEach(btn => {
    btn.addEventListener('click', () => {
        const d = btn.dataset;
        document.getElementById('erComponent').value  = d.component  || '';
        document.getElementById('erDamage').value     = d.damage     || '';
        document.getElementById('erRepair').value     = d.repair     || '';
        document.getElementById('erMaterial').value   = d.material   || '';
        document.getElementById('erHours').value      = d.labor_hours;
        document.getElementById('erLaborRate').value  = d.labor_rate;
        document.getElementById('erMatQty').value     = d.material_qty;
        document.getElementById('erMatRate').value    = d.material_rate;
        document.getElementById('erAncillary').value  = d.ancillary;
        document.getElementById('erMinCharge').value  = d.min_charge;
        document.getElementById('erMaxCharge').value  = d.max_charge || '';
        document.getElementById('erNotes').value      = d.notes || '';
        document.getElementById('editRuleForm').action =
            '{{ url("masters/mr-tariff/" . $mrTariff->id . "/rules") }}/' + d.id;

        // Refresh Select2 values
        ['erComponent','erDamage','erRepair','erMaterial'].forEach(id => {
            const el = document.getElementById(id);
            if ($(el).data('select2')) $(el).trigger('change');
        });

        new bootstrap.Modal(document.getElementById('editRuleModal')).show();
    });
});
</script>
@endpush
