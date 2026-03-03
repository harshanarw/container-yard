@extends('layouts.app')

@section('title', 'Storage Calculator')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item active">Storage Calculator</li>
@endsection

@push('styles')
<style>
    .calc-result-card {
        border-radius: 12px;
        background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        color: #fff;
    }
    .calc-result-card .label { opacity: .75; font-size: .8rem; }
    .rate-table th, .rate-table td { padding: .4rem .75rem; font-size: .82rem; }
    .tier-badge {
        width: 28px; height: 28px;
        border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .7rem; font-weight: 700;
    }
</style>
@endpush

@section('content')

<div class="page-header">
    <h4><i class="bi bi-calculator me-2 text-primary"></i>Storage Calculator</h4>
    <p class="text-muted mb-0 small">Calculate storage charges based on yard tariff and container dwell time</p>
</div>

<div class="row g-3">

    <!-- Calculator Form -->
    <div class="col-lg-5">
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-sliders me-2 text-primary"></i>Calculation Parameters
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Container Number</label>
                    <div class="input-group">
                        <input type="text" id="calcContainerNo" class="form-control font-monospace text-uppercase"
                               placeholder="XXXX0000000">
                        <button class="btn btn-outline-secondary" type="button" id="lookupContainer">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div class="form-text">Or enter parameters manually below</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Customer</label>
                    <select id="calcCustomer" class="form-select select2">
                        <option value="">— Select Customer —</option>
                        <option value="1" data-rate20="25" data-rate40="45" data-rate40hc="50" data-free="7">Maersk Line (Free: 7d)</option>
                        <option value="2" data-rate20="22" data-rate40="42" data-rate40hc="47" data-free="5">CMA CGM (Free: 5d)</option>
                        <option value="3" data-rate20="28" data-rate40="50" data-rate40hc="55" data-free="10">Hapag-Lloyd (Free: 10d)</option>
                        <option value="4" data-rate20="20" data-rate40="38" data-rate40hc="43" data-free="7">PIL Shipping (Free: 7d)</option>
                        <option value="5" data-rate20="25" data-rate40="45" data-rate40hc="50" data-free="5">OOCL (Free: 5d)</option>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Container Size</label>
                        <select id="calcSize" class="form-select">
                            <option value="20">20'</option>
                            <option value="40">40'</option>
                            <option value="40hc">40' HC</option>
                            <option value="45">45'</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Quantity</label>
                        <input type="number" id="calcQty" class="form-control" value="1" min="1">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Gate-In Date <span class="text-danger">*</span></label>
                        <input type="date" id="calcGateIn" class="form-control"
                               value="{{ date('Y-m-d', strtotime('-15 days')) }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Gate-Out / Today</label>
                        <input type="date" id="calcGateOut" class="form-control"
                               value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Daily Rate (MYR)</label>
                        <div class="input-group">
                            <span class="input-group-text">MYR</span>
                            <input type="number" id="calcDailyRate" class="form-control"
                                   value="25.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Free Days</label>
                        <input type="number" id="calcFreeDays" class="form-control" value="7" min="0">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Apply Tariff Tier</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="applyTiers" checked>
                        <label class="form-check-label small" for="applyTiers">
                            Apply progressive tier rates (after free period)
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Tax / SST (%)</label>
                    <input type="number" id="calcTax" class="form-control" value="8" min="0" max="100">
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary" id="calculateBtn">
                        <i class="bi bi-calculator me-2"></i>Calculate Storage Charges
                    </button>
                </div>
            </div>
        </div>

        <!-- Tariff Reference -->
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-table me-2 text-primary"></i>Tariff Rate Reference
            </div>
            <div class="card-body p-0">
                <table class="table rate-table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tier</th>
                            <th>Period</th>
                            <th>20' (MYR/day)</th>
                            <th>40' (MYR/day)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="tier-badge bg-success-subtle text-success">1</span></td>
                            <td>Days 1–7 (Free)</td>
                            <td class="text-success fw-semibold">0.00</td>
                            <td class="text-success fw-semibold">0.00</td>
                        </tr>
                        <tr>
                            <td><span class="tier-badge bg-primary-subtle text-primary">2</span></td>
                            <td>Days 8–14</td>
                            <td>25.00</td>
                            <td>45.00</td>
                        </tr>
                        <tr>
                            <td><span class="tier-badge bg-warning-subtle text-warning">3</span></td>
                            <td>Days 15–21</td>
                            <td>35.00</td>
                            <td>62.00</td>
                        </tr>
                        <tr>
                            <td><span class="tier-badge bg-danger-subtle text-danger">4</span></td>
                            <td>Day 22+</td>
                            <td>50.00</td>
                            <td>90.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="col-lg-7">

        <!-- Summary Result Card -->
        <div class="calc-result-card p-4 mb-3">
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="label">Total Days</div>
                    <div class="fs-2 fw-bold" id="resTotalDays">15</div>
                </div>
                <div class="col-4">
                    <div class="label">Chargeable Days</div>
                    <div class="fs-2 fw-bold" id="resChargeDays">8</div>
                </div>
                <div class="col-4">
                    <div class="label">Free Days Used</div>
                    <div class="fs-2 fw-bold" id="resFreeDays">7</div>
                </div>
                <div class="col-12 border-top border-white border-opacity-25 pt-3">
                    <div class="label">Total Storage Charge (incl. tax)</div>
                    <div class="display-5 fw-bold" id="resTotal">MYR 216.00</div>
                </div>
            </div>
        </div>

        <!-- Breakdown Table -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Charge Breakdown</span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <button class="btn btn-outline-primary" id="generateInvoice">
                        <i class="bi bi-receipt me-1"></i>Generate Invoice
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm rate-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Period</th>
                            <th>Days</th>
                            <th>Rate/Day</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Tier</th>
                        </tr>
                    </thead>
                    <tbody id="breakdownBody">
                        <tr>
                            <td class="ps-3 text-muted">Day 1 – 7</td>
                            <td>7</td>
                            <td class="text-success">MYR 0.00</td>
                            <td>1</td>
                            <td class="fw-semibold text-success">MYR 0.00</td>
                            <td><span class="tier-badge bg-success-subtle text-success" style="font-size:.6rem;">FREE</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Day 8 – 14</td>
                            <td>7</td>
                            <td>MYR 25.00</td>
                            <td>1</td>
                            <td class="fw-semibold">MYR 175.00</td>
                            <td><span class="tier-badge bg-primary-subtle text-primary" style="font-size:.6rem;">T2</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Day 15 (partial)</td>
                            <td>1</td>
                            <td>MYR 25.00</td>
                            <td>1</td>
                            <td class="fw-semibold">MYR 25.00</td>
                            <td><span class="tier-badge bg-primary-subtle text-primary" style="font-size:.6rem;">T2</span></td>
                        </tr>
                        <tr class="table-light">
                            <td class="ps-3 fw-semibold" colspan="4">Subtotal</td>
                            <td class="fw-semibold" id="resSubtotal">MYR 200.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted" colspan="4">SST (8%)</td>
                            <td id="resTaxAmt">MYR 16.00</td>
                            <td></td>
                        </tr>
                        <tr class="table-primary">
                            <td class="ps-3 fw-bold" colspan="4">GRAND TOTAL</td>
                            <td class="fw-bold" id="resGrandTotal">MYR 216.00</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bulk Calculator -->
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-boxes me-2 text-primary"></i>Bulk Container Storage Summary
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="bulkTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Container No.</th>
                                <th>Size</th>
                                <th>Gate-In</th>
                                <th>Days</th>
                                <th>Free Days</th>
                                <th>Chargeable</th>
                                <th class="text-end pe-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $bulkContainers = [
                                ['MSCU1234567','20\'','15 Feb 2026',13,7,6,'MYR 150.00'],
                                ['CMAU9876543','40\' HC','10 Feb 2026',18,7,11,'MYR 495.00'],
                                ['TGHU5551234','20\'','20 Feb 2026',8,7,1,'MYR 25.00'],
                                ['HLXU3334455','40\'','05 Feb 2026',23,7,16,'MYR 860.00'],
                                ['OOLU7778899','20\'','22 Feb 2026',6,7,0,'MYR 0.00'],
                            ];
                            @endphp
                            @foreach($bulkContainers as $bc)
                            <tr>
                                <td class="ps-3 font-monospace small">{{ $bc[0] }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $bc[1] }}</span></td>
                                <td class="small text-muted">{{ $bc[2] }}</td>
                                <td class="text-center"><span class="badge bg-light border text-dark">{{ $bc[3] }}d</span></td>
                                <td class="text-center text-success small">{{ $bc[4] }}d</td>
                                <td class="text-center small">{{ $bc[5] }}d</td>
                                <td class="text-end pe-3 fw-semibold {{ $bc[5]==0 ? 'text-success' : '' }}">{{ $bc[6] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td class="ps-3 fw-bold" colspan="6">Total</td>
                                <td class="text-end pe-3 fw-bold text-primary">MYR 1,530.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('calculateBtn').addEventListener('click', calculate);

    // Auto-fill rates when customer is selected
    document.getElementById('calcCustomer').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const size = document.getElementById('calcSize').value;
        const rates = { '20': opt.dataset.rate20, '40': opt.dataset.rate40, '40hc': opt.dataset.rate40hc, '45': opt.dataset.rate40hc };
        if (rates[size]) document.getElementById('calcDailyRate').value = rates[size];
        if (opt.dataset.free) document.getElementById('calcFreeDays').value = opt.dataset.free;
    });

    function calculate() {
        const gateIn   = new Date(document.getElementById('calcGateIn').value);
        const gateOut  = new Date(document.getElementById('calcGateOut').value);
        const freeDays = parseInt(document.getElementById('calcFreeDays').value) || 0;
        const dailyRate = parseFloat(document.getElementById('calcDailyRate').value) || 0;
        const qty      = parseInt(document.getElementById('calcQty').value) || 1;
        const taxPct   = parseFloat(document.getElementById('calcTax').value) || 0;

        if (!gateIn || !gateOut || gateOut < gateIn) {
            alert('Please enter valid gate-in and gate-out dates.');
            return;
        }

        const diffMs   = gateOut - gateIn;
        const totalDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24)) + 1;
        const chargeableDays = Math.max(0, totalDays - freeDays);
        const usedFreeDays   = Math.min(totalDays, freeDays);

        const subtotal = chargeableDays * dailyRate * qty;
        const taxAmt   = subtotal * (taxPct / 100);
        const grand    = subtotal + taxAmt;

        document.getElementById('resTotalDays').textContent  = totalDays;
        document.getElementById('resChargeDays').textContent = chargeableDays;
        document.getElementById('resFreeDays').textContent   = usedFreeDays;
        document.getElementById('resTotal').textContent      = 'MYR ' + grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
        document.getElementById('resSubtotal').textContent   = 'MYR ' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
        document.getElementById('resTaxAmt').textContent     = 'MYR ' + taxAmt.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
        document.getElementById('resGrandTotal').textContent = 'MYR ' + grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
    }

    // Run on load with defaults
    calculate();
</script>
@endpush
