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
             style="border-color:#adb5bd!important; cursor:pointer; transition:background .2s;">
            <i class="bi bi-cloud-upload fs-3 text-muted d-block mb-1"></i>
            <div class="text-muted small">
                Drag &amp; drop files here, or <span class="text-primary fw-semibold">browse</span>
            </div>
            <div class="text-muted" style="font-size:11px;">
                Max {{ $maxFiles }} files · 20 MB each
                @if($showLabel)
                    <div class="mt-2">
                        <input type="text" id="{{ $uid }}_label" class="form-control form-control-sm"
                               placeholder="Label (optional)" style="max-width:240px;display:inline-block;">
                    </div>
                @endif
            </div>
            <input type="file" id="{{ $uid }}_input" multiple accept="{{ $accept }}"
                   class="d-none">
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
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.background = '#f0f4ff'; });
    zone.addEventListener('dragleave', () => { zone.style.background = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.background = '';
        handleFiles(e.dataTransfer.files);
    });
    input.addEventListener('change', () => handleFiles(input.files));

    function handleFiles(fileList) {
        if (!fileList.length) return;
        const files = Array.from(fileList).slice(0, MAX);
        uploadBatch(files);
        input.value = '';
    }

    // ── Upload ────────────────────────────────────────────────────────────
    function uploadBatch(files) {
        const fd = new FormData();
        fd.append('_token',            csrfToken);
        fd.append('documentable_type', modelType);
        fd.append('documentable_id',   modelId);
        fd.append('document_type',     'photo');
        if (labelInput) fd.append('label', labelInput.value);
        files.forEach(f => fd.append('files[]', f));

        progWrap.classList.remove('d-none');
        progBar.style.width = '0%';
        progText.textContent = 'Uploading…';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                progBar.style.width = pct + '%';
                progText.textContent = 'Uploading… ' + pct + '%';
            }
        });

        xhr.addEventListener('load', () => {
            progWrap.classList.add('d-none');
            if (xhr.status === 200) {
                const resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    resp.documents.forEach(d => appendCard(d));
                    updateCount();
                    if (labelInput) labelInput.value = '';
                } else {
                    alert('Upload failed. Please try again.');
                }
            } else {
                const err = JSON.parse(xhr.responseText);
                alert('Upload error: ' + (err.message ?? xhr.status));
            }
        });

        xhr.addEventListener('error', () => {
            progWrap.classList.add('d-none');
            alert('Network error during upload.');
        });

        xhr.send(fd);
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
                        data-is-office="${(d.mime_type.includes('word')||d.mime_type.includes('spreadsheet')) ? '1' : '0'}">
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
        if (!confirm('Remove this file?')) return;

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
        const isOffice = btn.dataset.isOffice  === '1';
        const mime     = btn.dataset.mime;
        const url      = btn.dataset.url;

        if (isImage) {
            body.innerHTML = `<img src="${url}" class="img-fluid" style="max-height:78vh;">`;
        } else if (mime === 'application/pdf') {
            body.innerHTML = `<iframe src="${url}" style="width:100%;height:78vh;border:0;"></iframe>`;
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
