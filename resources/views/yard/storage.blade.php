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
                    <select id="calcCustomer" class="form-select">
                        <option value="">— Select Customer —</option>
                        @foreach($customers as $cust)
                            <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                        @endforeach
                    </select>
                    <div id="tariffStatus" class="form-text"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Equipment Type</label>
                    <select id="calcEquipmentType" class="form-select">
                        <option value="">— Select Equipment Type —</option>
                        @foreach($equipmentTypes as $eqt)
                            <option value="{{ $eqt->id }}"
                                    data-code="{{ $eqt->eqt_code }}"
                                    data-description="{{ $eqt->description }}">
                                {{ $eqt->eqt_code }} — {{ $eqt->description }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Quantity</label>
                        <input type="number" id="calcQty" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tax / SST (%)</label>
                        <input type="number" id="calcTax" class="form-control" value="8" min="0" max="100">
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
                        <label class="form-label fw-semibold">Daily Rate</label>
                        <div class="input-group">
                            <span class="input-group-text" id="rateCurrencyLabel">LKR</span>
                            <input type="number" id="calcDailyRate" class="form-control"
                                   value="0.00" step="0.01" placeholder="Auto-filled from tariff">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Free Days</label>
                        <input type="number" id="calcFreeDays" class="form-control" value="0" min="0"
                               placeholder="Auto-filled from tariff">
                    </div>
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary" id="calculateBtn">
                        <i class="bi bi-calculator me-2"></i>Calculate Storage Charges
                    </button>
                </div>
            </div>
        </div>

        <!-- Tariff Reference -->
        <div class="card content-card" id="tariffRefCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Tariff Rate Reference</span>
                <span id="tariffValidityBadge" class="badge bg-secondary-subtle text-secondary small"></span>
            </div>
            <div class="card-body p-0">
                <div id="tariffNoData" class="text-center text-muted py-3 small fst-italic">
                    Select a customer to view their active tariff rates.
                </div>
                <table class="table rate-table table-sm mb-0 d-none" id="tariffRefTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Equipment Type</th>
                            <th>ISO Code</th>
                            <th>Rate / Day</th>
                            <th>Currency</th>
                        </tr>
                    </thead>
                    <tbody id="tariffRefBody"></tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="ps-3 text-muted small" colspan="4">
                                Free Days: <strong id="tariffFreeDaysLabel">—</strong>
                            </td>
                        </tr>
                    </tfoot>
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
                    <div class="fs-2 fw-bold" id="resTotalDays">—</div>
                </div>
                <div class="col-4">
                    <div class="label">Chargeable Days</div>
                    <div class="fs-2 fw-bold" id="resChargeDays">—</div>
                </div>
                <div class="col-4">
                    <div class="label">Free Days Used</div>
                    <div class="fs-2 fw-bold" id="resFreeDays">—</div>
                </div>
                <div class="col-12 border-top border-white border-opacity-25 pt-3">
                    <div class="label">Total Storage Charge (incl. tax)</div>
                    <div class="display-5 fw-bold" id="resTotal">—</div>
                </div>
            </div>
        </div>

        <!-- Breakdown Table -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Charge Breakdown</span>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
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
                        </tr>
                    </thead>
                    <tbody id="breakdownBody">
                        <tr>
                            <td class="ps-3 text-muted" colspan="5">
                                Select parameters and click Calculate to see the breakdown.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Storage Records -->
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-boxes me-2 text-primary"></i>Active Storage Records</span>
                <a href="{{ route('yard.storage') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </a>
            </div>
            <div class="card-body p-0">
                @php
                    $activeRecords = $storageRecords->getCollection()->filter(fn ($r) => is_null($r->gate_out_date));
                @endphp
                @if($activeRecords->isEmpty())
                    <div class="text-center text-muted py-3 small fst-italic">No active storage records.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Container No.</th>
                                <th>Customer</th>
                                <th>Gate-In</th>
                                <th>Days</th>
                                <th>Free</th>
                                <th>Chargeable</th>
                                <th class="text-end pe-3">Est. Charge</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeRecords as $rec)
                            @php
                                $days       = max(1, $rec->gate_in_date->diffInDays(today()));
                                $chargeable = max(0, $days - $rec->free_days);
                                $estCharge  = $chargeable * $rec->daily_rate;
                            @endphp
                            <tr>
                                <td class="ps-3 font-monospace small">{{ $rec->container->container_no ?? '—' }}</td>
                                <td class="small">{{ $rec->customer->name ?? '—' }}</td>
                                <td class="small text-muted">{{ $rec->gate_in_date->format('d M Y') }}</td>
                                <td class="text-center"><span class="badge bg-light border text-dark">{{ $days }}d</span></td>
                                <td class="text-center text-success small">{{ $rec->free_days }}d</td>
                                <td class="text-center small {{ $chargeable > 0 ? 'text-danger' : 'text-success' }}">{{ $chargeable }}d</td>
                                <td class="text-end pe-3 fw-semibold {{ $chargeable == 0 ? 'text-success' : '' }}">
                                    {{ number_format($estCharge, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td class="ps-3 fw-bold" colspan="6">Total Est. Charge</td>
                                <td class="text-end pe-3 fw-bold text-primary">
                                    {{ number_format($activeRecords->sum(fn ($r) => max(0, max(1, $r->gate_in_date->diffInDays(today())) - $r->free_days) * $r->daily_rate), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── State ──────────────────────────────────────────────────────────────────
    let activeTariff = null;   // { found, free_days, valid_from, valid_to, rates[] }

    const fmt = (n, cur = 'LKR') =>
        cur + '\u00a0' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    // ── Tariff Fetch ───────────────────────────────────────────────────────────
    function fetchTariff(customerId) {
        if (!customerId) {
            activeTariff = null;
            renderTariffRef();
            clearRateFields();
            return;
        }

        fetch(`{{ url('/yard/tariff') }}/${customerId}`)
            .then(r => r.json())
            .then(data => {
                activeTariff = data;
                renderTariffRef();
                applyRateForSelectedEquipment();
                if (!data.found) {
                    document.getElementById('tariffStatus').innerHTML =
                        '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No active storage tariff found for this customer.</span>';
                } else {
                    document.getElementById('tariffStatus').innerHTML =
                        '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Tariff loaded. Free days & rates auto-filled.</span>';
                }
            })
            .catch(() => {
                activeTariff = null;
                document.getElementById('tariffStatus').innerHTML =
                    '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Failed to load tariff.</span>';
            });
    }

    function renderTariffRef() {
        const noData  = document.getElementById('tariffNoData');
        const table   = document.getElementById('tariffRefTable');
        const tbody   = document.getElementById('tariffRefBody');
        const badge   = document.getElementById('tariffValidityBadge');
        const freeLabel = document.getElementById('tariffFreeDaysLabel');

        if (!activeTariff || !activeTariff.found) {
            noData.classList.remove('d-none');
            table.classList.add('d-none');
            badge.textContent = '';
            return;
        }

        noData.classList.add('d-none');
        table.classList.remove('d-none');

        const to = activeTariff.valid_to ?? 'Open-ended';
        badge.textContent = activeTariff.valid_from + ' — ' + to;
        freeLabel.textContent = activeTariff.free_days + ' days';

        tbody.innerHTML = activeTariff.rates.map(r => `
            <tr>
                <td class="ps-3">${r.description ?? '—'}</td>
                <td><span class="badge bg-secondary-subtle text-secondary">${r.eqt_code ?? '—'}</span></td>
                <td class="fw-semibold">${r.storage_rate.toFixed(2)}</td>
                <td class="text-muted small">${r.currency}</td>
            </tr>
        `).join('');
    }

    function applyRateForSelectedEquipment() {
        if (!activeTariff || !activeTariff.found) return;

        document.getElementById('calcFreeDays').value = activeTariff.free_days;

        const eqtId = parseInt(document.getElementById('calcEquipmentType').value);
        if (!eqtId) {
            document.getElementById('calcDailyRate').value = '0.00';
            return;
        }

        const match = activeTariff.rates.find(r => r.equipment_type_id === eqtId);
        if (match) {
            document.getElementById('calcDailyRate').value     = match.storage_rate.toFixed(2);
            document.getElementById('rateCurrencyLabel').textContent = match.currency;
        } else {
            document.getElementById('calcDailyRate').value = '0.00';
            document.getElementById('tariffStatus').innerHTML =
                '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No rate defined for this equipment type in the active tariff.</span>';
        }
    }

    function clearRateFields() {
        document.getElementById('calcFreeDays').value  = '0';
        document.getElementById('calcDailyRate').value = '0.00';
        document.getElementById('tariffStatus').innerHTML = '';
    }

    // ── Event listeners ────────────────────────────────────────────────────────
    document.getElementById('calcCustomer').addEventListener('change', function () {
        fetchTariff(this.value);
    });

    document.getElementById('calcEquipmentType').addEventListener('change', function () {
        applyRateForSelectedEquipment();
    });

    // ── Container Lookup ───────────────────────────────────────────────────────
    document.getElementById('lookupContainer').addEventListener('click', function () {
        const no = document.getElementById('calcContainerNo').value.trim().toUpperCase();
        if (!no) return;

        fetch(`{{ route('yard.container.lookup', ':no') }}`.replace(':no', no))
            .then(r => r.json())
            .then(data => {
                if (!data.found) {
                    alert('Container not found.');
                    return;
                }

                // Fill customer
                const custSel = document.getElementById('calcCustomer');
                for (let i = 0; i < custSel.options.length; i++) {
                    if (parseInt(custSel.options[i].value) === data.customer_id) {
                        custSel.selectedIndex = i;
                        break;
                    }
                }
                fetchTariff(data.customer_id);

                // Fill equipment type
                const eqtSel = document.getElementById('calcEquipmentType');
                for (let i = 0; i < eqtSel.options.length; i++) {
                    if (parseInt(eqtSel.options[i].value) === data.equipment_type_id) {
                        eqtSel.selectedIndex = i;
                        break;
                    }
                }

                // Fill gate-in date
                if (data.gate_in_date) {
                    document.getElementById('calcGateIn').value = data.gate_in_date;
                }
            })
            .catch(() => alert('Lookup failed.'));
    });

    // ── Calculate ──────────────────────────────────────────────────────────────
    document.getElementById('calculateBtn').addEventListener('click', calculate);

    function calculate() {
        const gateIn    = new Date(document.getElementById('calcGateIn').value);
        const gateOut   = new Date(document.getElementById('calcGateOut').value);
        const freeDays  = parseInt(document.getElementById('calcFreeDays').value)  || 0;
        const dailyRate = parseFloat(document.getElementById('calcDailyRate').value) || 0;
        const qty       = parseInt(document.getElementById('calcQty').value)  || 1;
        const taxPct    = parseFloat(document.getElementById('calcTax').value) || 0;
        const currency  = document.getElementById('rateCurrencyLabel').textContent || 'LKR';

        if (!document.getElementById('calcGateIn').value || !document.getElementById('calcGateOut').value) {
            alert('Please enter gate-in and gate-out dates.');
            return;
        }
        if (gateOut < gateIn) {
            alert('Gate-out date must be on or after gate-in date.');
            return;
        }

        const diffMs         = gateOut - gateIn;
        const totalDays      = Math.ceil(diffMs / (1000 * 60 * 60 * 24)) + 1;
        const usedFreeDays   = Math.min(totalDays, freeDays);
        const chargeableDays = Math.max(0, totalDays - freeDays);
        const subtotal       = chargeableDays * dailyRate * qty;
        const taxAmt         = subtotal * (taxPct / 100);
        const grand          = subtotal + taxAmt;

        // Summary card
        document.getElementById('resTotalDays').textContent  = totalDays;
        document.getElementById('resChargeDays').textContent = chargeableDays;
        document.getElementById('resFreeDays').textContent   = usedFreeDays;
        document.getElementById('resTotal').textContent      = fmt(grand, currency);

        // Breakdown
        const tbody = document.getElementById('breakdownBody');
        let rows = '';

        if (usedFreeDays > 0) {
            rows += `<tr>
                <td class="ps-3 text-muted">Day 1 – ${usedFreeDays} (Free Period)</td>
                <td>${usedFreeDays}</td>
                <td class="text-success">${fmt(0, currency)}</td>
                <td>${qty}</td>
                <td class="fw-semibold text-success">${fmt(0, currency)}</td>
            </tr>`;
        }

        if (chargeableDays > 0) {
            const chargeStart = usedFreeDays + 1;
            const chargeEnd   = totalDays;
            rows += `<tr>
                <td class="ps-3 text-muted">Day ${chargeStart} – ${chargeEnd} (Chargeable)</td>
                <td>${chargeableDays}</td>
                <td>${fmt(dailyRate, currency)}</td>
                <td>${qty}</td>
                <td class="fw-semibold">${fmt(subtotal, currency)}</td>
            </tr>`;
        }

        rows += `<tr class="table-light">
            <td class="ps-3 fw-semibold" colspan="4">Subtotal</td>
            <td class="fw-semibold">${fmt(subtotal, currency)}</td>
        </tr>
        <tr>
            <td class="ps-3 text-muted" colspan="4">Tax (${taxPct}%)</td>
            <td>${fmt(taxAmt, currency)}</td>
        </tr>
        <tr class="table-primary">
            <td class="ps-3 fw-bold" colspan="4">GRAND TOTAL</td>
            <td class="fw-bold">${fmt(grand, currency)}</td>
        </tr>`;

        tbody.innerHTML = rows;
    }
</script>
@endpush
