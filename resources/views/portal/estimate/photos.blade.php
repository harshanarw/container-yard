@extends('portal.layout')

@section('title', 'Survey Photos — ' . $estimate->estimate_no)

@push('head')
<style>
  .photo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
  .photo-thumb {
    cursor:pointer; border-radius:10px; overflow:hidden;
    aspect-ratio:1/1; background:#e9ecef;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
    transition:transform .18s, box-shadow .18s;
    position:relative;
  }
  .photo-thumb:hover { transform:translateY(-3px); box-shadow:0 6px 18px rgba(0,0,0,.14); }
  .photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
  .photo-thumb .overlay {
    position:absolute; inset:0; background:rgba(0,0,0,0);
    display:flex; align-items:center; justify-content:center;
    transition:background .18s;
  }
  .photo-thumb:hover .overlay { background:rgba(0,0,0,.28); }
  .photo-thumb .overlay i { color:#fff; font-size:1.8rem; opacity:0; transition:opacity .18s; }
  .photo-thumb:hover .overlay i { opacity:1; }
  .count-pill {
    display:inline-flex; align-items:center; gap:6px;
    background:#dbeafe; color:#1d4ed8; border-radius:20px;
    padding:3px 14px; font-size:.82rem; font-weight:600;
  }
  #lightboxImg { max-width:100%; max-height:80vh; object-fit:contain; border-radius:4px; }
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
        <span class="count-pill">
          <i class="bi bi-camera"></i> {{ $totalCount }} photo{{ $totalCount !== 1 ? 's' : '' }}
        </span>
      @endif
    </p>
  </div>
  <a href="{{ route('portal.estimate.show', $token) }}" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Estimate
  </a>
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
  <div class="photo-thumb"
       data-src="{{ route('portal.estimate.photo.view', [$token, $doc->id]) }}"
       data-caption="{{ $doc->original_name }}"
       data-index="{{ $loop->index }}">
    <img src="{{ route('portal.estimate.photo.view', [$token, $doc->id]) }}"
         alt="{{ $doc->original_name }}"
         loading="lazy">
    <div class="overlay"><i class="bi bi-zoom-in"></i></div>
  </div>
  @endforeach

  {{-- Legacy InquiryPhoto records --}}
  @foreach($legacyPhotos as $photo)
  <div class="photo-thumb"
       data-src="{{ $photo->photo_url }}"
       data-caption="Survey Photo #{{ $loop->iteration }}"
       data-index="{{ $documents->count() + $loop->index }}">
    <img src="{{ $photo->photo_url }}" alt="Survey Photo" loading="lazy">
    <div class="overlay"><i class="bi bi-zoom-in"></i></div>
  </div>
  @endforeach

</div>

{{-- ── Lightbox modal ── --}}
<div class="modal fade" id="lightboxModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="background:#111;border:none;">
      <div class="modal-header border-0 pb-0 px-3 pt-2">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <button class="btn btn-sm btn-dark" id="prevBtn"><i class="bi bi-chevron-left"></i></button>
          <button class="btn btn-sm btn-dark" id="nextBtn"><i class="bi bi-chevron-right"></i></button>
          <span class="text-white small" id="lightboxCaption" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          <span class="text-secondary small" id="lightboxCounter"></span>
        </div>
        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="lightboxImg" src="" alt="">
      </div>
    </div>
  </div>
</div>

@endif

@endsection

@push('scripts')
<script>
(function () {
  const thumbs = Array.from(document.querySelectorAll('.photo-thumb'));
  if (!thumbs.length) return;

  let current = 0;

  function open(index) {
    current = index;
    const el = thumbs[index];
    document.getElementById('lightboxImg').src       = el.dataset.src;
    document.getElementById('lightboxCaption').textContent = el.dataset.caption;
    document.getElementById('lightboxCounter').textContent = (index + 1) + ' / ' + thumbs.length;
    bootstrap.Modal.getOrCreate(document.getElementById('lightboxModal')).show();
  }

  thumbs.forEach(function (el, i) {
    el.addEventListener('click', function () { open(i); });
  });

  document.getElementById('prevBtn').addEventListener('click', function () {
    open((current - 1 + thumbs.length) % thumbs.length);
  });
  document.getElementById('nextBtn').addEventListener('click', function () {
    open((current + 1) % thumbs.length);
  });

  // Keyboard navigation
  document.addEventListener('keydown', function (e) {
    const modal = document.getElementById('lightboxModal');
    if (!modal.classList.contains('show')) return;
    if (e.key === 'ArrowLeft')  document.getElementById('prevBtn').click();
    if (e.key === 'ArrowRight') document.getElementById('nextBtn').click();
  });
})();
</script>
@endpush
