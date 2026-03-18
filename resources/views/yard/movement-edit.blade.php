@extends('layouts.app')

@section('title', 'Edit Gate Movement #' . $movement->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.gate') }}">Gate In / Gate Out</a></li>
    <li class="breadcrumb-item active">Edit Movement #{{ $movement->id }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            @if($movement->movement_type === 'in')
                <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>
            @else
                <i class="bi bi-box-arrow-right me-2 text-success"></i>
            @endif
            Edit Gate {{ ucfirst($movement->movement_type) }} &mdash;
            <span class="font-monospace">{{ $movement->container_no }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            Movement #{{ $movement->id }} &nbsp;·&nbsp;
            Recorded {{ ($movement->gate_in_time ?? $movement->gate_out_time)?->format('d M Y H:i') }}
            &nbsp;·&nbsp; By {{ $movement->createdBy?->name ?? '—' }}
        </p>
    </div>
    <a href="{{ route('yard.gate') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header {{ $movement->movement_type === 'in' ? 'bg-primary' : 'bg-success' }} text-white">
                <i class="bi bi-pencil-square me-2"></i>Movement Details
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.movements.update', $movement) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    {{-- Container info (read-only) --}}
                    <div class="mb-3 p-3 bg-light rounded-3 small">
                        <div class="row g-1">
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Container</div>
                                <div class="fw-semibold font-monospace">{{ $movement->container_no }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Size / Type</div>
                                <div class="fw-semibold">{{ $movement->size }}' {{ $movement->container_type }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Customer</div>
                                <div class="fw-semibold">{{ $movement->customer?->name ?? '—' }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Location</div>
                                <div class="fw-semibold">
                                    @if($movement->location_row)
                                        {{ $movement->location_row }}{{ $movement->location_bay }}-T{{ $movement->location_tier }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger small py-2">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-3">

                        {{-- Gate In specific fields --}}
                        @if($movement->movement_type === 'in')

                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner</label>
                            <select name="customer_id" class="form-select select2">
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    {{ $movement->customer_id == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound"          {{ $movement->condition === 'sound'          ? 'selected' : '' }}>Sound</option>
                                <option value="damaged"        {{ $movement->condition === 'damaged'        ? 'selected' : '' }}>Damaged</option>
                                <option value="require_repair" {{ $movement->condition === 'require_repair' ? 'selected' : '' }}>Requires Repair</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty / Full</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty" {{ $movement->cargo_status === 'empty' ? 'selected' : '' }}>Empty</option>
                                <option value="full"  {{ $movement->cargo_status === 'full'  ? 'selected' : '' }}>Full</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control"
                                   value="{{ old('seal_no', $movement->seal_no) }}" placeholder="Optional">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   value="{{ old('vehicle_plate', $movement->vehicle_plate) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate In Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Admin only
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_in_time"
                                   class="form-control"
                                   value="{{ old('gate_in_time', $movement->gate_in_time?->format('Y-m-d\TH:i')) }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                        </div>

                        @else {{-- Gate Out --}}

                        <div class="col-6">
                            <label class="form-label fw-semibold">Release Order No.</label>
                            <input type="text" name="release_order" class="form-control"
                                   value="{{ old('release_order', $movement->release_order) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   value="{{ old('vehicle_plate', $movement->vehicle_plate) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control"
                                   value="{{ old('driver_name', $movement->driver_name) }}">
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver IC/Passport</label>
                            <input type="text" name="driver_ic" class="form-control"
                                   value="{{ old('driver_ic', $movement->driver_ic) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate Out Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Admin only
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_out_time"
                                   class="form-control"
                                   value="{{ old('gate_out_time', $movement->gate_out_time?->format('Y-m-d\TH:i')) }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                        </div>

                        @endif

                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $movement->remarks) }}</textarea>
                        </div>

                        {{-- Add more photos --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-camera me-1 text-primary"></i>Add More Photos
                                <span class="text-muted fw-normal small">(optional, max 5)</span>
                                <span id="editPhotoCounter" class="badge bg-secondary-subtle text-secondary ms-1">0 / 5</span>
                            </label>

                            <input type="file" id="editPhotoInput"
                                   multiple accept="image/*" class="d-none">
                            <input type="file" id="editCameraInput" accept="image/*"
                                   capture="environment" class="d-none">

                            <div id="editDropZone"
                                 class="border border-2 rounded-3 text-center p-3 mb-2"
                                 style="border-color:#dee2e6!important;border-style:dashed!important;cursor:pointer;transition:background .2s;">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="editBrowseBtn">
                                        <i class="bi bi-folder2-open me-1"></i>Browse
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="editCameraBtn">
                                        <i class="bi bi-camera me-1"></i>Camera
                                    </button>
                                </div>
                                <div class="text-muted mt-1" style="font-size:.72rem;">
                                    or drag &amp; drop images here &nbsp;·&nbsp; JPG/PNG/WEBP &nbsp;·&nbsp; max 5 MB each
                                </div>
                            </div>
                            <div id="editPhotoError" class="alert alert-danger py-1 small d-none mb-2"></div>
                            <div class="row g-1" id="editPhotoPreview"></div>
                        </div>

                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn {{ $movement->movement_type === 'in' ? 'btn-primary' : 'btn-success' }}">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="{{ route('yard.gate') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Existing Photos --}}
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-images me-2 text-primary"></i>Existing Photos
                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $movement->photos->count() }}</span>
            </div>
            <div class="card-body">
                @if($movement->photos->isEmpty())
                    <p class="text-muted small text-center py-3">No photos attached to this movement.</p>
                @else
                    <div class="row g-2">
                        @foreach($movement->photos as $photo)
                        <div class="col-6" id="photo-col-{{ $photo->id }}">
                            <div class="position-relative" style="border-radius:6px;overflow:hidden;">
                                <a href="{{ asset($photo->photo_path) }}" target="_blank">
                                    <img src="{{ asset($photo->photo_path) }}"
                                         style="width:100%;height:100px;object-fit:cover;" alt="">
                                </a>
                                <form method="POST"
                                      action="{{ route('yard.movements.photo.destroy', [$movement, $photo]) }}"
                                      class="position-absolute"
                                      style="top:4px;right:4px;"
                                      onsubmit="return confirm('Remove this photo?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-danger btn-sm py-0 px-1"
                                            style="font-size:.7rem;line-height:1.5;border-radius:50%;"
                                            title="Remove">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const MAX       = 5;
    const MAX_BYTES = 5 * 1024 * 1024;
    let files = [];

    const fileInput   = document.getElementById('editPhotoInput');
    const cameraInput = document.getElementById('editCameraInput');
    const browseBtn   = document.getElementById('editBrowseBtn');
    const cameraBtn   = document.getElementById('editCameraBtn');
    const dropZone    = document.getElementById('editDropZone');
    const errorEl     = document.getElementById('editPhotoError');
    const previewGrid = document.getElementById('editPhotoPreview');
    const counterEl   = document.getElementById('editPhotoCounter');
    const form        = fileInput.closest('form');
    const submitBtn   = form.querySelector('[type="submit"]');
    const origHtml    = submitBtn.innerHTML;

    function isImage(file) {
        if (/^image\/(jpeg|png|webp|gif)$/i.test(file.type)) return true;
        return /\.(jpe?g|png|webp|gif)$/i.test(file.name);
    }

    function updateCounter() {
        const n = files.length;
        counterEl.textContent = n + ' / ' + MAX;
        counterEl.className = n >= MAX
            ? 'badge bg-warning-subtle text-warning ms-1'
            : 'badge bg-secondary-subtle text-secondary ms-1';
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('d-none');
        setTimeout(function () { errorEl.classList.add('d-none'); }, 4000);
    }

    function renderPreviews() {
        previewGrid.innerHTML = '';
        files.forEach(function (file, idx) {
            const col = document.createElement('div');
            col.className = 'col-4 col-md-3';
            const reader = new FileReader();
            reader.onload = function (e) {
                col.innerHTML =
                    '<div class="position-relative" style="border-radius:6px;overflow:hidden;">' +
                        '<img src="' + e.target.result + '" style="width:100%;height:70px;object-fit:cover;" alt="">' +
                        '<button type="button" class="btn btn-danger btn-sm rm-photo position-absolute" ' +
                                'data-idx="' + idx + '" ' +
                                'style="top:2px;right:2px;padding:1px 5px;font-size:.7rem;line-height:1.2;border-radius:50%;">' +
                            '<i class="bi bi-x"></i>' +
                        '</button>' +
                    '</div>';
                previewGrid.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
        updateCounter();
    }

    function addFiles(incoming) {
        Array.from(incoming).forEach(function (file) {
            if (!isImage(file))          { showError('"' + file.name + '" is not a supported image type.'); return; }
            if (file.size > MAX_BYTES)   { showError('"' + file.name + '" exceeds 5 MB.'); return; }
            if (files.length >= MAX)     { showError('Maximum ' + MAX + ' photos allowed.'); return; }
            const dup = files.some(function (f) { return f.name === file.name && f.size === file.size; });
            if (!dup) files.push(file);
        });
        renderPreviews();
    }

    previewGrid.addEventListener('click', function (e) {
        const btn = e.target.closest('.rm-photo');
        if (!btn) return;
        const idx = parseInt(btn.dataset.idx, 10);
        files.splice(idx, 1);
        renderPreviews();
    });

    browseBtn.addEventListener('click', function (e) { e.stopPropagation(); fileInput.click(); });
    dropZone.addEventListener('click',  function () { fileInput.click(); });
    cameraBtn.addEventListener('click', function (e) { e.stopPropagation(); cameraInput.click(); });
    fileInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });
    cameraInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });

    dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); dropZone.style.background = '#e8f0fe'; });
    dropZone.addEventListener('dragleave', function ()  { dropZone.style.background = ''; });
    dropZone.addEventListener('drop',      function (e) {
        e.preventDefault();
        dropZone.style.background = '';
        addFiles(e.dataTransfer.files);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving…';
        const fd = new FormData(form);
        files.forEach(function (file) { fd.append('photos[]', file); });
        fetch(form.action, { method: 'POST', body: fd, redirect: 'manual' })
            .then(function () { window.location.reload(); })
            .catch(function () { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; });
    });
})();
</script>
@endpush
