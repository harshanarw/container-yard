@extends('layouts.app')

@section('title', 'Gate In / Gate Out')

@section('breadcrumb')
    <li class="breadcrumb-item active">Gate In / Gate Out</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Gate In / Gate Out</h4>
        <p class="text-muted mb-0 small">Record container arrivals and departures from the yard</p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-clock me-1"></i>{{ now()->format('d M Y, H:i') }}
    </div>
</div>

<!-- Quick Toggle: Gate In / Gate Out -->
<div class="d-flex gap-3 mb-4">
    <button class="btn btn-primary px-4" id="btnGateIn">
        <i class="bi bi-box-arrow-in-right me-2"></i>Gate In
    </button>
    <button class="btn btn-outline-success px-4" id="btnGateOut">
        <i class="bi bi-box-arrow-right me-2"></i>Gate Out
    </button>
</div>

<div class="row g-3">

    <!-- Gate In / Out Form -->
    <div class="col-lg-6">
        <div class="card content-card" id="gateInCard">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-box-arrow-in-right me-2"></i>Gate In — Container Arrival
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.in') }}" id="gateInForm">
                    @csrf
                    <input type="hidden" name="movement_type" value="in">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" id="containerNoIn"
                                   class="form-control font-monospace text-uppercase"
                                   placeholder="XXXX0000000" required autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="scanBtn" title="Scan">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="gateEqtSelect" class="form-select" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}"
                                            data-size="{{ $eqt->size }}"
                                            data-type="{{ $eqt->type_code }}"
                                            data-eqt="{{ $eqt->eqt_code }}">
                                        {{ $eqt->eqt_code }} — {{ $eqt->description }}
                                    </option>
                                    @endforeach
                                </select>
                                <span id="gateEqtSizeBadge" class="badge bg-light border text-dark text-nowrap d-none"></span>
                                <span id="gateEqtTypeBadge" class="badge bg-info-subtle text-info text-nowrap d-none"></span>
                            </div>
                            <input type="hidden" name="size" id="gateEqtSize">
                            <input type="hidden" name="type_code" id="gateEqtTypeCode">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound">Sound</option>
                                <option value="damaged">Damaged</option>
                                <option value="require_repair">Requires Repair</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty / Full</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty">Empty</option>
                                <option value="full">Full</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Yard Location</label>
                            <div class="input-group">
                                <select name="location_row" class="form-select form-select-sm" required>
                                    <option value="">Row</option>
                                    @foreach($emptySlots->pluck('row')->unique() as $row)
                                    <option value="{{ $row }}">{{ $row }}</option>
                                    @endforeach
                                </select>
                                <select name="location_bay" class="form-select form-select-sm" required>
                                    <option value="">Bay</option>
                                    @for($b = 1; $b <= 8; $b++)
                                    <option value="{{ $b }}">{{ $b }}</option>
                                    @endfor
                                </select>
                                <select name="location_tier" class="form-select form-select-sm" required>
                                    <option value="">Tier</option>
                                    @for($t = 1; $t <= 5; $t++)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   placeholder="e.g. WQR 1234">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"
                                      placeholder="Any remarks about this container…"></textarea>
                        </div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Record Gate In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gate Out (hidden by default) -->
        <div class="card content-card d-none" id="gateOutCard">
            <div class="card-header bg-success text-white">
                <i class="bi bi-box-arrow-right me-2"></i>Gate Out — Container Departure
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.out') }}" id="gateOutForm">
                    @csrf
                    <input type="hidden" name="movement_type" value="out">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" class="form-control font-monospace text-uppercase"
                                   placeholder="Search container in yard…" required id="containerSearch">
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Container Info Card (populated via JS/AJAX) -->
                    <div class="alert alert-info small p-2 mb-3" id="containerInfoBox">
                        <i class="bi bi-info-circle me-1"></i>Enter a container number above to view details.
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Release Order No.</label>
                            <input type="text" name="release_order" class="form-control"
                                   placeholder="RO-XXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   placeholder="e.g. JHQ 5678">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control"
                                   placeholder="Driver's name">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver IC/Passport</label>
                            <input type="text" name="driver_ic" class="form-control"
                                   placeholder="ID number">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"
                                      placeholder="Any remarks…"></textarea>
                        </div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-right me-2"></i>Record Gate Out
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Movements -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Today's Gate Movements</span>
                <span class="badge bg-primary rounded-pill">{{ $recentMovements->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <div class="list-group list-group-flush">
                    @forelse($recentMovements as $mv)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-muted small" style="width:45px;">
                                {{ ($mv->gate_in_time ?? $mv->gate_out_time)?->format('H:i') }}
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="font-monospace fw-semibold small">{{ $mv->container_no }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem;">{{ $mv->size }}' {{ $mv->container_type }}</span>
                                    @if($mv->movement_type === 'in')
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem;"><i class="bi bi-arrow-down-circle"></i> In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success" style="font-size:.65rem;"><i class="bi bi-arrow-up-circle"></i> Out</span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $mv->customer?->name }} &nbsp;·&nbsp; {{ $mv->vehicle_plate }}
                                </div>
                            </div>
                            <span class="badge rounded-pill {{ $mv->movement_status === 'done' ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size:.65rem;">
                                {{ ucfirst($mv->movement_status) }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item text-center text-muted small py-4">
                        No gate movements recorded yet.
                    </div>
                    @endforelse
                </div>
            </div>
            @php
                $inCount  = $recentMovements->where('movement_type', 'in')->count();
                $outCount = $recentMovements->where('movement_type', 'out')->count();
            @endphp
            <div class="card-footer bg-white">
                <div class="row text-center small">
                    <div class="col">
                        <div class="text-muted">Gate-In</div>
                        <strong class="text-primary">{{ $inCount }}</strong>
                    </div>
                    <div class="col border-start border-end">
                        <div class="text-muted">Gate-Out</div>
                        <strong class="text-success">{{ $outCount }}</strong>
                    </div>
                    <div class="col">
                        <div class="text-muted">Total</div>
                        <strong>{{ $recentMovements->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    const btnIn   = document.getElementById('btnGateIn');
    const btnOut  = document.getElementById('btnGateOut');
    const cardIn  = document.getElementById('gateInCard');
    const cardOut = document.getElementById('gateOutCard');

    btnIn.addEventListener('click', () => {
        cardIn.classList.remove('d-none');
        cardOut.classList.add('d-none');
        btnIn.classList.replace('btn-outline-primary','btn-primary');
        btnOut.classList.replace('btn-success','btn-outline-success');
    });

    btnOut.addEventListener('click', () => {
        cardOut.classList.remove('d-none');
        cardIn.classList.add('d-none');
        btnOut.classList.replace('btn-outline-success','btn-success');
        btnIn.classList.replace('btn-primary','btn-outline-primary');
    });

    // Auto-format container number
    document.getElementById('containerNoIn').addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    // Equipment Type auto-fill
    (function () {
        const sel       = document.getElementById('gateEqtSelect');
        const sizeHid   = document.getElementById('gateEqtSize');
        const typeHid   = document.getElementById('gateEqtTypeCode');
        const sizeBadge = document.getElementById('gateEqtSizeBadge');
        const typeBadge = document.getElementById('gateEqtTypeBadge');

        function applyEqt(opt) {
            if (!opt || !opt.value) {
                sizeHid.value = '';
                typeHid.value = '';
                sizeBadge.classList.add('d-none');
                typeBadge.classList.add('d-none');
                return;
            }
            sizeHid.value = opt.dataset.size;
            typeHid.value = opt.dataset.type;
            sizeBadge.textContent = opt.dataset.size + "'";
            typeBadge.textContent = opt.dataset.type;
            sizeBadge.classList.remove('d-none');
            typeBadge.classList.remove('d-none');
        }

        sel.addEventListener('change', () => applyEqt(sel.selectedOptions[0]));
        if (sel.value) applyEqt(sel.selectedOptions[0]);
    })();
</script>
@endpush
