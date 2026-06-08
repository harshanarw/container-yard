@extends('layouts.app')

@section('title', ($direction === 'gate_in' ? 'Gate-In' : 'Gate-Out') . ' Capture')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('guard-post.index') }}">Guard Post</a></li>
    <li class="breadcrumb-item active">New {{ $direction === 'gate_in' ? 'Gate-In' : 'Gate-Out' }} Capture</li>
@endsection

@push('styles')
<style>
    .capture-section { border-left: 4px solid var(--primary); }
    .section-number {
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--primary); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1rem; flex-shrink: 0;
    }
    .photo-upload-box {
        border: 2px dashed #ced4da; border-radius: 10px;
        padding: 20px; text-align: center; cursor: pointer;
        transition: border-color .2s, background .2s;
        position: relative; overflow: hidden; min-height: 130px;
        display: flex; align-items: center; justify-content: center;
    }
    .photo-upload-box:hover, .photo-upload-box.dragover {
        border-color: var(--primary); background: #e3f2fd22;
    }
    .photo-upload-box .preview-img {
        max-width: 100%; max-height: 200px;
        border-radius: 6px; display: none;
    }
    .photo-upload-box .upload-placeholder { pointer-events: none; }
    .photo-upload-box input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 2;
    }
    .ocr-badge {
        display: none; background: #e8f5e9; color: #2e7d32;
        border: 1px solid #a5d6a7; border-radius: 6px;
        padding: 6px 12px; font-size: .82rem; font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex align-items-center gap-3">
    <a href="{{ route('guard-post.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        @if($direction === 'gate_in')
            <h4><i class="bi bi-box-arrow-in-right me-2 text-success"></i>New Gate-In Capture</h4>
        @else
            <h4><i class="bi bi-box-arrow-right me-2 text-primary"></i>New Gate-Out Capture</h4>
        @endif
    </div>
</div>

@if($errors->has('general'))
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first('general') }}</div>
@endif

