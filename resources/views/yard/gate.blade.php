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
                        <div class="col-6">
                            <label class="form-label fw-semibold">Size</label>
                            <select name="size" class="form-select" required>
                                <option value="">— Size —</option>
                                <option>20'</option>
                                <option>40'</option>
                                <option>45'</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="container_type" class="form-select" required>
                                <option value="">— Type —</option>
                                <option value="GP">GP — General Purpose</option>
                                <option value="HC">HC — High Cube</option>
                                <option value="RF">RF — Reefer</option>
                                <option value="OT">OT — Open Top</option>
                                <option value="FR">FR — Flat Rack</option>
                                <option value="TK">TK — Tank</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                <option value="1">Maersk Line</option>
                                <option value="2">CMA CGM Malaysia</option>
                                <option value="3">Hapag-Lloyd</option>
                                <option value="4">PIL Shipping</option>
                                <option value="5">OOCL Malaysia</option>
                                <option value="6">Evergreen</option>
                                <option value="7">MSC Malaysia</option>
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
                                <select name="row" class="form-select form-select-sm">
                                    <option>Row A</option><option>Row B</option>
                                    <option>Row C</option><option>Row D</option>
                                </select>
                                <input type="text" name="bay" class="form-control form-control-sm"
                                       placeholder="Bay" style="max-width:70px;">
                                <input type="number" name="tier" class="form-control form-control-sm"
                                       placeholder="Tier" min="1" max="5" style="max-width:60px;" value="1">
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
                <span class="badge bg-primary rounded-pill">41</span>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <div class="list-group list-group-flush">
                    @php
                    $todayMovements = [
                        ['11:05','MSKU2223344','40\' HC','MSC','Gate In','Pending','WQR 5432'],
                        ['10:28','OOLU7778899','20\' GP','OOCL','Gate Out','Done','JTK 9821'],
                        ['10:02','HLXU3334455','40\' GP','Hapag','Gate In','Done','WBG 3344'],
                        ['09:34','TGHU5551234','20\' RF','PIL','Gate In','Pending','BCB 1122'],
                        ['09:12','CMAU9876543','40\' HC','Maersk','Gate Out','Done','WPQ 6677'],
                        ['08:45','MSCU1234567','20\' GP','Evergreen','Gate In','Done','VCX 9900'],
                        ['08:30','ZIMU4433221','40\' GP','ZIM','Gate Out','Done','WDF 5544'],
                        ['08:15','EVGU7654321','20\' GP','Evergreen','Gate In','Done','BCN 3322'],
                    ];
                    @endphp
                    @foreach($todayMovements as $mv)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-muted small" style="width:45px;">{{ $mv[0] }}</div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="font-monospace fw-semibold small">{{ $mv[1] }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem;">{{ $mv[2] }}</span>
                                    @if($mv[4]==='Gate In')
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem;"><i class="bi bi-arrow-down-circle"></i> In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success" style="font-size:.65rem;"><i class="bi bi-arrow-up-circle"></i> Out</span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">{{ $mv[3] }} &nbsp;·&nbsp; {{ $mv[6] }}</div>
                            </div>
                            <span class="badge rounded-pill {{ $mv[5]==='Done'?'bg-success':'bg-warning text-dark' }}" style="font-size:.65rem;">
                                {{ $mv[5] }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer bg-white">
                <div class="row text-center small">
                    <div class="col">
                        <div class="text-muted">Gate-In</div>
                        <strong class="text-primary">23</strong>
                    </div>
                    <div class="col border-start border-end">
                        <div class="text-muted">Gate-Out</div>
                        <strong class="text-success">18</strong>
                    </div>
                    <div class="col">
                        <div class="text-muted">Total</div>
                        <strong>41</strong>
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
</script>
@endpush
