{{--
    <x-document-manager
        model-type="App\Models\GateMovement"
        :model-id="$movement->id"
        title="Movement Photos & Documents"
    />
--}}
@php $uid = 'dm_' . Str::random(6); @endphp

<div class="card content-card" id="{{ $uid }}_card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-paperclip me-2 text-primary"></i>{{ $title }}</span>
        <span class="badge bg-secondary-subtle text-secondary" id="{{ $uid }}_count">
            {{ $documents->count() }} file(s)
        </span>
    </div>
    <div class="card-body">

        {{-- ── Upload zone ──────────────────────────────────────────────── --}}
        <div class="dm-drop-zone border border-2 border-dashed rounded-3 p-3 text-center mb-3"
             id="{{ $uid }}_zone"
             data-document-type="{{ $attributes->get('document-type', 'document') }}"
             style="border-color:#adb5bd!important; cursor:pointer; transition:background .2s;">
            <i class="bi bi-cloud-upload fs-3 text-muted d-block mb-1"></i>
            <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $uid }}_browse_btn">
                    <i class="bi bi-folder2-open me-1"></i>Browse
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="{{ $uid }}_camera_btn">
                    <i class="bi bi-camera me-1"></i>Camera
                </button>
            </div>
            <div class="text-muted mt-2" style="font-size:11px;">
                or drag &amp; drop · max {{ $maxFiles }} files · 20 MB each
                @if($showLabel)
                    <div class="mt-2">
                        <input type="text" id="{{ $uid }}_label" class="form-control form-control-sm"
                               placeholder="Label (optional)" style="max-width:240px;display:inline-block;">
                    </div>
                @endif
            </div>
            {{-- File picker (gallery / file system) --}}
            <input type="file" id="{{ $uid }}_input" multiple accept="{{ $accept }}" class="d-none">
            {{-- Camera capture — opens device camera directly on mobile/tablet --}}
            <input type="file" id="{{ $uid }}_camera" accept="image/*" capture="environment" class="d-none">
        </div>

        {{-- Upload progress ------------------------------------------------}}
        <div id="{{ $uid }}_progress" class="d-none mb-3">
            <div class="progress" style="height:6px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     id="{{ $uid }}_bar" style="width:0%"></div>
            </div>
            <div class="text-muted small mt-1" id="{{ $uid }}_prog_text">Uploading…</div>
        </div>

        {{-- ── File list ────────────────────────────────────────────────── --}}
        <div id="{{ $uid }}_list">
            @if($documents->isEmpty())
            <div class="text-center text-muted py-3 small" id="{{ $uid }}_empty">
                <i class="bi bi-folder2-open fs-3 d-block mb-1"></i>No files attached yet.
            </div>
            @endif

            <div class="row g-2" id="{{ $uid }}_grid">
                @foreach($documents as $doc)
                    @include('documents._card', ['doc' => $doc, 'uid' => $uid])
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── Preview Modal (shared, rendered once) ─────────────────────────── --}}
@once
@push('modals')
<div class="modal fade" id="docPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="docPreviewTitle">
                    <i class="bi bi-eye me-2"></i>Preview
                </h6>
                <div class="ms-auto d-flex gap-2 align-items-center">
                    <a href="#" id="docPreviewDownload" class="btn btn-outline-primary btn-sm" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="min-height:400px; max-height:80vh; overflow:auto;">
                <div id="docPreviewBody" class="d-flex align-items-center justify-content-center h-100 p-3">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush
@endonce