<form method="POST" action="{{ route('guard-post.store') }}" enctype="multipart/form-data" id="captureForm">
    @csrf
    <input type="hidden" name="direction" value="{{ $direction }}">

    {{-- ── Section A: Container ─────────────────────────────────────────── --}}
    <div class="card content-card capture-section mb-4">
        <div class="card-header py-3 d-flex align-items-center gap-3">
            <div class="section-number">A</div>
            <div>
                <div class="fw-bold">Container Details</div>
                <div class="text-muted small">Photograph the rear door plate — number will be read automatically</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Container Rear Photo</label>
                    <div class="photo-upload-box" id="containerPhotoBox">
                        <input type="file" name="container_image" id="containerImage"
                               accept="image/*" capture="environment">
                        <div class="upload-placeholder">
                            <i class="bi bi-camera fs-2 text-muted d-block mb-1"></i>
                            <div class="small text-muted">Tap to take photo or choose file</div>
                            <div class="text-muted" style="font-size:.72rem;">JPEG / PNG — max 10 MB</div>
                        </div>
                        <img class="preview-img" id="containerPreview" src="" alt="Container photo preview">
                    </div>
                    <div class="ocr-badge mt-2" id="ocrBadge">
                        <i class="bi bi-magic me-1"></i>OCR detected: <span id="ocrResult"></span>
                    </div>
                    @error('container_image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7">
                    <div class="mb-3">
                        <label for="containerNumber" class="form-label fw-semibold">
                            Container Number
                            <span class="text-muted fw-normal">(auto-filled from photo, or enter manually)</span>
                        </label>
                        <input type="text" class="form-control font-monospace text-uppercase @error('container_number') is-invalid @enderror"
                               id="containerNumber" name="container_number"
                               value="{{ old('container_number') }}"
                               placeholder="e.g. CAIU9081725" maxlength="20" autocomplete="off"
                               oninput="this.value = this.value.toUpperCase()">
                        @error('container_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="isoCode" class="form-label fw-semibold">
                            Size / Type Code
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Optional</span>
                        </label>
                        <input type="text" class="form-control font-monospace text-uppercase @error('iso_code') is-invalid @enderror"
                               id="isoCode" name="iso_code"
                               value="{{ old('iso_code') }}"
                               placeholder="e.g. 22G1" maxlength="10" autocomplete="off"
                               oninput="this.value = this.value.toUpperCase()">
                        @error('iso_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section B: Vehicle ───────────────────────────────────────────── --}}
    <div class="card content-card capture-section mb-4">
        <div class="card-header py-3 d-flex align-items-center gap-3">
            <div class="section-number">B</div>
            <div>
                <div class="fw-bold">Vehicle Details</div>
                <div class="text-muted small">Photograph the number plate</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Number Plate Photo</label>
                    <div class="photo-upload-box" id="platePhotoBox">
                        <input type="file" name="plate_image" id="plateImage"
                               accept="image/*" capture="environment">
                        <div class="upload-placeholder">
                            <i class="bi bi-camera fs-2 text-muted d-block mb-1"></i>
                            <div class="small text-muted">Tap to take photo or choose file</div>
                        </div>
                        <img class="preview-img" id="platePreview" src="" alt="Plate photo preview">
                    </div>
                    @error('plate_image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-7">
                    <div class="mb-3">
                        <label for="vehicleNumber" class="form-label fw-semibold">Vehicle Number</label>
                        <input type="text" class="form-control font-monospace text-uppercase @error('vehicle_number') is-invalid @enderror"
                               id="vehicleNumber" name="vehicle_number"
                               value="{{ old('vehicle_number') }}"
                               placeholder="e.g. WP CAB-4521" maxlength="30" autocomplete="off"
                               oninput="this.value = this.value.toUpperCase()">
                        @error('vehicle_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="vehicleType" class="form-label fw-semibold">
                            Vehicle Type
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Optional</span>
                        </label>
                        <select class="form-select @error('vehicle_type') is-invalid @enderror"
                                id="vehicleType" name="vehicle_type">
                            <option value="">— Select —</option>
                            <option value="truck"    {{ old('vehicle_type') === 'truck'    ? 'selected' : '' }}>Truck</option>
                            <option value="trailer"  {{ old('vehicle_type') === 'trailer'  ? 'selected' : '' }}>Trailer</option>
                            <option value="flatbed"  {{ old('vehicle_type') === 'flatbed'  ? 'selected' : '' }}>Flatbed</option>
                            <option value="other"    {{ old('vehicle_type') === 'other'    ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section C: Driver ────────────────────────────────────────────── --}}
    <div class="card content-card capture-section mb-4">
        <div class="card-header py-3 d-flex align-items-center gap-3">
            <div class="section-number">C</div>
            <div>
                <div class="fw-bold">Driver Details</div>
                <div class="text-muted small">Photograph ID documents and enter driver information</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">NIC / ID — Front</label>
                    <div class="photo-upload-box" id="nicFrontBox">
                        <input type="file" name="nic_front" id="nicFront" accept="image/*" capture="environment">
                        <div class="upload-placeholder">
                            <i class="bi bi-person-vcard fs-2 text-muted d-block mb-1"></i>
                            <div class="small text-muted">Front side</div>
                        </div>
                        <img class="preview-img" id="nicFrontPreview" src="" alt="">
                    </div>
                    @error('nic_front')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">NIC / ID — Back</label>
                    <div class="photo-upload-box" id="nicBackBox">
                        <input type="file" name="nic_back" id="nicBack" accept="image/*" capture="environment">
                        <div class="upload-placeholder">
                            <i class="bi bi-person-vcard-fill fs-2 text-muted d-block mb-1"></i>
                            <div class="small text-muted">Back side</div>
                        </div>
                        <img class="preview-img" id="nicBackPreview" src="" alt="">
                    </div>
                    @error('nic_back')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Driving Licence — Front</label>
                    <div class="photo-upload-box" id="licenseFrontBox">
                        <input type="file" name="license_front" id="licenseFront" accept="image/*" capture="environment">
                        <div class="upload-placeholder">
                            <i class="bi bi-card-text fs-2 text-muted d-block mb-1"></i>
                            <div class="small text-muted">Licence front</div>
                        </div>
                        <img class="preview-img" id="licenseFrontPreview" src="" alt="">
                    </div>
                    @error('license_front')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="driverName" class="form-label fw-semibold">Driver Name</label>
                    <input type="text" class="form-control @error('driver_name') is-invalid @enderror"
                           id="driverName" name="driver_name" value="{{ old('driver_name') }}"
                           placeholder="Full name" maxlength="150">
                    @error('driver_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="nicNumber" class="form-label fw-semibold">NIC Number</label>
                    <input type="text" class="form-control font-monospace @error('nic_number') is-invalid @enderror"
                           id="nicNumber" name="nic_number" value="{{ old('nic_number') }}"
                           placeholder="e.g. 901234567V" maxlength="30" autocomplete="off"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('nic_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="driverPhone" class="form-label fw-semibold">
                        Phone
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Optional</span>
                    </label>
                    <input type="text" class="form-control @error('driver_phone') is-invalid @enderror"
                           id="driverPhone" name="driver_phone" value="{{ old('driver_phone') }}"
                           placeholder="e.g. 077 123 4567" maxlength="30">
                    @error('driver_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="d-flex gap-3 align-items-center mb-5">
        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
            <i class="bi bi-send me-2"></i>Submit Capture
        </button>
        <a href="{{ route('guard-post.index') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
        <div class="ms-auto text-muted small">
            <i class="bi bi-info-circle me-1"></i>All photos are optional — provide what you have.
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Photo preview + file input wiring
    const photoPairs = [
        ['containerImage', 'containerPreview', 'containerPhotoBox'],
        ['plateImage',      'platePreview',     'platePhotoBox'],
        ['nicFront',        'nicFrontPreview',  'nicFrontBox'],
        ['nicBack',         'nicBackPreview',   'nicBackBox'],
        ['licenseFront',    'licenseFrontPreview', 'licenseFrontBox'],
    ];

    photoPairs.forEach(([inputId, previewId, boxId]) => {
        const input   = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const box     = document.getElementById(boxId);
        if (!input || !preview || !box) return;

        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.style.display = 'block';
                box.querySelector('.upload-placeholder').style.display = 'none';
            };
            reader.readAsDataURL(file);

            // Trigger OCR only for container image
            if (inputId === 'containerImage') triggerOcr(file);
        });

        // Drag & drop
        box.addEventListener('dragover',  e => { e.preventDefault(); box.classList.add('dragover'); });
        box.addEventListener('dragleave', () => box.classList.remove('dragover'));
        box.addEventListener('drop', e => {
            e.preventDefault();
            box.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });
    });

    // OCR auto-scan on container image
    function triggerOcr(file) {
        const badge = document.getElementById('ocrBadge');
        const result = document.getElementById('ocrResult');
        badge.style.display = 'none';
        result.textContent = '';

        const form = new FormData();
        form.append('image', file);
        form.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch('{{ route('guard-post.ocr-scan') }}', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.container_no) {
                    const field = document.getElementById('containerNumber');
                    if (!field.value) field.value = data.container_no;
                    if (data.iso_type) {
                        const iso = document.getElementById('isoCode');
                        if (!iso.value) iso.value = data.iso_type;
                    }
                    result.textContent = data.container_no;
                    badge.style.display = 'inline-flex';
                    badge.style.alignItems = 'center';
                }
            })
            .catch(() => {});
    }

    // Prevent double-submit
    document.getElementById('captureForm').addEventListener('submit', function () {
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
    });
});
</script>
@endpush
