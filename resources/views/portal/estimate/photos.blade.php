@extends('portal.layout')

@section('title', 'Survey Photos — ' . $estimate->estimate_no)

@push('head')
<style>
  /* Grid */
  .photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; }

  /* Card */
  .photo-card { border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.08); transition:box-shadow .18s; }
  .photo-card:hover { box-shadow:0 6px 18px rgba(0,0,0,.14); }

  /* Thumbnail */
  .photo-thumb {
    cursor:pointer; aspect-ratio:1/1; background:#e9ecef;
    position:relative; overflow:hidden;
  }
  .photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .2s; }
  .photo-thumb:hover img { transform:scale(1.05); }
  .photo-thumb .zoom-overlay {
    position:absolute; inset:0; background:rgba(0,0,0,0);
    display:flex; align-items:center; justify-content:center;
    transition:background .18s;
  }
  .photo-thumb:hover .zoom-overlay { background:rgba(0,0,0,.32); }
  .photo-thumb .zoom-overlay i { color:#fff; font-size:1.8rem; opacity:0; transition:opacity .18s; }
  .photo-thumb:hover .zoom-overlay i { opacity:1; }

  /* Card footer bar */
  .photo-bar {
    display:flex; align-items:center; gap:6px;
    padding:6px 8px; border-top:1px solid #f0f0f0; background:#fff;
  }
  .photo-bar .photo-name { flex:1; font-size:.72rem; color:#6c757d; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .photo-bar .btn-dl {
    flex-shrink:0; width:28px; height:28px; border-radius:6px; border:1px solid #dee2e6;
    background:#f8f9fa; color:#495057; display:inline-flex; align-items:center; justify-content:center;
    font-size:.8rem; text-decoration:none; transition:background .15s, color .15s;
  }
  .photo-bar .btn-dl:hover { background:#0d6efd; color:#fff; border-color:#0d6efd; }

  /* Count pill */
  .count-pill {
    display:inline-flex; align-items:center; gap:5px;
    background:#dbeafe; color:#1d4ed8; border-radius:20px;
    padding:3px 12px; font-size:.8rem; font-weight:600;
  }

  /* Lightbox */
  #lightboxModal .modal-content { background:#0d0d0d; border:none; border-radius:12px; }
  #lightboxModal .modal-body    { padding:0; min-height:200px; display:flex; align-items:center; justify-content:center; }
  #lightboxImg  { max-width:100%; max-height:78vh; object-fit:contain; display:block; margin:0 auto; border-radius:4px; }
  .lb-btn {
    width:40px; height:40px; border-radius:50%; border:none;
    background:rgba(255,255,255,.12); color:#fff; font-size:1rem;
    display:inline-flex; align-items:center; justify-content:center;
    transition:background .15s; cursor:pointer; flex-shrink:0; text-decoration:none;
  }
  .lb-btn:hover  { background:rgba(255,255,255,.28); color:#fff; }
  .lb-btn:active { background:rgba(255,255,255,.45); }
  .lb-spinner { display:none; color:#aaa; flex-shrink:0; }
</style>
@endpush

@section('content')

{{-- ── Page header ── --}}
<div class="d-flex align-items-start justify-content-between mb-4 mt-2 flex-wrap gap-2">
  <div>
    <h5 class="mb-1 fw-bold"><i class="bi bi-images me-2 text-primary"></i>Survey Photos</h5>
    <p class="text-muted small mb-0">
      Estimate&nbsp;<strong>{{ $estimate->estimate_no }}</strong>
      &nbsp;·&nbsp;Container&nbsp;<strong>{{ $estimate->container_no }}</strong>
      @if($totalCount > 0)
        &nbsp;·&nbsp;
        <span class="count-pill"><i class="bi bi-camera"></i> {{ $totalCount }} photo{{ $totalCount !== 1 ? 's' : '' }}</span>
      @endif
    </p>
  </div>

  <div class="d-flex gap-2 flex-wrap">
    @if($totalCount > 0)
    <a href="{{ route('portal.estimate.photos.download-all', $token) }}"
       class="btn btn-primary btn-sm">
      <i class="bi bi-file-zip me-1"></i>Download All (ZIP)
    </a>
    @endif
    <a href="{{ route('portal.estimate.show', $token) }}" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Estimate
    </a>
  </div>
</div>

@if($totalCount === 0)

{{-- ── Empty state ── --}}
<div class="card shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-images" style="font-size:3.5rem;opacity:.25;display:block;margin-bottom:16px;"></i>
    <p class="mb-0">No survey photos have been uploaded for this estimate.</p>
    <p class="small mt-1">Please contact the depot if you expected to see images here.</p>
  </div>
</div>

@else

{{-- ── Photo grid ── --}}
<div class="photo-grid">

  {{-- Document-manager based photos --}}
  @foreach($documents as $doc)
  @php $downloadUrl = route('portal.estimate.photo.download', [$token, $doc->id]); @endphp
  <div class="photo-card">
    <div class="photo-thumb"
         data-src="{{ route('portal.estimate.photo.view', [$token, $doc->id]) }}"
         data-download="{{ $downloadUrl }}"
         data-caption="{{ $doc->original_name }}">
      <img src="{{ route('portal.estimate.photo.view', [$token, $doc->id]) }}"
           alt="{{ $doc->original_name }}" loading="lazy">
      <div class="zoom-overlay"><i class="bi bi-zoom-in"></i></div>
    </div>
    <div class="photo-bar">
      <span class="photo-name" title="{{ $doc->original_name }}">{{ $doc->original_name }}</span>
      <a href="{{ $downloadUrl }}" class="btn-dl" title="Download photo" download>
        <i class="bi bi-download"></i>
      </a>
    </div>
  </div>
  @endforeach

  {{-- Legacy InquiryPhoto records --}}
  @foreach($legacyPhotos as $i => $photo)
  @php $label = 'Survey Photo #' . ($documents->count() + $loop->iteration); @endphp
  <div class="photo-card">
    <div class="photo-thumb"
         data-src="{{ $photo->photo_url }}"
         data-download="{{ $photo->photo_url }}"
         data-caption="{{ $label }}">
      <img src="{{ $photo->photo_url }}" alt="{{ $label }}" loading="lazy">
      <div class="zoom-overlay"><i class="bi bi-zoom-in"></i></div>
    </div>
    <div class="photo-bar">
      <span class="photo-name" title="{{ $label }}">{{ $label }}</span>
      <a href="{{ $photo->photo_url }}" class="btn-dl" title="Download photo" download>
        <i class="bi bi-download"></i>
      </a>
    </div>
  </div>
  @endforeach

</div>

{{-- ── Lightbox modal ── --}}
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header border-0 px-3 py-2" style="background:#0d0d0d;">
        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
          <button class="lb-btn" id="prevBtn" title="Previous (←)"><i class="bi bi-chevron-left"></i></button>
          <button class="lb-btn" id="nextBtn" title="Next (→)"><i class="bi bi-chevron-right"></i></button>
          <span class="text-white small ms-1" id="lightboxCaption"
                style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          <span class="text-secondary small" id="lightboxCounter"></span>
          <div class="lb-spinner spinner-border spinner-border-sm" role="status">
            <span class="visually-hidden">Loading…</span>
          </div>
          <a href="#" id="lbDownloadBtn" class="lb-btn" title="Download this photo" download>
            <i class="bi bi-download"></i>
          </a>
        </div>
        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" style="background:#0d0d0d;">
        <img id="lightboxImg" src="" alt=""
             style="max-width:100%;max-height:78vh;object-fit:contain;display:block;margin:0 auto;border-radius:4px;">
      </div>

    </div>
  </div>
</div>

@endif

@endsection

@push('scripts')
<script>
(function () {
  const thumbs      = Array.from(document.querySelectorAll('.photo-thumb'));
  if (!thumbs.length) return;

  const modalEl     = document.getElementById('lightboxModal');
  const imgEl       = document.getElementById('lightboxImg');
  const captionEl   = document.getElementById('lightboxCaption');
  const counterEl   = document.getElementById('lightboxCounter');
  const spinnerEl   = document.querySelector('.lb-spinner');
  const downloadBtn = document.getElementById('lbDownloadBtn');
  const modal       = bootstrap.Modal.getOrCreateInstance(modalEl);

  let current = 0;

  function loadImage(index) {
    current = index;
    const el = thumbs[index];

    imgEl.style.opacity     = '0.15';
    spinnerEl.style.display = 'inline-block';
    captionEl.textContent   = el.dataset.caption;
    counterEl.textContent   = (index + 1) + ' / ' + thumbs.length;

    // Update lightbox download button
    downloadBtn.href = el.dataset.download || el.dataset.src;

    const tmp   = new Image();
    tmp.onload  = function () { imgEl.src = tmp.src; imgEl.style.opacity = '1'; spinnerEl.style.display = 'none'; };
    tmp.onerror = function () { imgEl.style.opacity = '1'; spinnerEl.style.display = 'none'; };
    tmp.src     = el.dataset.src;
  }

  function open(index) { loadImage(index); modal.show(); }
  function navigate(dir) { loadImage((current + dir + thumbs.length) % thumbs.length); }

  thumbs.forEach(function (el, i) { el.addEventListener('click', function () { open(i); }); });

  document.getElementById('prevBtn').addEventListener('click', function () { navigate(-1); });
  document.getElementById('nextBtn').addEventListener('click', function () { navigate(+1); });

  document.addEventListener('keydown', function (e) {
    if (!modalEl.classList.contains('show')) return;
    if (e.key === 'ArrowLeft')  navigate(-1);
    if (e.key === 'ArrowRight') navigate(+1);
    if (e.key === 'Escape')     modal.hide();
  });

  if (thumbs.length === 1) {
    document.getElementById('prevBtn').style.display = 'none';
    document.getElementById('nextBtn').style.display = 'none';
  }
})();
</script>
@endpush
