@extends('layouts.app')

@section('title', 'Edit Estimate — ' . $estimate->estimate_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item"><a href="{{ route('estimates.show', $estimate) }}">{{ $estimate->estimate_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@push('styles')
<style>
    .estimate-line:hover { background: #f8f9fa; }
    .bd-row > td { border-top: 0 !important; }
    .bd-panel { border-left: 3px solid #0d6efd !important; }
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
        <h4><i class="bi bi-tools me-2 text-primary"></i>Edit Estimate</h4>
        <p class="text-muted mb-0 small">Ref: <strong>{{ $estimate->estimate_no }}</strong>
            &nbsp;·&nbsp; {{ $estimate->estimate_date->format('d M Y') }}</p>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('estimates.update', $estimate) }}" id="estimateForm">
    @csrf
    @method('PUT')

    <div class="row g-3">

        <!-- Main -->
        <div class="col-lg-8">

            <!-- Header Info -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Estimate Header
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Container Number</label>
                            <input type="text" class="form-control font-monospace"
                                   value="{{ $estimate->container_no }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Equipment Type</label>
                            <div class="d-flex flex-wrap gap-1 align-items-center" style="padding-top:.4rem;">
                                @if($estimate->equipmentType)
                                    <span class="badge bg-primary fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                                        {{ $estimate->equipmentType->eqt_code }}
                                    </span>
                                @endif
                                <span class="badge bg-light border text-dark">{{ $estimate->size }}'</span>
                                <span class="badge bg-info-subtle text-info">{{ $estimate->type_code }}</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Inquiry Ref.</label>
                            <input type="text" class="form-control"
                                   value="{{ $estimate->inquiry->inquiry_no ?? '—' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" class="form-control"
                                   value="{{ $estimate->customer->name ?? '—' }}" readonly>
                        </div>
                        @php
                            $editCur     = old('currency', $estimate->currency);
                            $editRate    = old('exchange_rate', $estimate->exchange_rate ?? ($todayRate ?? '1.0000'));
                            $editRateFmt = number_format((float)$editRate, 4, '.', '');
                            // $rateLocked is passed from EstimateController::edit()
                        @endphp
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Estimate Date <span class="text-danger">*</span></label>
                            <input type="date" name="estimate_date" id="estimateDate" class="form-control"
                                   value="{{ old('estimate_date', $estimate->estimate_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" name="valid_until" class="form-control"
                                   value="{{ old('valid_until', $estimate->valid_until->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <select name="currency" id="estimateCurrency" class="form-select" {{ $rateLocked ? 'disabled' : '' }} required>
                                <option value="USD" {{ $editCur==='USD'?'selected':'' }}>USD — US Dollar</option>
                                <option value="{{ $defaultCurrency }}" {{ $editCur===$defaultCurrency && $defaultCurrency!=='USD'?'selected':'' }}>{{ $defaultCurrency }} — Local</option>
                                <option value="EUR" {{ $editCur==='EUR'?'selected':'' }}>EUR — Euro</option>
                                <option value="GBP" {{ $editCur==='GBP'?'selected':'' }}>GBP — British Pound</option>
                                <option value="SGD" {{ $editCur==='SGD'?'selected':'' }}>SGD — Singapore Dollar</option>
                                <option value="AUD" {{ $editCur==='AUD'?'selected':'' }}>AUD — Australian Dollar</option>
                            </select>
                            @if($rateLocked)
                                {{-- keep the value in the form even when select is disabled --}}
                                <input type="hidden" name="currency" value="{{ $editCur }}">
                            @endif
                            <div class="form-text">Tariffs are in USD</div>
                        </div>
                        <div class="col-md-4" id="exchangeRateGroup">
                            <label class="form-label fw-semibold">
                                <span id="estRateLabel">
                                    @if($editCur === 'USD') No conversion (USD tariff)
                                    @else 1 USD = ? {{ $editCur }}
                                    @endif
                                </span>
                                @if(!$rateLocked)<span class="text-danger">*</span>@endif
                                <span id="estRateSpinner" class="spinner-border spinner-border-sm ms-1 d-none" style="width:.7rem;height:.7rem;"></span>
                                @if($rateLocked)
                                    <span class="badge bg-secondary ms-1" style="font-size:.68rem;">Locked</span>
                                @endif
                            </label>
                            <div class="input-group">
                                <span class="input-group-text small px-2" id="estRatePrefix">
                                    {{ $editCur === 'USD' ? '' : '1 USD =' }}
                                </span>
                                <input type="number" name="exchange_rate" id="estimateExchangeRate"
                                       class="form-control" value="{{ $editRateFmt }}"
                                       min="0.0001" step="0.0001"
                                       {{ ($rateLocked || $editCur === 'USD') ? 'readonly' : '' }}>
                                <span class="input-group-text" id="estRateSuffix">{{ $editCur === 'USD' ? '' : $editCur }}</span>
                            </div>
                            <div id="estRateNote" class="form-text">
                                @if($rateLocked)
                                    <span class="text-muted"><i class="bi bi-lock me-1"></i>Rate locked — estimate has been sent to customer</span>
                                @elseif($editCur === 'USD')
                                    <span class="text-muted">Estimate is in USD — no conversion applied</span>
                                @else
                                    <span class="text-success"><i class="bi bi-check-circle me-1"></i>Rate as at estimate date</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Repair Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="normal"   {{ old('priority', $estimate->priority) === 'normal'   ? 'selected' : '' }}>Normal (7–14 days)</option>
                                <option value="urgent"   {{ old('priority', $estimate->priority) === 'urgent'   ? 'selected' : '' }}>Urgent (3–5 days)</option>
                                <option value="critical" {{ old('priority', $estimate->priority) === 'critical' ? 'selected' : '' }}>Critical (Next day)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items</span>
                    <button type="button" class="btn btn-sm btn-outline-success" id="getRateBtn" data-bs-toggle="modal" data-bs-target="#getRateModal">
                        <i class="bi bi-calculator me-1"></i>Get Rate
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLine">
                        <i class="bi bi-plus-circle me-1"></i>Add Line
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="lineTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:11%">MR Code</th>
                                    <th style="width:10%">Charge Code</th>
                                    <th style="width:17%">Description</th>
                                    <th style="width:14%">Repair Type</th>
                                    <th style="width:7%">Qty</th>
                                    <th style="width:10%">Unit Price</th>
                                    <th style="width:8%">Tax Code</th>
                                    <th style="width:11%">Net Amount</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItems">
                                @foreach($estimate->lineItems as $i => $item)
                                <tr class="estimate-line">
                                    <td class="ps-3">
                                        <input type="hidden" name="line_items[{{ $i }}][id]"                value="{{ $item->id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][damage_id]"         value="{{ $item->damage_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][mr_tariff_rule_id]" value="{{ $item->mr_tariff_rule_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][location_code_id]"  value="{{ $item->location_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][damage_code_id]"    value="{{ $item->damage_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][repair_code_id]"    value="{{ $item->repair_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_code_id]"  value="{{ $item->material_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][cedex_code]"        value="{{ $item->cedex_code }}">
                                        <input type="hidden" name="line_items[{{ $i }}][repair_category_id]" value="{{ $item->repair_category_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][std_labor_hours]"   value="{{ $item->std_labor_hours ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][labor_rate]"        value="{{ $item->labor_rate ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][labor_amount]"      value="{{ $item->labor_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_qty]"      value="{{ $item->material_qty ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_rate]"     value="{{ $item->material_rate ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_amount]"   value="{{ $item->material_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][ancillary_amount]"  value="{{ $item->ancillary_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_length]"        value="{{ $item->dim_length ?? '' }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_width]"         value="{{ $item->dim_width ?? '' }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_uom]"           value="{{ $item->dim_uom ?? '' }}">
                                        <select name="line_items[{{ $i }}][component_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">— any —</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ old("line_items.{$i}.component_code_id", $item->component_code_id) == $c->id ? 'selected' : '' }}>
                                                {{ $c->code }} — {{ $c->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][charge_code_id]" class="form-select form-select-sm charge-code-sel s2 s2-code">
                                            <option value="">— none —</option>
                                            @foreach($chargeCodes as $cc)
                                            <option value="{{ $cc->id }}"
                                                    data-code="{{ $cc->code }}"
                                                    data-name="{{ $cc->description }}"
                                                    data-tax1-rate="{{ $cc->taxCode?->tax1_rate ?? 0 }}"
                                                    data-tax2-rate="{{ $cc->taxCode?->tax2_rate ?? 0 }}"
                                                    data-tax-code-id="{{ $cc->tax_code_id ?? '' }}"
                                                    {{ old("line_items.{$i}.charge_code_id", $item->charge_code_id) == $cc->id ? 'selected' : '' }}>
                                                {{ $cc->code }} — {{ $cc->description }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="line_items[{{ $i }}][component]"
                                               class="form-control form-control-sm comp-desc"
                                               value="{{ old("line_items.{$i}.component", $item->component) }}"
                                               placeholder="Description">
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][repair_type]" class="form-select form-select-sm" required>
                                            @foreach(['replace'=>'Replace','repair'=>'Repair','weld'=>'Weld','straighten'=>'Straighten','clean_and_treat'=>'Clean & Treat','paint'=>'Paint'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ old("line_items.{$i}.repair_type", $item->repair_type) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][qty]"
                                               class="form-control form-control-sm qty"
                                               value="{{ old("line_items.{$i}.qty", $item->qty) }}"
                                               min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][unit_price]"
                                               class="form-control form-control-sm unit-price"
                                               value="{{ old("line_items.{$i}.unit_price", $item->unit_price) }}"
                                               step="0.01" min="0" required>
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][tax_code_id]" class="form-select form-select-sm tax-code-sel s2 s2-code">
                                            <option value="">— none —</option>
                                            @foreach($taxCodes as $tc)
                                            <option value="{{ $tc->id }}"
                                                    data-code="{{ $tc->code }}"
                                                    data-name="{{ $tc->code }} (SSCL {{ $tc->tax1_rate }}% + VAT {{ $tc->tax2_rate }}%)"
                                                    data-tax1-rate="{{ $tc->tax1_rate }}"
                                                    data-tax2-rate="{{ $tc->tax2_rate }}"
                                                    title="{{ $tc->code }} (SSCL {{ $tc->tax1_rate }}% + VAT {{ $tc->tax2_rate }}%)"
                                                    {{ old("line_items.{$i}.tax_code_id", $item->tax_code_id) == $tc->id ? 'selected' : '' }}>
                                                {{ $tc->code }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-end pe-2 small">
                                        <div class="fw-semibold line-net">
                                            {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                                        </div>
                                        <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                                            @if(($item->tax1_amount ?? 0) > 0)
                                                <span class="line-sscl-amt">+SSCL {{ number_format($item->tax1_amount, 2) }}</span>
                                            @else
                                                <span class="line-sscl-amt"></span>
                                            @endif
                                            @if(($item->tax2_amount ?? 0) > 0)
                                                <span class="line-vat-amt"> +VAT {{ number_format($item->tax2_amount, 2) }}</span>
                                            @else
                                                <span class="line-vat-amt"></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="pe-2">
                                        <div class="d-flex flex-column gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-breakdown" title="Cost breakdown"><i class="bi bi-sliders2-vertical"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @php $bdTotal = ($item->labor_amount ?? 0) + ($item->material_amount ?? 0) + ($item->ancillary_amount ?? 0); @endphp
                                <tr class="bd-row{{ $bdTotal > 0 ? '' : ' d-none' }}">
                                    <td colspan="99" class="pt-0 pb-2 ps-4 pe-3">
                                        <div class="rounded border bg-light px-3 py-2 bd-panel">
                                            <div class="d-flex flex-wrap align-items-end gap-3 small">
                                                <div class="d-flex align-items-end gap-2">
                                                    <div>
                                                        <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Hrs</div>
                                                        <input type="number" class="form-control form-control-sm bd-labor-hrs" value="{{ $item->std_labor_hours ?? 0 }}" min="0" step="0.25" style="width:65px">
                                                    </div>
                                                    <span class="text-muted mb-1" style="font-size:.85rem;">×</span>
                                                    <div>
                                                        <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Rate / hr</div>
                                                        <input type="number" class="form-control form-control-sm bd-labor-rate" value="{{ $item->labor_rate ?? 0 }}" min="0" step="0.01" style="width:75px">
                                                    </div>
                                                    <span class="text-muted mb-1" style="font-size:.85rem;">=</span>
                                                    <div>
                                                        <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Amt</div>
                                                        <input type="number" class="form-control form-control-sm bd-labor-amt" value="{{ number_format($item->labor_amount ?? 0, 2, '.', '') }}" min="0" step="0.01" style="width:85px">
                                                    </div>
                                                </div>
                                                <div class="vr mx-1"></div>
                                                <div>
                                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Material Amt</div>
                                                    <input type="number" class="form-control form-control-sm bd-material-amt" value="{{ number_format($item->material_amount ?? 0, 2, '.', '') }}" min="0" step="0.01" style="width:90px">
                                                </div>
                                                <div>
                                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Ancillary Amt</div>
                                                    <input type="number" class="form-control form-control-sm bd-ancillary-amt" value="{{ number_format($item->ancillary_amount ?? 0, 2, '.', '') }}" min="0" step="0.01" style="width:90px">
                                                </div>
                                                <div class="ms-auto text-end">
                                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Total ÷ Qty → Unit Price</div>
                                                    <strong class="bd-total text-primary fs-6">{{ number_format($bdTotal, 2, '.', '') }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="7" class="text-end fw-semibold pe-3">Subtotal:</td>
                                    <td class="fw-semibold text-end pe-2" id="subtotal">
                                        {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end pe-3 small text-muted">
                                        SSCL <span id="ssclPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalSscl">
                                        {{ $estimate->currency }} {{ number_format($estimate->sscl_amount ?? 0, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end pe-3 small text-muted">
                                        VAT <span id="vatPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalVat">
                                        {{ $estimate->currency }} {{ number_format($estimate->vat_amount ?? 0, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="7" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
                                    <td class="fw-bold text-end pe-2 fs-6" id="grandTotal">
                                        {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-file-text me-2 text-primary"></i>Terms & Remarks
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope of Work</label>
                        <textarea name="scope_of_work" class="form-control" rows="3"
                                  placeholder="Describe the detailed scope of repair work…">{{ old('scope_of_work', $estimate->scope_of_work) }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Terms &amp; Conditions</label>
                        <textarea name="terms" class="form-control" rows="3">{{ old('terms', $estimate->terms) }}</textarea>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">

            <!-- Send Options -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-send me-2 text-primary"></i>Send Options
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Send To</label>
                        <textarea name="send_to_email" class="form-control form-control-sm" rows="2"
                                  placeholder="customer@email.com">{{ old('send_to_email', $estimate->send_to_email) }}</textarea>
                        <div class="form-text">Separate multiple addresses with a comma, semicolon, or new line.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC</label>
                        <textarea name="send_cc_email" class="form-control form-control-sm" rows="2"
                                  placeholder="manager@email.com">{{ old('send_cc_email', $estimate->send_cc_email) }}</textarea>
                        <div class="form-text">Separate multiple addresses with a comma, semicolon, or new line.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Brief message to the customer…">{{ old('email_message', $estimate->email_message) }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="attach_pdf" id="attachPdf"
                               value="1" {{ old('attach_pdf', $estimate->attach_pdf) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="attachPdf">Attach PDF estimate</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_photos" id="attachPhotos"
                               value="1" {{ old('attach_photos', $estimate->attach_photos) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="attachPhotos">Attach inspection photos</label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Changes
                </button>
                <a href="{{ route('estimates.show', $estimate) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>

    </div>
</form>

{{-- ── Get Rate Modal ── --}}
<div class="modal fade" id="getRateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">
                    <i class="bi bi-calculator me-2 text-success"></i>M&amp;R Tariff Rate Lookup
                    <span id="grCurrencyBadge" class="badge bg-primary-subtle text-primary border ms-2 fw-normal d-none" style="font-size:.72rem;"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" id="grSearchQ" class="form-control form-control-sm" placeholder="Search item code or description…">
                    </div>
                    <div class="col-md-3">
                        <select id="grOpFilter" class="form-select form-select-sm">
                            <option value="">All Operations</option>
                            <option value="straight">Straight</option>
                            <option value="insert">Insert</option>
                            <option value="section">Section</option>
                            <option value="replace">Replace</option>
                            <option value="weld">Weld</option>
                            <option value="remove">Remove</option>
                            <option value="paint">Paint</option>
                            <option value="resecure">Resecure</option>
                            <option value="free">Free / Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="grUnitFilter" class="form-select form-select-sm">
                            <option value="">All Units</option>
                            <option value="nos">NOS</option>
                            <option value="lift">LIFT</option>
                            <option value="sqft">SQFT</option>
                            <option value="inches">INCHES</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-primary w-100" id="grSearchBtn">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-2" style="width:100px">Code</th>
                                <th style="width:90px">Operation</th>
                                <th>Description</th>
                                <th style="width:60px">Unit</th>
                                <th style="width:60px">Slabs</th>
                                <th style="width:90px">Qty</th>
                                <th style="width:70px">Labor Rate</th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody id="grResultBody">
                            <tr><td colspan="8" class="text-center text-muted py-4">Search for tariff items above.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="grRateResult" class="d-none mt-3 p-3 border rounded bg-light">
                    <div class="row g-2 small">
                        <div class="col-md-2"><div class="text-muted">Labor Hours</div><div class="fw-bold" id="grLaborHrs">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Labor Amount</div><div class="fw-bold" id="grLaborAmt">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Material Cost</div><div class="fw-bold" id="grMaterialAmt">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Total</div><div class="fw-bold text-success fs-6" id="grTotal">—</div></div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-success w-100" id="grApplyBtn">
                                <i class="bi bi-check-circle me-1"></i>Apply Rate to New Line
                            </button>
                        </div>
                    </div>
                    <div id="grFxNote" class="d-none mt-2" style="font-size:.72rem;color:#6c757d;"></div>
                    <div id="grRateWarning" class="d-none alert alert-warning py-2 px-2 mt-2 mb-0 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="grRateWarningText"></span>
                        You can still enter the price manually, or
                        <a href="{{ route('masters.mr-tariff.index') }}" target="_blank">update the MR tariff &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Exchange rate auto-fetch ───────────────────────────────────────────
    const EXCH_RATE_URL    = '{{ route("estimates.exchange-rate") }}';
    const DEFAULT_CURRENCY = '{{ $defaultCurrency }}';
    const RATE_LOCKED      = {{ $rateLocked ? 'true' : 'false' }};

    async function fetchEstimateExchangeRate() {
        if (RATE_LOCKED) return;
        const currency = document.getElementById('estimateCurrency')?.value || 'USD';
        const date     = document.getElementById('estimateDate')?.value;
        const note     = document.getElementById('estRateNote');
        const spinner  = document.getElementById('estRateSpinner');
        const input    = document.getElementById('estimateExchangeRate');
        const prefix   = document.getElementById('estRatePrefix');
        const suffix   = document.getElementById('estRateSuffix');
        const label    = document.getElementById('estRateLabel');

        if (currency === 'USD') {
            if (input)  { input.value = '1.0000'; input.readOnly = true; }
            if (label)  label.textContent = 'No conversion (USD tariff)';
            if (prefix) prefix.textContent = '';
            if (suffix) suffix.textContent = '';
            if (note)   note.innerHTML = '<span class="text-muted">Estimate is in USD — no conversion applied.</span>';
            return;
        }

        if (input) input.readOnly = false;
        if (label)  label.textContent = `1 USD = ? ${currency}`;
        if (prefix) prefix.textContent = '1 USD =';
        if (suffix) suffix.textContent = currency;

        if (!date) return;
        if (spinner) spinner.classList.remove('d-none');
        try {
            const res  = await fetch(`${EXCH_RATE_URL}?currency=USD&target=${encodeURIComponent(currency)}&date=${encodeURIComponent(date)}`);
            const data = await res.json();
            if (data.found && data.rate) {
                if (input) input.value = parseFloat(data.rate).toFixed(4);
                if (note)  note.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Rate auto-loaded: 1 USD = ${parseFloat(data.rate).toFixed(4)} ${currency}</span>`;
            } else {
                if (note)  note.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No rate found — enter manually</span>`;
            }
        } catch (_) {
            if (note) note.innerHTML = '<span class="text-danger">Failed to fetch rate</span>';
        } finally {
            if (spinner) spinner.classList.add('d-none');
        }
    }

    // ── Live currency / exchange-rate reconversion ───────────────────────────
    // Same model as the create form: on-screen amounts are held in the estimate
    // currency at `appliedFactor` (1.0 for USD, else USD→currency rate). Editing
    // the rate — allowed until the estimate is sent — rescales every line so the
    // totals track the new rate rather than keeping the old-rate magnitudes.
    function estFactor() {
        const cur  = document.getElementById('estimateCurrency')?.value || 'USD';
        const rate = parseFloat(document.getElementById('estimateExchangeRate')?.value) || 0;
        return cur === 'USD' ? 1.0 : (rate > 0 ? rate : 1.0);
    }
    let appliedFactor = estFactor();

    function scaleField(el, ratio, dp) {
        if (!el) return;
        el.value = ((parseFloat(el.value) || 0) * ratio).toFixed(dp);
    }

    function reconvertLines(ratio) {
        if (!isFinite(ratio) || ratio <= 0 || ratio === 1) return;
        document.querySelectorAll('.estimate-line').forEach(row => {
            const bdRow = row.nextElementSibling;
            const hasBd = bdRow && bdRow.classList.contains('bd-row');
            const laborAmt = parseFloat(row.querySelector('[name$="[labor_amount]"]')?.value) || 0;
            const matAmt   = parseFloat(row.querySelector('[name$="[material_amount]"]')?.value) || 0;
            const ancAmt   = parseFloat(row.querySelector('[name$="[ancillary_amount]"]')?.value) || 0;
            if (hasBd && (laborAmt + matAmt + ancAmt) > 0) {
                scaleField(bdRow.querySelector('.bd-labor-rate'),    ratio, 2);
                scaleField(bdRow.querySelector('.bd-material-amt'),  ratio, 2);
                scaleField(bdRow.querySelector('.bd-ancillary-amt'), ratio, 2);
                scaleField(row.querySelector('[name$="[material_rate]"]'), ratio, 2);
                syncBreakdown(bdRow, null);
            } else {
                scaleField(row.querySelector('.unit-price'), ratio, 4);
            }
        });
        recalculate();
    }

    function applyCurrencyRate() {
        const nf = estFactor();
        if (appliedFactor > 0 && nf !== appliedFactor) {
            reconvertLines(nf / appliedFactor);
        }
        appliedFactor = nf;
    }

    if (!RATE_LOCKED) {
        document.getElementById('estimateCurrency')?.addEventListener('change', async function () {
            await fetchEstimateExchangeRate();
            applyCurrencyRate();
        });
        document.getElementById('estimateExchangeRate')?.addEventListener('change', applyCurrencyRate);
        document.getElementById('estimateDate')?.addEventListener('change', async function () {
            const currency = document.getElementById('estimateCurrency')?.value || 'USD';
            if (currency !== 'USD') {
                await fetchEstimateExchangeRate();
                applyCurrencyRate();
            }
        });
    }

    let lineIdx  = {{ $estimate->lineItems->count() }};
    const currency = '{{ $estimate->currency }}';
    // Totals label follows the live currency dropdown (falls back to the saved code).
    function curCode() { return document.getElementById('estimateCurrency')?.value || currency; }

    function fmt(n)      { return curCode() + ' ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function fmtSmall(n) { return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function esc(str)    { return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // MR component code options
    const mrCmpCodeOpts = @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));

    // Charge code options with embedded Tax1/Tax2 rates
    @php
    $chargeCodeJson = $chargeCodes->map(fn($c) => [
        'id'          => $c->id,
        'code'        => $c->code,
        'description' => $c->description,
        'tax1_rate'   => $c->taxCode?->tax1_rate ?? 0,
        'tax2_rate'   => $c->taxCode?->tax2_rate ?? 0,
        'tax_code_id' => $c->tax_code_id,
    ]);
    @endphp
    const chargeCodeOpts = @json($chargeCodeJson);

    // Tax code options
    @php
    $taxCodeJson = $taxCodes->map(fn($tc) => [
        'id'        => $tc->id,
        'code'      => $tc->code,
        'tax1_rate' => $tc->tax1_rate,
        'tax2_rate' => $tc->tax2_rate,
    ]);
    @endphp
    const taxCodeOpts = @json($taxCodeJson);

    const resolveUrl = '{{ route("estimates.resolve-charge-code") }}';

    function buildCompSelect(name) {
        let opts = '<option value="">— any —</option>';
        mrCmpCodeOpts.forEach(o => { opts += `<option value="${o.id}" data-code="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.code)} — ${esc(o.name)}</option>`; });
        return `<select name="${name}" class="form-select form-select-sm mb-1 s2 s2-code">${opts}</select>`;
    }

    function buildChargeCodeSelect(name) {
        let opts = '<option value="">— none —</option>';
        chargeCodeOpts.forEach(c => {
            opts += `<option value="${c.id}" data-code="${esc(c.code)}" data-name="${esc(c.description)}" data-tax1-rate="${c.tax1_rate}" data-tax2-rate="${c.tax2_rate}" data-tax-code-id="${c.tax_code_id ?? ''}">${esc(c.code)} — ${esc(c.description)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm charge-code-sel s2 s2-code">${opts}</select>`;
    }

    function buildTaxCodeSelect(name) {
        let opts = '<option value="">— none —</option>';
        taxCodeOpts.forEach(tc => {
            const fullLabel = `${tc.code} (SSCL ${tc.tax1_rate}% + VAT ${tc.tax2_rate}%)`;
            opts += `<option value="${tc.id}" data-code="${esc(tc.code)}" data-name="${esc(fullLabel)}" data-tax1-rate="${tc.tax1_rate}" data-tax2-rate="${tc.tax2_rate}" title="${esc(fullLabel)}">${esc(tc.code)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm tax-code-sel s2 s2-code">${opts}</select>`;
    }

    function initLineSelects(tr) {
        $(tr).find('select.s2').each(function() { window.initS2Code($(this), { width: '100%' }); });
    }

    function applyChargeToRow(row, chargeCodeId, taxCodeId) {
        const chargeSel  = row.querySelector('.charge-code-sel');
        const taxCodeSel = row.querySelector('.tax-code-sel');
        if (chargeSel) {
            chargeSel.value = chargeCodeId || '';
            if (typeof $ !== 'undefined') $(chargeSel).trigger('change.select2');
        }
        if (taxCodeSel) {
            taxCodeSel.value = taxCodeId ?? '';
            if (typeof $ !== 'undefined') $(taxCodeSel).trigger('change.select2');
        }
        recalculate();
    }

    function syncBreakdown(bdRow, changedInput) {
        const hrs  = parseFloat(bdRow.querySelector('.bd-labor-hrs').value)  || 0;
        const rate = parseFloat(bdRow.querySelector('.bd-labor-rate').value) || 0;
        if (!changedInput?.classList.contains('bd-labor-amt')) {
            bdRow.querySelector('.bd-labor-amt').value = (hrs * rate).toFixed(2);
        }
        const laborAmt = parseFloat(bdRow.querySelector('.bd-labor-amt').value)    || 0;
        const matAmt   = parseFloat(bdRow.querySelector('.bd-material-amt').value)  || 0;
        const ancAmt   = parseFloat(bdRow.querySelector('.bd-ancillary-amt').value) || 0;
        const total    = laborAmt + matAmt + ancAmt;
        bdRow.querySelector('.bd-total').textContent = total.toFixed(2);

        const mainRow = bdRow.previousElementSibling;
        const qty     = parseFloat(mainRow?.querySelector('.qty')?.value) || 1;
        if (mainRow) {
            mainRow.querySelector('.unit-price').value                    = (qty > 0 ? total / qty : total).toFixed(4);
            mainRow.querySelector('[name$="[std_labor_hours]"]').value    = hrs;
            mainRow.querySelector('[name$="[labor_rate]"]').value         = rate;
            mainRow.querySelector('[name$="[labor_amount]"]').value       = laborAmt.toFixed(4);
            mainRow.querySelector('[name$="[material_amount]"]').value    = matAmt.toFixed(4);
            mainRow.querySelector('[name$="[ancillary_amount]"]').value   = ancAmt.toFixed(4);
        }
    }

    function recalculate() {
        let subtotal = 0, ssclTotal = 0, vatTotal = 0;
        let ssclRates = new Set(), vatRates = new Set();

        document.querySelectorAll('.estimate-line').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty')?.value       || 0);
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const net   = qty * price;

            const taxSel = row.querySelector('.tax-code-sel');
            const selOpt = taxSel?.selectedOptions[0];
            const t1Rate = parseFloat(selOpt?.dataset.tax1Rate || 0);
            const t2Rate = parseFloat(selOpt?.dataset.tax2Rate || 0);

            const t1 = net * (t1Rate / 100);
            const t2 = (net + t1) * (t2Rate / 100);

            subtotal  += net;
            ssclTotal += t1;
            vatTotal  += t2;

            if (t1Rate > 0) ssclRates.add(t1Rate);
            if (t2Rate > 0) vatRates.add(t2Rate);

            const netEl = row.querySelector('.line-net');
            const sEl   = row.querySelector('.line-sscl-amt');
            const vEl   = row.querySelector('.line-vat-amt');
            if (netEl) netEl.textContent = fmt(net);
            if (sEl)   sEl.textContent  = t1 > 0 ? '+SSCL ' + fmtSmall(t1) : '';
            if (vEl)   vEl.textContent  = t2 > 0 ? ' +VAT ' + fmtSmall(t2) : '';
        });

        document.getElementById('subtotal').textContent   = fmt(subtotal);
        document.getElementById('totalSscl').textContent  = fmt(ssclTotal);
        document.getElementById('totalVat').textContent   = fmt(vatTotal);
        document.getElementById('grandTotal').textContent  = fmt(subtotal + ssclTotal + vatTotal);

        document.getElementById('ssclPctDisplay').textContent = ssclRates.size === 1 ? `(${[...ssclRates][0]}%)` : '';
        document.getElementById('vatPctDisplay').textContent  = vatRates.size  === 1 ? `(${[...vatRates][0]}%)`  : '';
    }

    // Change events: component code → auto-fill + AJAX resolve; charge code → set tax code; tax code → recalc
    document.getElementById('lineItems').addEventListener('change', function (e) {
        const sel = e.target;
        const row = sel.closest('tr');
        if (!row) return;

        if (sel.name?.includes('[component_code_id]')) {
            const descInput = row.querySelector('.comp-desc');
            if (descInput && !descInput.value.trim()) {
                const opt = mrCmpCodeOpts.find(o => o.id == sel.value);
                if (opt) descInput.value = opt.name;
            }
            if (sel.value) {
                const repairCodeId = row.querySelector('[name*="[repair_code_id]"]')?.value || '';
                const url = resolveUrl + '?component_code_id=' + sel.value + (repairCodeId ? '&repair_code_id=' + repairCodeId : '');
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        if (data.found) {
                            applyChargeToRow(row, data.charge_code_id, data.tax_code_id);
                        }
                    })
                    .catch(() => {});
            }
        }

        if (sel.classList.contains('charge-code-sel')) {
            const opt = sel.selectedOptions[0];
            applyChargeToRow(row, opt?.value || '', opt?.dataset.taxCodeId || '');
        }

        if (sel.classList.contains('tax-code-sel')) {
            recalculate();
        }
    });

    document.getElementById('lineTable').addEventListener('input', function (e) {
        const bdRow = e.target.closest('.bd-row');
        if (bdRow) {
            syncBreakdown(bdRow, e.target);
        } else if (e.target.classList.contains('qty')) {
            const mainRow = e.target.closest('.estimate-line');
            const sibBd   = mainRow?.nextElementSibling;
            if (sibBd?.classList.contains('bd-row')) {
                const total = parseFloat(sibBd.querySelector('.bd-total')?.textContent) || 0;
                if (total > 0) {
                    mainRow.querySelector('.unit-price').value = (total / (parseFloat(e.target.value) || 1)).toFixed(4);
                }
            }
        }
        recalculate();
    });

    // Add new row
    document.getElementById('addLine').addEventListener('click', function () {
        const tbody = document.getElementById('lineItems');
        const i = lineIdx++;
        tbody.insertAdjacentHTML('beforeend', `
            <tr class="estimate-line js-new-line">
                <td class="ps-3">
                    ${buildCompSelect(`line_items[${i}][component_code_id]`)}
                    <input type="hidden" name="line_items[${i}][damage_id]"         value="">
                    <input type="hidden" name="line_items[${i}][mr_tariff_rule_id]" value="">
                    <input type="hidden" name="line_items[${i}][location_code_id]"  value="">
                    <input type="hidden" name="line_items[${i}][damage_code_id]"    value="">
                    <input type="hidden" name="line_items[${i}][repair_code_id]"    value="">
                    <input type="hidden" name="line_items[${i}][material_code_id]"  value="">
                    <input type="hidden" name="line_items[${i}][cedex_code]"        value="">
                    <input type="hidden" name="line_items[${i}][repair_category_id]" value="">
                    <input type="hidden" name="line_items[${i}][std_labor_hours]"   value="0">
                    <input type="hidden" name="line_items[${i}][labor_rate]"        value="0">
                    <input type="hidden" name="line_items[${i}][labor_amount]"      value="0">
                    <input type="hidden" name="line_items[${i}][material_qty]"      value="0">
                    <input type="hidden" name="line_items[${i}][material_rate]"     value="0">
                    <input type="hidden" name="line_items[${i}][material_amount]"   value="0">
                    <input type="hidden" name="line_items[${i}][ancillary_amount]"  value="0">
                    <input type="hidden" name="line_items[${i}][dim_length]"        value="">
                    <input type="hidden" name="line_items[${i}][dim_width]"         value="">
                    <input type="hidden" name="line_items[${i}][dim_uom]"           value="">
                </td>
                <td>${buildChargeCodeSelect(`line_items[${i}][charge_code_id]`)}</td>
                <td><input type="text" name="line_items[${i}][component]" class="form-control form-control-sm comp-desc" placeholder="Description"></td>
                <td>
                    <select name="line_items[${i}][repair_type]" class="form-select form-select-sm" required>
                        <option value="replace">Replace</option>
                        <option value="repair" selected>Repair</option>
                        <option value="weld">Weld</option>
                        <option value="straighten">Straighten</option>
                        <option value="clean_and_treat">Clean &amp; Treat</option>
                        <option value="paint">Paint</option>
                    </select>
                </td>
                <td><input type="number" name="line_items[${i}][qty]"        class="form-control form-control-sm qty"        value="1"    min="0.01" step="0.01" required></td>
                <td><input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="0.00" step="0.01" min="0"   required></td>
                <td>${buildTaxCodeSelect(`line_items[${i}][tax_code_id]`)}</td>
                <td class="text-end pe-2 small">
                    <div class="fw-semibold line-net">${currency} 0.00</div>
                    <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                        <span class="line-sscl-amt"></span>
                        <span class="line-vat-amt"></span>
                    </div>
                </td>
                <td class="pe-2">
                    <div class="d-flex flex-column gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-breakdown" title="Cost breakdown"><i class="bi bi-sliders2-vertical"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
            <tr class="bd-row d-none">
                <td colspan="99" class="pt-0 pb-2 ps-4 pe-3">
                    <div class="rounded border bg-light px-3 py-2 bd-panel">
                        <div class="d-flex flex-wrap align-items-end gap-3 small">
                            <div class="d-flex align-items-end gap-2">
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Hrs</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-hrs" value="0" min="0" step="0.25" style="width:65px">
                                </div>
                                <span class="text-muted mb-1" style="font-size:.85rem;">×</span>
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Rate / hr</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-rate" value="0" min="0" step="0.01" style="width:75px">
                                </div>
                                <span class="text-muted mb-1" style="font-size:.85rem;">=</span>
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Amt</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-amt" value="0" min="0" step="0.01" style="width:85px">
                                </div>
                            </div>
                            <div class="vr mx-1"></div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Material Amt</div>
                                <input type="number" class="form-control form-control-sm bd-material-amt" value="0" min="0" step="0.01" style="width:90px">
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Ancillary Amt</div>
                                <input type="number" class="form-control form-control-sm bd-ancillary-amt" value="0" min="0" step="0.01" style="width:90px">
                            </div>
                            <div class="ms-auto text-end">
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Total ÷ Qty → Unit Price</div>
                                <strong class="bd-total text-primary fs-6">0.00</strong>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `);
        initLineSelects(tbody.lastElementChild.previousElementSibling);
        recalculate();
    });

    document.getElementById('lineItems').addEventListener('click', function (e) {
        if (e.target.closest('.btn-breakdown')) {
            const mainRow = e.target.closest('.estimate-line');
            mainRow?.nextElementSibling?.classList.toggle('d-none');
            return;
        }
        if (e.target.closest('.remove-line')) {
            if (document.querySelectorAll('.estimate-line').length > 1) {
                const mainRow = e.target.closest('.estimate-line');
                const bdRow   = mainRow.nextElementSibling;
                if (bdRow?.classList.contains('bd-row')) bdRow.remove();
                mainRow.remove();
                recalculate();
            }
        }
    });

    // Initialize Select2 on all Blade-rendered line rows
    $(function () {
        document.querySelectorAll('#lineItems .estimate-line').forEach(function (row) {
            initLineSelects(row);
        });
    });

    // Initial calculation from Blade-rendered rows
    recalculate();

    // ── Get Rate Modal ────────────────────────────────────────────────────────
    (function () {
        const searchBtn  = document.getElementById('grSearchBtn');
        const resultBody = document.getElementById('grResultBody');
        const rateResult = document.getElementById('grRateResult');
        const applyBtn   = document.getElementById('grApplyBtn');
        let   selectedItem = null;
        let   selectedRate = null;

        function grFxFactor() {
            const cur  = document.getElementById('estimateCurrency')?.value || 'USD';
            const rate = parseFloat(document.getElementById('estimateExchangeRate')?.value) || 1.0;
            return cur !== 'USD' ? rate : 1.0;
        }

        function grUpdateCurrencyUI() {
            const cur   = document.getElementById('estimateCurrency')?.value || 'USD';
            const rate  = parseFloat(document.getElementById('estimateExchangeRate')?.value) || 1.0;
            const badge = document.getElementById('grCurrencyBadge');
            const note  = document.getElementById('grFxNote');
            if (badge) {
                badge.textContent = cur;
                badge.classList.remove('d-none');
            }
            if (note) {
                if (cur !== 'USD' && rate !== 1.0) {
                    note.innerHTML = `<i class="bi bi-info-circle me-1"></i>Tariff rates are in USD. Amounts above are converted at <strong>1 USD = ${rate.toFixed(4)} ${cur}</strong>.`;
                    note.classList.remove('d-none');
                } else {
                    note.classList.add('d-none');
                }
            }
        }

        document.getElementById('getRateModal')?.addEventListener('show.bs.modal', grUpdateCurrencyUI);
        const opColors = {
            straight:'info', insert:'success', section:'warning',
            replace:'danger', weld:'secondary', remove:'dark',
            paint:'primary', resecure:'info', free:'secondary'
        };

        function fetchItems() {
            const q  = document.getElementById('grSearchQ').value;
            const op = document.getElementById('grOpFilter').value;
            const ut = document.getElementById('grUnitFilter').value;
            const params = new URLSearchParams();
            if (q)  params.set('q', q);
            if (op) params.set('operation_type', op);
            if (ut) params.set('unit_type', ut);

            resultBody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
            rateResult.classList.add('d-none');

            fetch('{{ route("masters.mr-tariff.item-search") }}?' + params)
                .then(r => r.json())
                .then(data => renderItems(data.items))
                .catch(() => { resultBody.innerHTML = '<tr><td colspan="8" class="text-danger text-center py-3">Failed to load items.</td></tr>'; });
        }

        const YARD_DIM_UOM = '{{ $dimUom ?? "ft_in" }}';
        const DIM_UOM_LABEL = { ft_in: 'ft', cm: 'cm', m: 'm' }[YARD_DIM_UOM] || 'ft';

        // dimL / dimW units depend on YARD_DIM_UOM:
        //   ft_in → total decimal inches (e.g. 1 ft 2 in = 14.0)
        //   cm    → centimetres
        //   m     → metres
        function dimsToQty(unitType, dimL, dimW) {
            dimL = parseFloat(dimL) || 0;
            dimW = parseFloat(dimW) || 0;
            if (unitType === 'sqft') {
                if (YARD_DIM_UOM === 'ft_in') return (dimL * dimW) / 144;   // in² → sqft
                if (YARD_DIM_UOM === 'cm')    return (dimL * dimW) / 929.0304;
                if (YARD_DIM_UOM === 'm')     return (dimL * dimW) / 0.09290304;
                return dimL * dimW;
            }
            if (unitType === 'inches') {
                if (YARD_DIM_UOM === 'ft_in') return dimL;           // already total inches
                if (YARD_DIM_UOM === 'cm')    return dimL / 2.54;
                if (YARD_DIM_UOM === 'm')     return dimL / 0.0254;
                return dimL;
            }
            return null; // nos / lift: no conversion
        }

        // Read the ft+in pair inputs from a Get Rate modal row and return total inches
        function grReadTotalIn(row, axis) {
            const ft = parseFloat(row.querySelector(`.gr-ft-${axis}`)?.value) || 0;
            const inch = parseFloat(row.querySelector(`.gr-in-${axis}`)?.value) || 0;
            return ft * 12 + inch;
        }

        function dimQtyCell(unitType) {
            if (unitType === 'sqft') {
                if (YARD_DIM_UOM === 'ft_in') {
                    return `<div class="d-flex flex-column gap-1" style="min-width:148px;" onclick="event.stopPropagation()">
                        <div class="d-flex align-items-center gap-1">
                            <input type="number" class="form-control form-control-sm dim-no-spin gr-ft-l" placeholder="0" min="0" step="1" style="width:40px" title="Length feet">
                            <span class="dim-unit-lbl">ft</span>
                            <input type="number" class="form-control form-control-sm dim-no-spin gr-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:40px" title="Length inches">
                            <span class="dim-unit-lbl">in</span>
                            <span class="dim-axis-lbl">L</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <input type="number" class="form-control form-control-sm dim-no-spin gr-ft-w" placeholder="0" min="0" step="1" style="width:40px" title="Width feet">
                            <span class="dim-unit-lbl">ft</span>
                            <input type="number" class="form-control form-control-sm dim-no-spin gr-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:40px" title="Width inches">
                            <span class="dim-unit-lbl">in</span>
                            <span class="dim-axis-lbl">W</span>
                        </div>
                        <input type="hidden" class="gr-qty" value="">
                        <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;sqft</div>
                    </div>`;
                }
                return `<div class="d-flex align-items-center gap-1 flex-wrap" onclick="event.stopPropagation()">
                    <input type="number" class="form-control form-control-sm gr-dim-l" placeholder="L" min="0.01" step="0.01" style="width:52px" title="Length (${DIM_UOM_LABEL})">
                    <span class="text-muted small">×</span>
                    <input type="number" class="form-control form-control-sm gr-dim-w" placeholder="W" min="0.01" step="0.01" style="width:52px" title="Width (${DIM_UOM_LABEL})">
                    <span class="text-muted" style="font-size:.72rem;">${DIM_UOM_LABEL}</span>
                    <input type="hidden" class="gr-qty" value="1">
                    <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;sqft</div>
                </div>`;
            }
            if (unitType === 'inches') {
                if (YARD_DIM_UOM === 'ft_in') {
                    return `<div class="d-flex align-items-center gap-1" onclick="event.stopPropagation()">
                        <input type="number" class="form-control form-control-sm dim-no-spin gr-ft-l" placeholder="0" min="0" step="1" style="width:40px" title="Length feet">
                        <span class="dim-unit-lbl">ft</span>
                        <input type="number" class="form-control form-control-sm dim-no-spin gr-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:40px" title="Length inches">
                        <span class="dim-unit-lbl">in</span>
                        <input type="hidden" class="gr-qty" value="">
                        <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;in</div>
                    </div>`;
                }
                return `<div class="d-flex align-items-center gap-1" onclick="event.stopPropagation()">
                    <input type="number" class="form-control form-control-sm gr-dim-l" placeholder="Length" min="0.01" step="0.01" style="width:68px" title="Length (${DIM_UOM_LABEL})">
                    <span class="text-muted" style="font-size:.72rem;">${DIM_UOM_LABEL}</span>
                    <input type="hidden" class="gr-qty" value="1">
                    <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;in</div>
                </div>`;
            }
            return `<input type="number" class="form-control form-control-sm gr-qty" value="1" min="0.01" step="0.01" style="width:70px" onclick="event.stopPropagation()">`;
        }

        function renderItems(items) {
            if (!items.length) {
                resultBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No items found.</td></tr>';
                return;
            }
            resultBody.innerHTML = items.map(item => {
                const color = opColors[item.operation_type] || 'secondary';
                return `<tr data-item-id="${item.id}" data-item-unit="${item.unit_type}" data-item-desc="${item.description}" style="cursor:pointer;">
                    <td class="ps-2 font-monospace small">${item.tariff_code || '—'}</td>
                    <td><span class="badge bg-${color}-subtle text-${color} border text-uppercase small">${item.operation_type}</span></td>
                    <td class="small">${item.description}</td>
                    <td class="small text-muted">${item.unit_type.toUpperCase()}</td>
                    <td class="text-center small">${item.slab_count}</td>
                    <td>${dimQtyCell(item.unit_type)}</td>
                    <td><input type="number" class="form-control form-control-sm gr-labor-rate" value="0" min="0" step="0.01" style="width:65px" placeholder="Rate" onclick="event.stopPropagation()"></td>
                    <td><button type="button" class="btn btn-xs btn-outline-success gr-calc-btn" onclick="event.stopPropagation()">
                        <i class="bi bi-calculator"></i> Calc
                    </button></td>
                </tr>`;
            }).join('');

            // Re-usable: read dimL and dimW as total inches (ft_in) or raw value (cm/m)
            function grReadDims(row) {
                if (YARD_DIM_UOM === 'ft_in') {
                    return { dimL: grReadTotalIn(row, 'l'), dimW: grReadTotalIn(row, 'w') };
                }
                return {
                    dimL: parseFloat(row.querySelector('.gr-dim-l')?.value) || 0,
                    dimW: parseFloat(row.querySelector('.gr-dim-w')?.value) || 0,
                };
            }

            resultBody.addEventListener('input', function (e) {
                const inp = e.target;
                const isDim = ['gr-dim-l','gr-dim-w','gr-ft-l','gr-in-l','gr-ft-w','gr-in-w']
                              .some(c => inp.classList.contains(c));
                if (!isDim) return;
                const row  = inp.closest('tr');
                const unit = row?.dataset.itemUnit;
                const { dimL, dimW } = grReadDims(row);
                const computed = dimsToQty(unit, dimL, dimW);
                if (computed !== null && computed > 0) {
                    const qtyHidden = row.querySelector('.gr-qty');
                    if (qtyHidden) qtyHidden.value = computed.toFixed(4);
                    const display = row.querySelector('[data-dim-display]');
                    if (display) {
                        const suffix = unit === 'sqft' ? ' sqft' : ' in';
                        display.textContent = computed.toFixed(3) + suffix;
                    }
                }
            });

            resultBody.querySelectorAll('.gr-calc-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row       = this.closest('tr');
                    const itemId    = row.dataset.itemId;
                    const qty       = parseFloat(row.querySelector('.gr-qty').value) || 1;
                    const laborRate = parseFloat(row.querySelector('.gr-labor-rate').value) || 0;
                    const custId    = document.querySelector('[name="customer_id"]')?.value || '';
                    const { dimL, dimW } = grReadDims(row);
                    const hasDim = dimL > 0;

                    const params = new URLSearchParams({ item_id: itemId, qty, labor_rate: laborRate });
                    if (custId) params.set('customer_id', custId);

                    fetch('{{ route("masters.mr-tariff.rate-lookup") }}?' + params)
                        .then(r => r.json())
                        .then(result => {
                            selectedItem = {
                                id: itemId, desc: row.dataset.itemDesc,
                                unit: row.dataset.itemUnit, qty, laborRate,
                                dimL: hasDim ? dimL : null,
                                dimW: hasDim ? dimW : null,
                                dimUom: hasDim ? YARD_DIM_UOM : null,
                            };
                            selectedRate = result;
                            const fx = grFxFactor();
                            document.getElementById('grLaborHrs').textContent    = result.labor_hours.toFixed(3) + ' hrs';
                            document.getElementById('grLaborAmt').textContent    = (result.labor_amount  * fx).toFixed(2);
                            document.getElementById('grMaterialAmt').textContent = (result.material_cost * fx).toFixed(2);
                            document.getElementById('grTotal').textContent       = (result.total         * fx).toFixed(2);
                            grUpdateCurrencyUI();

                            // Non-blocking warning when the MR tariff yields no usable rate
                            const warnBox = document.getElementById('grRateWarning');
                            if (result.rate_missing) {
                                document.getElementById('grRateWarningText').textContent =
                                    (result.rate_missing_reason || 'No tariff rate found for this item.') + ' ';
                                warnBox.classList.remove('d-none');
                            } else {
                                warnBox.classList.add('d-none');
                            }

                            rateResult.classList.remove('d-none');
                        })
                        .catch(() => alert('Rate lookup failed.'));
                });
            });
        }

        searchBtn.addEventListener('click', fetchItems);
        document.getElementById('grSearchQ').addEventListener('keydown', e => { if (e.key === 'Enter') fetchItems(); });

        applyBtn.addEventListener('click', function () {
            if (!selectedItem || !selectedRate) return;
            const fx    = grFxFactor();
            const tbody = document.getElementById('lineItems');
            const i = lineIdx++;
            tbody.insertAdjacentHTML('beforeend', `<tr class="estimate-line">
                <td class="ps-2 text-center"><span class="badge bg-light text-muted border"><i class="bi bi-pencil"></i></span></td>
                <td class="ps-1">
                    ${buildCompSelect(`line_items[${i}][component_code_id]`)}
                    <input type="hidden" name="line_items[${i}][damage_id]" value="">
                    <input type="hidden" name="line_items[${i}][mr_tariff_rule_id]" value="">
                    <input type="hidden" name="line_items[${i}][location_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][damage_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][repair_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][material_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][cedex_code]" value="">
                    <input type="hidden" name="line_items[${i}][repair_category_id]" value="">
                    <input type="hidden" name="line_items[${i}][std_labor_hours]" value="${selectedRate.labor_hours}">
                    <input type="hidden" name="line_items[${i}][labor_rate]" value="${(selectedItem.laborRate * fx).toFixed(4)}">
                    <input type="hidden" name="line_items[${i}][labor_amount]" value="${(selectedRate.labor_amount * fx).toFixed(4)}">
                    <input type="hidden" name="line_items[${i}][material_qty]" value="1">
                    <input type="hidden" name="line_items[${i}][material_rate]" value="${(selectedRate.material_cost * fx).toFixed(4)}">
                    <input type="hidden" name="line_items[${i}][material_amount]" value="${(selectedRate.material_cost * fx).toFixed(4)}">
                    <input type="hidden" name="line_items[${i}][ancillary_amount]" value="0">
                    <input type="hidden" name="line_items[${i}][dim_length]"     value="${selectedItem.dimL ?? ''}">
                    <input type="hidden" name="line_items[${i}][dim_width]"      value="${selectedItem.dimW ?? ''}">
                    <input type="hidden" name="line_items[${i}][dim_uom]"        value="${selectedItem.dimUom ?? ''}">
                </td>
                <td>${buildChargeCodeSelect(`line_items[${i}][charge_code_id]`)}</td>
                <td><input type="text" name="line_items[${i}][component]" class="form-control form-control-sm comp-desc" value="${selectedItem.desc}"></td>
                <td><select name="line_items[${i}][repair_type]" class="form-select form-select-sm">
                    <option value="repair" selected>Repair</option>
                    <option value="replace">Replace</option>
                    <option value="weld">Weld</option>
                    <option value="straighten">Straighten</option>
                    <option value="clean_and_treat">Clean &amp; Treat</option>
                    <option value="paint">Paint</option>
                </select></td>
                <td><input type="number" name="line_items[${i}][qty]" class="form-control form-control-sm qty" value="${selectedItem.qty}" min="0.01" step="0.01"></td>
                <td><input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="${(selectedRate.total * fx).toFixed(4)}" min="0" step="0.01"></td>
                <td>${buildTaxCodeSelect(`line_items[${i}][tax_code_id]`)}</td>
                <td class="text-end pe-2 small">
                    <div class="fw-semibold line-net">${currency} 0.00</div>
                    <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                        <span class="line-sscl-amt"></span>
                        <span class="line-vat-amt"></span>
                    </div>
                </td>
                <td class="pe-2">
                    <div class="d-flex flex-column gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-breakdown" title="Cost breakdown"><i class="bi bi-sliders2-vertical"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
            <tr class="bd-row">
                <td colspan="99" class="pt-0 pb-2 ps-4 pe-3">
                    <div class="rounded border bg-light px-3 py-2 bd-panel">
                        <div class="d-flex flex-wrap align-items-end gap-3 small">
                            <div class="d-flex align-items-end gap-2">
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Hrs</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-hrs" value="${selectedRate.labor_hours}" min="0" step="0.25" style="width:65px">
                                </div>
                                <span class="text-muted mb-1" style="font-size:.85rem;">×</span>
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Rate / hr</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-rate" value="${(selectedItem.laborRate * fx).toFixed(4)}" min="0" step="0.01" style="width:75px">
                                </div>
                                <span class="text-muted mb-1" style="font-size:.85rem;">=</span>
                                <div>
                                    <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Labor Amt</div>
                                    <input type="number" class="form-control form-control-sm bd-labor-amt" value="${(selectedRate.labor_amount * fx).toFixed(2)}" min="0" step="0.01" style="width:85px">
                                </div>
                            </div>
                            <div class="vr mx-1"></div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Material Amt</div>
                                <input type="number" class="form-control form-control-sm bd-material-amt" value="${(selectedRate.material_cost * fx).toFixed(2)}" min="0" step="0.01" style="width:90px">
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Ancillary Amt</div>
                                <input type="number" class="form-control form-control-sm bd-ancillary-amt" value="0" min="0" step="0.01" style="width:90px">
                            </div>
                            <div class="ms-auto text-end">
                                <div class="text-muted" style="font-size:.7rem;white-space:nowrap;">Total ÷ Qty → Unit Price</div>
                                <strong class="bd-total text-primary fs-6">${(selectedRate.total * fx).toFixed(2)}</strong>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`);
            initLineSelects(tbody.lastElementChild.previousElementSibling);
            recalculate();
            bootstrap.Modal.getInstance(document.getElementById('getRateModal'))?.hide();
            const addedRow = tbody.lastElementChild.previousElementSibling;
            addedRow.style.backgroundColor = '#d1fae5';
            setTimeout(() => { addedRow.style.backgroundColor = ''; }, 1400);
        });
    })();
})();
</script>
@endpush
