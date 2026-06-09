@extends('layouts.app')

@section('title', 'New Capture')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('guard-post.index') }}" class="text-decoration-none">Guard Post</a></li>
    <li class="breadcrumb-item active">New Capture</li>
@endsection

@section('content')

@php $isGateIn = $direction === 'gate_in'; @endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            @if($isGateIn)
                <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Gate-In Capture
            @else
                <i class="bi bi-box-arrow-right me-2 text-success"></i>Gate-Out Capture
            @endif
        </h4>
        <p class="text-muted mb-0 small">Take photos of the container, vehicle and driver documents</p>
    </div>
    <a href="{{ route('guard-post.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 mb-3">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix:</strong>
    <ul class="mb-0 mt-1 ps-3 small">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('guard-post.store') }}" id="captureForm" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="direction" value="{{ $direction }}">

    {{-- ── SECTION A: Container ──────────────────────────────────────────────── --}}
    <div class="card content-card mb-3">
        <div class="card-header py-2 {{ $isGateIn ? 'bg-primary' : 'bg-success' }} text-white d-flex align-items-center gap-2">
            <span class="badge bg-white {{ $isGateIn ? 'text-primary' : 'text-success' }} fw-bold">A</span>
            <i class="bi bi-box-seam"></i> Container
            <span class="badge bg-white bg-opacity-25 fw-normal ms-auto" style="font-size:.7rem;">Optional</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Container Photo</label>
                    <div class="photo-upload-box" id="boxContainerImage" onclick="triggerUpload('containerImage')">
                        <div class="photo-placeholder" id="phContainerImage">
                            <i class="bi bi-camera display-6 opacity-50"></i>
                            <div class="small text-muted mt-1">Tap to capture or upload</div>
                        </div>
                        <img id="prevContainerImage" class="photo-preview d-none" alt="">
                    </div>
                    <input type="file" name="container_image" id="containerImage" accept="image/*" capture="environment" class="d-none">
                    <div id="ocrStatus" class="mt-1 small text-muted d-none">
                        <i class="bi bi-hourglass-split me-1"></i>Reading container number…
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column justify-content-end gap-3">
                    <div>
                        <label class="form-label fw-semibold small">Container Number</label>
                        <input type="text" name="container_number" id="containerNumber"
                               class="form-control font-monospace text-uppercase"
                               value="{{ old('container_number') }}"
                               placeholder="XXXX0000000" maxlength="20" autocomplete="off">
                        <div class="form-text">Filled automatically by OCR — edit if incorrect</div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small">ISO Type Code</label>
                        <input type="text" name="iso_code" id="isoCode"
                               class="form-control font-monospace"
                               value="{{ old('iso_code') }}"
                               placeholder="e.g. 22G1" maxlength="10">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── SECTION B: Vehicle ────────────────────────────────────────────────── --}}
    <div class="card content-card mb-3">
        <div class="card-header py-2 {{ $isGateIn ? 'bg-primary' : 'bg-success' }} text-white d-flex align-items-center gap-2">
            <span class="badge bg-white {{ $isGateIn ? 'text-primary' : 'text-success' }} fw-bold">B</span>
            <i class="bi bi-truck"></i> Vehicle
            <span class="badge bg-white bg-opacity-25 fw-normal ms-auto" style="font-size:.7rem;">Optional</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Plate Photo</label>
                    <div class="photo-upload-box" id="boxPlateImage" onclick="triggerUpload('plateImage')">
                        <div class="photo-placeholder" id="phPlateImage">
                            <i class="bi bi-camera display-6 opacity-50"></i>
                            <div class="small text-muted mt-1">Tap to capture or upload</div>
                        </div>
                        <img id="prevPlateImage" class="photo-preview d-none" alt="">
                    </div>
                    <input type="file" name="plate_image" id="plateImage" accept="image/*" capture="environment" class="d-none">
                    <div id="plateOcrStatus" class="mt-1 small text-muted d-none">
                        <i class="bi bi-hourglass-split me-1"></i>Reading plate number…
                    </div>
                </div>
                <div class="col-md-6 d-flex flex-column justify-content-end gap-3">
                    <div>
                        <label class="form-label fw-semibold small">Vehicle / Plate Number</label>
                        <input type="text" name="vehicle_number" id="vehicleNumber"
                               class="form-control text-uppercase"
                               value="{{ old('vehicle_number') }}"
                               placeholder="e.g. ABC 1234" maxlength="30">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small">Vehicle Type</label>
                        <select name="vehicle_type" class="form-select">
                            <option value="">— Select —</option>
                            <option value="Prime Mover" {{ old('vehicle_type') === 'Prime Mover' ? 'selected' : '' }}>Prime Mover</option>
                            <option value="Truck" {{ old('vehicle_type') === 'Truck' ? 'selected' : '' }}>Truck</option>
                            <option value="Trailer" {{ old('vehicle_type') === 'Trailer' ? 'selected' : '' }}>Trailer</option>
                            <option value="Van" {{ old('vehicle_type') === 'Van' ? 'selected' : '' }}>Van</option>
                            <option value="Other" {{ old('vehicle_type') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── SECTION C: Driver ─────────────────────────────────────────────────── --}}
    <div class="card content-card mb-3">
        <div class="card-header py-2 {{ $isGateIn ? 'bg-primary' : 'bg-success' }} text-white d-flex align-items-center gap-2">
            <span class="badge bg-white {{ $isGateIn ? 'text-primary' : 'text-success' }} fw-bold">C</span>
            <i class="bi bi-person-vcard"></i> Driver
            <span class="badge bg-white bg-opacity-25 fw-normal ms-auto" style="font-size:.7rem;">Optional</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">NIC / ID (Front)</label>
                    <div class="photo-upload-box" onclick="triggerUpload('nicFront')">
                        <div class="photo-placeholder" id="phNicFront">
                            <i class="bi bi-camera display-6 opacity-50"></i>
                            <div class="small text-muted mt-1">Front</div>
                        </div>
                        <img id="prevNicFront" class="photo-preview d-none" alt="">
                    </div>
                    <input type="file" name="nic_front" id="nicFront" accept="image/*" capture="environment" class="d-none">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">NIC / ID (Back)</label>
                    <div class="photo-upload-box" onclick="triggerUpload('nicBack')">
                        <div class="photo-placeholder" id="phNicBack">
                            <i class="bi bi-camera display-6 opacity-50"></i>
                            <div class="small text-muted mt-1">Back</div>
                        </div>
                        <img id="prevNicBack" class="photo-preview d-none" alt="">
                    </div>
                    <input type="file" name="nic_back" id="nicBack" accept="image/*" capture="environment" class="d-none">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Driving Licence (Front)</label>
                    <div class="photo-upload-box" onclick="triggerUpload('licenseFront')">
                        <div class="photo-placeholder" id="phLicenseFront">
                            <i class="bi bi-camera display-6 opacity-50"></i>
                            <div class="small text-muted mt-1">Front</div>
                        </div>
                        <img id="prevLicenseFront" class="photo-preview d-none" alt="">
                    </div>
                    <input type="file" name="license_front" id="licenseFront" accept="image/*" capture="environment" class="d-none">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Driver Name</label>
                    <input type="text" name="driver_name" class="form-control" value="{{ old('driver_name') }}" maxlength="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">NIC / ID Number</label>
                    <input type="text" name="nic_number" class="form-control" value="{{ old('nic_number') }}" maxlength="50">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Phone Number</label>
                    <input type="text" name="driver_phone" class="form-control" value="{{ old('driver_phone') }}" maxlength="30">
                </div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="card content-card mb-4">
        <div class="card-body">
            <label class="form-label fw-semibold small">Notes <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Any additional observations…">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-{{ $isGateIn ? 'primary' : 'success' }} px-4" id="submitBtn">
            <span id="submitSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
            <i class="bi bi-send me-1" id="submitIcon"></i>Submit Capture
        </button>
        <a href="{{ route('guard-post.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection

