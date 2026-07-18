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
                        <div class="ocr-overlay d-none" id="ocrOverlayContainer"></div>
                    </div>
                    <input type="file" name="container_image" id="containerImage" accept="image/*" capture="environment" class="d-none">
                    <div id="ocrStatus" class="mt-2 d-none"></div>
                </div>
                <div class="col-md-6 d-flex flex-column justify-content-end gap-3">
                    <div>
                        <label class="form-label fw-semibold small">Container Number</label>
                        <input type="text" name="container_number" id="containerNumber"
                               class="form-control font-monospace text-uppercase"
                               value="{{ old('container_number') }}"
                               placeholder="XXXX0000000" maxlength="11" autocomplete="off">
                        <div id="containerCheckDigitWarn" class="mt-1 d-none">
                            <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fbbf24;font-size:.72rem;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Check digit not confirmed — please verify number
                            </span>
                        </div>
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
                        <div class="ocr-overlay d-none" id="ocrOverlayPlate"></div>
                    </div>
                    <input type="file" name="plate_image" id="plateImage" accept="image/*" capture="environment" class="d-none">
                    <div id="plateOcrStatus" class="mt-2 d-none"></div>
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
    transition: border-color .2s, box-shadow .2s;
    background: #f8f9fa;
    position: relative;
}
.photo-upload-box:hover { border-color: #6c757d; }
.photo-upload-box.ocr-active  { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
.photo-upload-box.ocr-ok      { border-color: #198754; box-shadow: 0 0 0 3px rgba(25,135,84,.15); }
.photo-upload-box.ocr-warn    { border-color: #d97706; box-shadow: 0 0 0 3px rgba(217,119,6,.15); }
.photo-upload-box.ocr-err     { border-color: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,.15); }
.photo-placeholder { text-align: center; padding: 1rem; color: #6c757d; }
.photo-preview { width: 100%; max-height: 180px; object-fit: cover; border-radius: 6px; }
/* OCR overlay sits on top of the photo while scanning / briefly after result */
.ocr-overlay {
    position: absolute;
    inset: 0;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    color: #fff;
    font-size: .82rem;
    font-weight: 600;
    pointer-events: none;
    transition: opacity .3s;
}
.ocr-overlay.scanning { background: rgba(13,110,253,.60); }
.ocr-overlay.ok       { background: rgba(25,135,84,.65); }
.ocr-overlay.warn     { background: rgba(217,119,6,.65); }
.ocr-overlay.err      { background: rgba(220,53,69,.60); }
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

// ── OCR overlay helpers ───────────────────────────────────────────────────────

const OCR_DISMISS_MS = 4000;

/**
 * Show/update the overlay on a photo box.
 * state: 'scanning' | 'ok' | 'warn' | 'err' | 'hide'
 */
function setOcrOverlay(overlayId, boxId, state, html) {
    const overlay = document.getElementById(overlayId);
    const box     = document.getElementById(boxId);
    if (!overlay || !box) return;

    // Reset box border classes
    box.classList.remove('ocr-active', 'ocr-ok', 'ocr-warn', 'ocr-err');

    if (state === 'hide') {
        overlay.classList.add('d-none');
        return;
    }

    overlay.className = 'ocr-overlay ' + state;
    overlay.innerHTML = html;
    overlay.classList.remove('d-none');

    const borderClass = { scanning: 'ocr-active', ok: 'ocr-ok', warn: 'ocr-warn', err: 'ocr-err' }[state];
    if (borderClass) box.classList.add(borderClass);

    if (state !== 'scanning') {
        setTimeout(() => {
            overlay.classList.add('d-none');
            box.classList.remove('ocr-active', 'ocr-ok', 'ocr-warn', 'ocr-err');
        }, OCR_DISMISS_MS);
    }
}

function setOcrStatus(statusId, html) {
    const el = document.getElementById(statusId);
    if (!el) return;
    el.classList.remove('d-none');
    el.innerHTML = html;
}

// ── Container OCR ─────────────────────────────────────────────────────────────

document.getElementById('containerImage').addEventListener('change', async function () {
    if (!this.files || !this.files[0]) return;

    setOcrOverlay('ocrOverlayContainer', 'boxContainerImage', 'scanning',
        '<div class="spinner-border spinner-border-sm" role="status"></div>' +
        '<span>Reading container number…</span>');
    setOcrStatus('ocrStatus',
        '<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-2 py-1">' +
        '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Reading container number…</span>');

    const fd = new FormData();
    fd.append('image', this.files[0]);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');

    try {
        const res  = await fetch('{{ route('guard-post.ocr-scan') }}', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.container_no) {
            document.getElementById('containerNumber').value = data.container_no;
            if (data.iso_type) document.getElementById('isoCode').value = data.iso_type;
            const warn = document.getElementById('containerCheckDigitWarn');

            if (data.check_digit_valid === false) {
                warn.classList.remove('d-none');
                setOcrOverlay('ocrOverlayContainer', 'boxContainerImage', 'warn',
                    '<i class="bi bi-exclamation-triangle-fill fs-2"></i>' +
                    '<span>' + data.container_no + '</span>' +
                    '<span style="font-size:.72rem;font-weight:400;">Check digit unconfirmed</span>');
                setOcrStatus('ocrStatus',
                    '<span class="badge border px-2 py-1" style="background:#fef3c7;color:#92400e;border-color:#fbbf24!important;">' +
                    '<i class="bi bi-exclamation-triangle-fill me-1"></i>Read: ' + data.container_no + ' — verify check digit</span>');
            } else {
                warn.classList.add('d-none');
                setOcrOverlay('ocrOverlayContainer', 'boxContainerImage', 'ok',
                    '<i class="bi bi-check-circle-fill fs-2"></i>' +
                    '<span>' + data.container_no + '</span>');
                setOcrStatus('ocrStatus',
                    '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">' +
                    '<i class="bi bi-check-circle-fill me-1"></i>Read: ' + data.container_no + '</span>');
            }
        } else {
            document.getElementById('containerCheckDigitWarn').classList.add('d-none');
            setOcrOverlay('ocrOverlayContainer', 'boxContainerImage', 'warn',
                '<i class="bi bi-exclamation-triangle-fill fs-2"></i>' +
                '<span>Could not read</span>' +
                '<span style="font-size:.72rem;font-weight:400;">Enter number manually</span>');
            setOcrStatus('ocrStatus',
                '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>Could not read number — enter manually</span>');
        }
    } catch (e) {
        setOcrOverlay('ocrOverlayContainer', 'boxContainerImage', 'err',
            '<i class="bi bi-x-circle-fill fs-2"></i><span>OCR failed</span>');
        setOcrStatus('ocrStatus',
            '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle px-2 py-1">' +
            '<i class="bi bi-x-circle me-1"></i>OCR failed — enter manually</span>');
    }
});

// ── Container number: enforce ISO 6346 shape while typing (like the gate form) ─
(function () {
    const inp = document.getElementById('containerNumber');
    if (!inp) return;

    // Block invalid keys: letters only in positions 0-3, digits only in 4-10, cap 11.
    inp.addEventListener('keydown', function (e) {
        const ctrl = e.ctrlKey || e.metaKey;
        const nav  = ['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab','Enter'].includes(e.key);
        if (ctrl || nav) return;
        const pos = this.selectionStart, sel = this.selectionEnd;
        if (this.value.length >= 11 && pos === sel) { e.preventDefault(); return; }
        if (pos < 4) { if (!/^[A-Za-z]$/.test(e.key)) e.preventDefault(); }
        else         { if (!/^[0-9]$/.test(e.key))     e.preventDefault(); }
    });
    // Uppercase + keep only 4 letters then 7 digits (handles paste too).
    inp.addEventListener('input', function () {
        const raw = this.value.toUpperCase();
        let out = '', letters = 0, digits = 0;
        for (let i = 0; i < raw.length; i++) {
            if (letters < 4 && /[A-Z]/.test(raw[i])) { out += raw[i]; letters++; }
            else if (letters === 4 && digits < 7 && /[0-9]/.test(raw[i])) { out += raw[i]; digits++; }
            if (out.length >= 11) break;
        }
        this.value = out;
        checkContainerDigit();
    });
    inp.addEventListener('blur', checkContainerDigit);
})();

// Live ISO 6346 check-digit warning for the manually-typed number.
function isoCheckDigitValid(no) {
    if (!/^[A-Z]{4}[0-9]{7}$/.test(no)) return null;
    const v = {A:10,B:12,C:13,D:14,E:15,F:16,G:17,H:18,I:19,J:20,K:21,L:23,M:24,
               N:25,O:26,P:27,Q:28,R:29,S:30,T:31,U:32,V:34,W:35,X:36,Y:37,Z:38};
    let sum = 0;
    for (let i = 0; i < 10; i++) {
        const ch = no[i];
        sum += (/[A-Z]/.test(ch) ? v[ch] : parseInt(ch, 10)) * Math.pow(2, i);
    }
    return (sum % 11) % 10 === parseInt(no[10], 10);
}
function checkContainerDigit() {
    const inp  = document.getElementById('containerNumber');
    const warn = document.getElementById('containerCheckDigitWarn');
    if (!inp || !warn) return;
    // Show only when the number is complete (11 chars) and the check digit is wrong.
    warn.classList.toggle('d-none', isoCheckDigitValid(inp.value.trim().toUpperCase()) !== false);
}

// ── Plate OCR ─────────────────────────────────────────────────────────────────

document.getElementById('plateImage').addEventListener('change', async function () {
    if (!this.files || !this.files[0]) return;

    setOcrOverlay('ocrOverlayPlate', 'boxPlateImage', 'scanning',
        '<div class="spinner-border spinner-border-sm" role="status"></div>' +
        '<span>Reading plate number…</span>');
    setOcrStatus('plateOcrStatus',
        '<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-2 py-1">' +
        '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Reading plate number…</span>');

    const fd = new FormData();
    fd.append('image', this.files[0]);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');

    try {
        const res  = await fetch('{{ route('yard.ocr-plate') }}', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.plate_no) {
            const raw = data.plate_no.replace(/\s+/g, '').toUpperCase();
            const m   = raw.match(/^([A-Z]{2,4})([0-9]{4,5})$/);
            const formatted = m ? m[1] + ' ' + m[2] : raw;
            document.getElementById('vehicleNumber').value = formatted;
            setOcrOverlay('ocrOverlayPlate', 'boxPlateImage', 'ok',
                '<i class="bi bi-check-circle-fill fs-2"></i>' +
                '<span>' + formatted + '</span>');
            setOcrStatus('plateOcrStatus',
                '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">' +
                '<i class="bi bi-check-circle-fill me-1"></i>Read: ' + formatted + '</span>');
        } else {
            setOcrOverlay('ocrOverlayPlate', 'boxPlateImage', 'warn',
                '<i class="bi bi-exclamation-triangle-fill fs-2"></i>' +
                '<span>Could not read</span>' +
                '<span style="font-size:.72rem;font-weight:400;">Enter plate manually</span>');
            setOcrStatus('plateOcrStatus',
                '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>Could not read plate — enter manually</span>');
        }
    } catch (e) {
        setOcrOverlay('ocrOverlayPlate', 'boxPlateImage', 'err',
            '<i class="bi bi-x-circle-fill fs-2"></i><span>OCR failed</span>');
        setOcrStatus('plateOcrStatus',
            '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle px-2 py-1">' +
            '<i class="bi bi-x-circle me-1"></i>OCR failed — enter manually</span>');
    }
});

// ── Submit guard ──────────────────────────────────────────────────────────────

document.getElementById('captureForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    document.getElementById('submitSpinner').classList.remove('d-none');
    document.getElementById('submitIcon').classList.add('d-none');
    btn.disabled = true;
});
</script>
@endpush
