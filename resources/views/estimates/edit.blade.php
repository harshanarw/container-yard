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
                            <label class="form-label fw-semibold">Size / Type</label>
                            <input type="text" class="form-control"
                                   value="{{ $estimate->size }}' {{ $estimate->type_code }}" readonly>
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
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estimate Date <span class="text-danger">*</span></label>
                            <input type="date" name="estimate_date" class="form-control"
                                   value="{{ old('estimate_date', $estimate->estimate_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" name="valid_until" class="form-control"
                                   value="{{ old('valid_until', $estimate->valid_until->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <select name="currency" class="form-select" required>
                                <option value="LKR" {{ old('currency', $estimate->currency) === 'LKR' ? 'selected' : '' }}>LKR — Sri Lankan Rupee</option>
                                <option value="USD" {{ old('currency', $estimate->currency) === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                                <option value="SGD" {{ old('currency', $estimate->currency) === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
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
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLine">
                        <i class="bi bi-plus-circle me-1"></i>Add Line
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="lineTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:25%">Component / Location</th>
                                    <th style="width:22%">Repair Type</th>
                                    <th style="width:10%">Qty</th>
                                    <th style="width:15%">Unit Price</th>
                                    <th style="width:8%">Tax %</th>
                                    <th style="width:15%">Amount</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItems">
                                @foreach($estimate->lineItems as $i => $item)
                                <tr class="estimate-line">
                                    <td class="ps-3">
                                        <input type="hidden" name="line_items[{{ $i }}][id]" value="{{ $item->id }}">
                                        <input type="text" name="line_items[{{ $i }}][component]"
                                               class="form-control form-control-sm"
                                               value="{{ old("line_items.{$i}.component", $item->component) }}"
                                               placeholder="e.g. Floor Panel" required>
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
                                               min="0.01" step="0.5" required>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][unit_price]"
                                               class="form-control form-control-sm unit-price"
                                               value="{{ old("line_items.{$i}.unit_price", $item->unit_price) }}"
                                               step="0.01" min="0" required>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][tax_percentage]"
                                               class="form-control form-control-sm tax-pct"
                                               value="{{ old("line_items.{$i}.tax_percentage", $item->tax_percentage) }}"
                                               min="0" max="100">
                                    </td>
                                    <td class="fw-semibold line-amount text-end pe-2">
                                        {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                                    </td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end fw-semibold pe-3">Subtotal:</td>
                                    <td class="fw-semibold text-end pe-2" id="subtotal">
                                        {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-semibold pe-3">
                                        Tax (<input type="number" name="tax_percentage" id="taxPct"
                                                    class="form-control form-control-sm d-inline-block text-center"
                                                    style="width:60px"
                                                    value="{{ old('tax_percentage', $estimate->tax_percentage) }}"
                                                    min="0" max="100" step="0.01">%):
                                    </td>
                                    <td class="fw-semibold text-end pe-2" id="totalTax">
                                        {{ $estimate->currency }} {{ number_format($estimate->tax_amount, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="5" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
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
                        <input type="email" name="send_to_email" class="form-control form-control-sm"
                               value="{{ old('send_to_email', $estimate->send_to_email) }}"
                               placeholder="customer@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC</label>
                        <input type="email" name="send_cc_email" class="form-control form-control-sm"
                               value="{{ old('send_cc_email', $estimate->send_cc_email) }}"
                               placeholder="manager@email.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Brief message to the customer…">{{ old('email_message', $estimate->email_message) }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="attach_pdf" id="attachPdf"
                               {{ old('attach_pdf', $estimate->attach_pdf) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="attachPdf">Attach PDF estimate</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_photos" id="attachPhotos"
                               {{ old('attach_photos', $estimate->attach_photos) ? 'checked' : '' }}>
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

@endsection

@push('scripts')
<script>
    let lineIdx = {{ $estimate->lineItems->count() }};
    const currency = '{{ $estimate->currency }}';

    function fmt(n) {
        return currency + ' ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function recalculate() {
        let subtotal = 0;
        document.querySelectorAll('.estimate-line').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty')?.value  || 0);
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const net   = qty * price;
            subtotal   += net;
            const amtEl = row.querySelector('.line-amount');
            if (amtEl) amtEl.textContent = fmt(net);
        });
        const taxPct = parseFloat(document.getElementById('taxPct').value || 0);
        const tax    = subtotal * taxPct / 100;
        document.getElementById('subtotal').textContent  = fmt(subtotal);
        document.getElementById('totalTax').textContent  = fmt(tax);
        document.getElementById('grandTotal').textContent = fmt(subtotal + tax);
    }

    document.getElementById('lineTable').addEventListener('input', recalculate);

    document.getElementById('addLine').addEventListener('click', function () {
        const tbody = document.getElementById('lineItems');
        const i = lineIdx++;
        tbody.insertAdjacentHTML('beforeend', `
            <tr class="estimate-line">
                <td class="ps-3">
                    <input type="text" name="line_items[${i}][component]" class="form-control form-control-sm" placeholder="Component" required>
                </td>
                <td>
                    <select name="line_items[${i}][repair_type]" class="form-select form-select-sm" required>
                        <option value="replace">Replace</option>
                        <option value="repair">Repair</option>
                        <option value="weld">Weld</option>
                        <option value="straighten">Straighten</option>
                        <option value="clean_and_treat">Clean &amp; Treat</option>
                        <option value="paint">Paint</option>
                    </select>
                </td>
                <td><input type="number" name="line_items[${i}][qty]" class="form-control form-control-sm qty" value="1" min="0.01" step="0.5" required></td>
                <td><input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="0.00" step="0.01" min="0" required></td>
                <td><input type="number" name="line_items[${i}][tax_percentage]" class="form-control form-control-sm tax-pct" value="0" min="0" max="100"></td>
                <td class="fw-semibold line-amount text-end pe-2">${currency} 0.00</td>
                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
            </tr>
        `);
    });

    document.getElementById('lineItems').addEventListener('click', function (e) {
        if (e.target.closest('.remove-line')) {
            if (document.querySelectorAll('.estimate-line').length > 1) {
                e.target.closest('.estimate-line').remove();
                recalculate();
            }
        }
    });
</script>
@endpush