@push('styles')
<style>
.photo-upload-box {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    min-height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: border-color .2s;
    background: #f8f9fa;
}
.photo-upload-box:hover { border-color: #6c757d; }
.photo-placeholder { text-align: center; padding: 1rem; color: #6c757d; }
.photo-preview { width: 100%; max-height: 180px; object-fit: cover; border-radius: 6px; }
</style>
@endpush

@push('scripts')
<script>
function triggerUpload(id) {
    document.getElementById(id).click();
}

function wirePreview(inputId, previewId, placeholderId) {
    const input   = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const ph      = document.getElementById(placeholderId);
    if (!input) return;
    input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        const url = URL.createObjectURL(this.files[0]);
        preview.src = url;
        preview.classList.remove('d-none');
        if (ph) ph.classList.add('d-none');
    });
}

wirePreview('containerImage', 'prevContainerImage', 'phContainerImage');
wirePreview('plateImage',     'prevPlateImage',     'phPlateImage');
wirePreview('nicFront',       'prevNicFront',        'phNicFront');
wirePreview('nicBack',        'prevNicBack',         'phNicBack');
wirePreview('licenseFront',   'prevLicenseFront',    'phLicenseFront');

// OCR auto-scan on container image upload
document.getElementById('containerImage').addEventListener('change', async function () {
    if (!this.files || !this.files[0]) return;
    const status = document.getElementById('ocrStatus');
    status.classList.remove('d-none');
    status.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Reading container number…';

    const fd = new FormData();
    fd.append('image', this.files[0]);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');

    try {
        const res  = await fetch('{{ route('guard-post.ocr-scan') }}', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.container_no) {
            document.getElementById('containerNumber').value = data.container_no;
            if (data.iso_type) document.getElementById('isoCode').value = data.iso_type;
            status.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Read: ' + data.container_no;
        } else {
            status.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Could not read number — enter manually.';
        }
    } catch (e) {
        status.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>OCR failed — enter manually.';
    }
});

// OCR auto-scan on plate image upload
document.getElementById('plateImage').addEventListener('change', async function () {
    if (!this.files || !this.files[0]) return;
    const status = document.getElementById('plateOcrStatus');
    status.classList.remove('d-none');
    status.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Reading plate number…';

    const fd = new FormData();
    fd.append('image', this.files[0]);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');

    try {
        const res  = await fetch('{{ route('yard.ocr-plate') }}', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.plate_no) {
            // Insert space between letter prefix and digit group for readability
            const raw = data.plate_no.replace(/\s+/g, '').toUpperCase();
            const m   = raw.match(/^([A-Z]{2,4})([0-9]{4,5})$/);
            document.getElementById('vehicleNumber').value = m ? m[1] + ' ' + m[2] : raw;
            status.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Read: ' + (m ? m[1] + ' ' + m[2] : raw);
        } else {
            status.innerHTML = '<i class="bi bi-exclamation-circle text-warning me-1"></i>Could not read plate — enter manually.';
        }
    } catch (e) {
        status.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>OCR failed — enter manually.';
    }
});

// Disable submit button on form submit to prevent double-submit
document.getElementById('captureForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    document.getElementById('submitSpinner').classList.remove('d-none');
    document.getElementById('submitIcon').classList.add('d-none');
    btn.disabled = true;
});
</script>
@endpush
