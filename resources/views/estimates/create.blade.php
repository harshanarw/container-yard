@extends('layouts.app')

@section('title', 'Repair Estimate')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item active">Create Estimate</li>
@endsection

@push('styles')
<style>
    .estimate-line:hover { background: #f8f9fa; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>Repair Estimate</h4>
        <p class="text-muted mb-0 small">Generate and send repair cost estimate to customer</p>
    </div>
    <div class="text-muted small">
        Ref: <strong>RE-{{ str_pad(rand(1,999), 4, '0', STR_PAD_LEFT) }}</strong>
        &nbsp;·&nbsp; {{ now()->format('d M Y') }}
    </div>
</div>

<form method="POST" action="{{ route('estimates.store') }}" id="estimateForm">
    @csrf

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
                            <input type="text" name="container_no" class="form-control font-monospace"
                                   value="{{ $inquiry->container_no ?? 'MSCU7890123' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Size / Type</label>
                            <input type="text" class="form-control"
                                   value="{{ $inquiry->size ?? '20\' GP' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Inquiry Ref.</label>
                            <input type="text" name="inquiry_id" class="form-control"
                                   value="{{ $inquiry->inquiry_no ?? 'INQ-0091' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <input type="text" class="form-control"
                                   value="{{ $inquiry->customer->name ?? 'Maersk Line' }}" readonly>
                            <input type="hidden" name="customer_id" value="{{ $inquiry->customer_id ?? 1 }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estimate Date</label>
                            <input type="date" name="estimate_date" class="form-control"
                                   value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Valid Until</label>
                            <input type="date" name="valid_until" class="form-control"
                                   value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="MYR">MYR — Malaysian Ringgit</option>
                                <option value="USD">USD — US Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Repair Priority</label>
                            <select name="priority" class="form-select">
                                <option>Normal (7-14 days)</option>
                                <option>Urgent (3-5 days)</option>
                                <option>Critical (Next day)</option>
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
                                    <th style="width:12%">Qty</th>
                                    <th style="width:15%">Unit Price</th>
                                    <th style="width:8%">Tax %</th>
                                    <th style="width:13%">Amount</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItems">
                                <!-- Line 1 -->
                                <tr class="estimate-line">
                                    <td class="ps-3">
                                        <input type="text" name="lines[0][component]" class="form-control form-control-sm"
                                               placeholder="e.g. Floor Panel" value="Floor Panel">
                                    </td>
                                    <td>
                                        <select name="lines[0][repair_type]" class="form-select form-select-sm">
                                            <option>Replace</option>
                                            <option>Repair</option>
                                            <option>Weld</option>
                                            <option>Straighten</option>
                                            <option>Clean &amp; Treat</option>
                                            <option>Paint</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="lines[0][qty]" class="form-control form-control-sm qty"
                                               value="2" min="1" step="0.5">
                                    </td>
                                    <td>
                                        <input type="number" name="lines[0][unit_price]" class="form-control form-control-sm unit-price"
                                               value="350.00" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="lines[0][tax]" class="form-control form-control-sm tax-pct"
                                               value="8" min="0" max="100">
                                    </td>
                                    <td class="fw-semibold line-amount text-end pe-2">MYR 756.00</td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- Line 2 -->
                                <tr class="estimate-line">
                                    <td class="ps-3">
                                        <input type="text" name="lines[1][component]" class="form-control form-control-sm"
                                               placeholder="e.g. Door Seal" value="Door Seal Gasket">
                                    </td>
                                    <td>
                                        <select name="lines[1][repair_type]" class="form-select form-select-sm">
                                            <option>Replace</option><option>Repair</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="lines[1][qty]" class="form-control form-control-sm qty"
                                               value="1" min="1">
                                    </td>
                                    <td>
                                        <input type="number" name="lines[1][unit_price]" class="form-control form-control-sm unit-price"
                                               value="180.00" step="0.01">
                                    </td>
                                    <td>
                                        <input type="number" name="lines[1][tax]" class="form-control form-control-sm tax-pct"
                                               value="8">
                                    </td>
                                    <td class="fw-semibold line-amount text-end pe-2">MYR 194.40</td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <!-- Totals -->
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end fw-semibold pe-3">Subtotal:</td>
                                    <td class="fw-semibold text-end pe-2" id="subtotal">MYR 980.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end fw-semibold pe-3">Tax (SST):</td>
                                    <td class="fw-semibold text-end pe-2" id="totalTax">MYR 78.40</td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="5" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
                                    <td class="fw-bold text-end pe-2 fs-6" id="grandTotal">MYR 1,058.40</td>
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
                                  placeholder="Describe the detailed scope of repair work…">Replace 2 damaged floor panels (Section 3 and 4). Replace door seal gasket on both doors. Surface treatment and anti-rust paint to affected areas.</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Terms &amp; Conditions</label>
                        <textarea name="terms" class="form-control" rows="3">1. This estimate is valid for 30 days from the date of issue.
2. Prices are subject to change based on actual damage found during repair.
3. Additional damages discovered during repair will be notified and re-estimated.
4. Payment is due within 30 days of invoice.</textarea>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">

            <!-- Damage Summary from Inquiry -->
            <div class="card content-card mb-3">
                <div class="card-header bg-warning-subtle">
                    <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Damage Summary (INQ-0091)
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong>Floor</strong> — Dent/Damage</span>
                            <span class="badge bg-danger-subtle text-danger">Severe</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong>Door Seal</strong> — Deterioration</span>
                            <span class="badge bg-warning-subtle text-warning">Moderate</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong>Right Wall</strong> — Minor Dent</span>
                            <span class="badge bg-success-subtle text-success">Minor</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Send Options -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-send me-2 text-primary"></i>Send Options
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Send To</label>
                        <input type="email" name="send_to" class="form-control form-control-sm"
                               value="ops@maersk.com" placeholder="customer@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC</label>
                        <input type="email" name="send_cc" class="form-control form-control-sm"
                               placeholder="manager@email.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Brief message to the customer…">Please find attached the repair estimate for container MSCU7890123. Kindly review and revert with your approval at your earliest convenience.</textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_pdf" id="attachPdf" checked>
                        <label class="form-check-label small" for="attachPdf">Attach PDF estimate</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_photos" id="attachPhotos">
                        <label class="form-check-label small" for="attachPhotos">Attach inspection photos</label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" name="action" value="send" class="btn btn-primary">
                    <i class="bi bi-send me-2"></i>Save & Send to Customer
                </button>
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                    <i class="bi bi-save me-2"></i>Save Draft
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Print / PDF Preview
                </button>
                <a href="{{ route('estimates.index') }}" class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
    let lineIdx = 2;

    function recalculate() {
        let subtotal = 0, taxTotal = 0;
        document.querySelectorAll('.estimate-line').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty')?.value  || 0);
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const tax   = parseFloat(row.querySelector('.tax-pct')?.value  || 0);
            const net   = qty * price;
            const taxAmt = net * (tax / 100);
            subtotal  += net;
            taxTotal  += taxAmt;
            const amtEl = row.querySelector('.line-amount');
            if (amtEl) amtEl.textContent = 'MYR ' + (net + taxAmt).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
        const grand = subtotal + taxTotal;
        document.getElementById('subtotal').textContent  = 'MYR ' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('totalTax').textContent  = 'MYR ' + taxTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('grandTotal').textContent = 'MYR ' + grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    document.getElementById('lineTable').addEventListener('input', recalculate);

    document.getElementById('addLine').addEventListener('click', function () {
        const tbody = document.getElementById('lineItems');
        const i = lineIdx++;
        tbody.insertAdjacentHTML('beforeend', `
            <tr class="estimate-line">
                <td class="ps-3"><input type="text" name="lines[${i}][component]" class="form-control form-control-sm" placeholder="Component"></td>
                <td>
                    <select name="lines[${i}][repair_type]" class="form-select form-select-sm">
                        <option>Replace</option><option>Repair</option><option>Weld</option>
                        <option>Straighten</option><option>Clean &amp; Treat</option><option>Paint</option>
                    </select>
                </td>
                <td><input type="number" name="lines[${i}][qty]" class="form-control form-control-sm qty" value="1" min="1"></td>
                <td><input type="number" name="lines[${i}][unit_price]" class="form-control form-control-sm unit-price" value="0.00" step="0.01"></td>
                <td><input type="number" name="lines[${i}][tax]" class="form-control form-control-sm tax-pct" value="8"></td>
                <td class="fw-semibold line-amount text-end pe-2">MYR 0.00</td>
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