@push('scripts')
<script>
(function () {
    const uid        = '{{ $uid }}';
    const zone       = document.getElementById(uid + '_zone');
    const input      = document.getElementById(uid + '_input');
    const camera     = document.getElementById(uid + '_camera');
    const browseBtn  = document.getElementById(uid + '_browse_btn');
    const cameraBtn  = document.getElementById(uid + '_camera_btn');
    const grid       = document.getElementById(uid + '_grid');
    const countBadge = document.getElementById(uid + '_count');
    const emptyMsg   = document.getElementById(uid + '_empty');
    const progWrap   = document.getElementById(uid + '_progress');
    const progBar    = document.getElementById(uid + '_bar');
    const progText   = document.getElementById(uid + '_prog_text');
    const labelInput = document.getElementById(uid + '_label');
    const MAX        = {{ $maxFiles }};

    const modelType  = '{{ addslashes($modelType) }}';
    const modelId    = {{ $modelId }};
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const uploadUrl  = '{{ route('documents.store') }}';

    // ── Drop zone interactions ────────────────────────────────────────────
    // Zone click → browse (but not when a button inside it was clicked)
    zone.addEventListener('click', e => { if (!e.target.closest('button')) input.click(); });
    browseBtn.addEventListener('click', e => { e.stopPropagation(); input.click(); });
    cameraBtn.addEventListener('click', e => { e.stopPropagation(); camera.click(); });

    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.background = '#f0f4ff'; });
    zone.addEventListener('dragleave', () => { zone.style.background = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.background = '';
        handleFiles(Array.from(e.dataTransfer.files));
    });
    input.addEventListener('change', function () {
        const snapshot = Array.from(this.files);
        this.value = '';
        handleFiles(snapshot);
    });
    // Camera capture: single photo taken → upload immediately
    camera.addEventListener('change', function () {
        const snapshot = Array.from(this.files);
        this.value = '';
        handleFiles(snapshot);
    });

    // ── Upload: one file per request, sequentially ────────────────────────
    async function handleFiles(files) {
        if (!files.length) return;
        const batch = files.slice(0, MAX);

        zone.style.pointerEvents = 'none';
        zone.style.opacity = '0.6';
        try {
            for (let i = 0; i < batch.length; i++) {
                await uploadOne(batch[i], i + 1, batch.length);
            }
        } finally {
            zone.style.pointerEvents = '';
            zone.style.opacity = '';
            progWrap.classList.add('d-none');
        }
    }

    function uploadOne(file, current, total) {
        return new Promise(resolve => {
            const fd = new FormData();
            fd.append('_token',            csrfToken);
            fd.append('documentable_type', modelType);
            fd.append('documentable_id',   modelId);
            fd.append('document_type',     zone.dataset.documentType || 'document');
            if (labelInput) fd.append('label', labelInput.value);
            fd.append('files[]', file);

            progWrap.classList.remove('d-none');
            progBar.style.width = '0%';
            progText.textContent = (total > 1 ? `File ${current} of ${total} — ` : '') + file.name;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) {
                    const pct = Math.round(e.loaded / e.total * 100);
                    progBar.style.width = pct + '%';
                    progText.textContent = (total > 1 ? `File ${current} of ${total} — ` : '') + file.name + ' (' + pct + '%)';
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            resp.documents.forEach(d => appendCard(d));
                            updateCount();
                            if (current === total && labelInput) labelInput.value = '';
                        } else {
                            showUploadError(file.name, 'Upload failed. Please try again.');
                        }
                    } catch (_) {
                        showUploadError(file.name, 'Unexpected server response.');
                    }
                } else {
                    try {
                        const err = JSON.parse(xhr.responseText);
                        const msg = err.errors
                            ? Object.values(err.errors).flat().join('; ')
                            : (err.message ?? ('HTTP ' + xhr.status));
                        showUploadError(file.name, msg);
                    } catch (_) {
                        showUploadError(file.name, 'HTTP ' + xhr.status);
                    }
                }
                resolve();
            });

            xhr.addEventListener('error', () => {
                showUploadError(file.name, 'Network error. Please try again.');
                resolve();
            });

            xhr.send(fd);
        });
    }

    function showUploadError(filename, msg) {
        const errDiv = document.createElement('div');
        errDiv.className = 'alert alert-danger alert-dismissible py-2 small mt-2';
        errDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>'
            + '<strong>' + filename + '</strong>: ' + msg
            + ' <button type="button" class="btn-close btn-sm" onclick="this.closest(\'.alert\').remove()"></button>';
        progWrap.insertAdjacentElement('afterend', errDiv);
        setTimeout(() => { if (errDiv.parentNode) errDiv.remove(); }, 8000);
    }

    // ── Append card ───────────────────────────────────────────────────────
    function appendCard(d) {
        const emptyEl = document.getElementById(uid + '_empty');
        if (emptyEl) emptyEl.remove();

        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        col.id = 'doc_col_' + d.id;
        col.innerHTML = buildCardHtml(d);
        grid.appendChild(col);
    }

    function buildCardHtml(d) {
        const thumb = d.is_image
            ? `<img src="${d.preview_url}" class="card-img-top object-fit-cover" style="height:100px;" loading="lazy">`
            : `<div class="d-flex align-items-center justify-content-center bg-light" style="height:100px;">
                  <i class="bi ${d.icon} fs-1 ${d.icon_color}"></i>
               </div>`;
        const previewBtn = d.is_previewable
            ? `<button class="btn btn-outline-primary btn-sm dm-preview-btn flex-fill"
                        data-url="${d.preview_url}"
                        data-download="${d.download_url}"
                        data-name="${d.original_name}"
                        data-mime="${d.mime_type}"
                        data-is-image="${d.is_image ? '1' : '0'}"
                        data-is-pdf="${d.is_pdf ? '1' : '0'}"
                        data-is-office="${(d.mime_type && (d.mime_type.includes('word')||d.mime_type.includes('spreadsheet')||d.mime_type.includes('presentation')||d.mime_type.includes('excel')||d.mime_type.includes('msword')||d.mime_type.includes('ms-excel'))) ? '1' : '0'}">
                  <i class="bi bi-eye"></i>
               </button>`
            : '';
        return `
            <div class="card shadow-sm h-100">
                ${thumb}
                <div class="card-body p-2">
                    <div class="small fw-semibold text-truncate" title="${d.original_name}">${d.original_name}</div>
                    <div class="text-muted" style="font-size:11px;">${d.formatted_size}</div>
                </div>
                <div class="card-footer p-1 d-flex gap-1">
                    ${previewBtn}
                    <a href="${d.download_url}" class="btn btn-outline-secondary btn-sm flex-fill" download>
                        <i class="bi bi-download"></i>
                    </a>
                    <button class="btn btn-outline-danger btn-sm dm-delete-btn"
                            data-url="${d.destroy_url}" data-col="doc_col_${d.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>`;
    }

    // ── Delete ────────────────────────────────────────────────────────────
    document.addEventListener('click', e => {
        const btn = e.target.closest('.dm-delete-btn');
        if (!btn || !btn.closest('#' + uid + '_grid')) return;

        confirmAction('Remove this file?', () => {
            fetch(btn.dataset.url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }).then(r => r.json()).then(resp => {
                if (resp.success) {
                    const col = document.getElementById(btn.dataset.col);
                    if (col) col.remove();
                    updateCount();
                    if (!grid.children.length) {
                        const empty = document.createElement('div');
                        empty.id = uid + '_empty';
                        empty.className = 'text-center text-muted py-3 small';
                        empty.innerHTML = '<i class="bi bi-folder2-open fs-3 d-block mb-1"></i>No files attached yet.';
                        document.getElementById(uid + '_list').prepend(empty);
                    }
                }
            });
        }, { title: 'Remove File', confirmClass: 'btn-danger', confirmLabel: 'Remove' });
    });

    // ── Preview ───────────────────────────────────────────────────────────
    document.addEventListener('click', e => {
        const btn = e.target.closest('.dm-preview-btn');
        if (!btn) return;

        const modal   = new bootstrap.Modal(document.getElementById('docPreviewModal'));
        const body    = document.getElementById('docPreviewBody');
        const title   = document.getElementById('docPreviewTitle');
        const dlBtn   = document.getElementById('docPreviewDownload');

        title.innerHTML = '<i class="bi bi-eye me-2"></i>' + btn.dataset.name;
        dlBtn.href      = btn.dataset.download;
        body.innerHTML  = '<div class="spinner-border text-primary"></div>';

        modal.show();

        const isImage  = btn.dataset.isImage  === '1';
        const isPdf    = btn.dataset.isPdf    === '1' || (btn.dataset.mime && btn.dataset.mime.includes('pdf'));
        const isOffice = btn.dataset.isOffice === '1';
        const mime     = btn.dataset.mime;
        const url      = btn.dataset.url;

        if (isImage) {
            body.innerHTML = `<img src="${url}" class="img-fluid" style="max-height:78vh;">`;
        } else if (isPdf) {
            body.innerHTML = `
                <div style="display:flex;flex-direction:column;height:78vh;">
                    <div class="d-flex justify-content-end align-items-center gap-2 px-3 py-1 bg-light border-bottom small">
                        <a href="${url}" target="_blank" class="text-decoration-none">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open in new tab
                        </a>
                        <a href="${btn.dataset.download}" download class="text-decoration-none text-secondary">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                    <iframe src="${url}" style="flex:1;border:0;" allowfullscreen></iframe>
                </div>`;
        } else if (isOffice) {
            const encoded = encodeURIComponent(window.location.origin + url);
            body.innerHTML = `<iframe src="https://view.officeapps.live.com/op/view.aspx?src=${encoded}"
                                       style="width:100%;height:78vh;border:0;"></iframe>`;
        } else {
            body.innerHTML = `<div class="text-center p-5 text-muted">
                <i class="bi bi-file-earmark fs-1 d-block mb-2"></i>
                Preview not available for this file type.<br>
                <a href="${btn.dataset.download}" class="btn btn-primary mt-3" download>
                    <i class="bi bi-download me-1"></i>Download
                </a></div>`;
        }
    });

    function updateCount() {
        const n = grid.children.length;
        countBadge.textContent = n + ' file(s)';
    }
})();
</script>
@endpush
