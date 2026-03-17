@extends('layouts.app')

@section('title', 'Survey — ' . $inquiry->inquiry_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}" class="text-decoration-none">Container Surveys</a></li>
    <li class="breadcrumb-item active">{{ $inquiry->inquiry_no }}</li>
@endsection

@push('styles')
<style>
    .photo-thumb { cursor: pointer; transition: transform .15s, box-shadow .15s; }
    .photo-thumb:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.15) !important; }
    .checklist-item.checked  { color: #198754; }
    .checklist-item.unchecked { color: #adb5bd; }
    /* Lightbox overlay */
    #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.88);
                z-index:9999; align-items:center; justify-content:center; }
    #lightbox.open { display:flex; }
    #lightbox img { max-width:90vw; max-height:90vh; border-radius:8px; object-fit:contain; }
    #lightbox .lb-close { position:absolute; top:16px; right:20px; font-size:2rem;
                          color:#fff; cursor:pointer; line-height:1; }
    #lightbox .lb-nav { position:absolute; top:50%; transform:translateY(-50%);
                        font-size:2.5rem; color:#fff; cursor:pointer; padding:0 12px; user-select:none; }
    #lightbox .lb-prev { left:8px; }
    #lightbox .lb-next { right:8px; }
    #lightbox .lb-caption { position:absolute; bottom:16px; color:#ccc; font-size:.85rem; }
</style>
@endpush

@section('content')

@php
    $statusColor = match($inquiry->status) {
        'open'          => 'warning',
        'in_progress'   => 'primary',
        'estimate_sent' => 'info',
        'approved'      => 'success',
        'closed'        => 'dark',
        default         => 'secondary',
    };
    $statusLabel = match($inquiry->status) {
        'open'          => 'Open',
        'in_progress'   => 'In Progress',
        'estimate_sent' => 'Estimate Sent',
        'approved'      => 'Approved',
        'closed'        => 'Closed',
        default         => ucfirst($inquiry->status),
    };
    $priorityColor = match($inquiry->priority) {
        'urgent'   => 'warning',
        'critical' => 'danger',
        default    => 'secondary',
    };
    $conditionColor = match($inquiry->overall_condition) {
        'excellent' => 'success',
        'good'      => 'info',
        'fair'      => 'warning',
        'poor'      => 'danger',
        'condemned' => 'dark',
        default     => 'secondary',
    };
@endphp

{{-- Page Header --}}
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-card-checklist me-2 text-primary"></i>
            {{ $inquiry->inquiry_no }}
            <span class="badge bg-{{ $statusColor }} ms-2" style="font-size:.7rem;vertical-align:middle;">{{ $statusLabel }}</span>
            <span class="badge bg-{{ $priorityColor }}-subtle text-{{ $priorityColor }} ms-1" style="font-size:.65rem;vertical-align:middle;">
                {{ ucfirst($inquiry->priority) }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            Created {{ $inquiry->created_at->format('d M Y, H:i') }}
            &nbsp;·&nbsp; Last updated {{ $inquiry->updated_at->diffForHumans() }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(!$inquiry->estimate)
        <a href="{{ route('estimates.create', ['inquiry_id' => $inquiry->id]) }}"
           class="btn btn-warning btn-sm">
            <i class="bi bi-tools me-1"></i>Create Estimate
        </a>
        @endif
        <a href="{{ route('surveys.edit', $inquiry) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">

    {{-- ════════════ LEFT: main content ════════════ --}}
    <div class="col-lg-8">

        {{-- Container & Survey Details --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-box-seam me-2 text-primary"></i>Container & Survey Details
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Container Number</div>
                        <div class="font-monospace fw-bold fs-6">{{ $inquiry->container_no }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Size / Type</div>
                        <div>
                            <span class="badge bg-light border text-dark me-1">{{ $inquiry->size }}ft</span>
                            <span class="badge bg-info-subtle text-info">{{ $inquiry->type_code }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Customer / Owner</div>
                        <div class="fw-semibold">
                            @if($inquiry->customer)
                                <span class="badge bg-dark text-white me-1">{{ $inquiry->customer->code }}</span>
                                {{ $inquiry->customer->name }}
                            @else —
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Survey Type</div>
                        <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $inquiry->inquiry_type)) }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Assigned Inspector</div>
                        <div class="fw-semibold">{{ $inquiry->inspector?->name ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Inspection Date</div>
                        <div class="fw-semibold">
                            {{ $inquiry->inspection_date ? $inquiry->inspection_date->format('d M Y') : '—' }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Gate-In Reference</div>
                        <div class="fw-semibold font-monospace">{{ $inquiry->gate_in_ref ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Estimated Repair Cost</div>
                        <div class="fw-semibold">
                            @if($inquiry->estimated_repair_cost)
                                LKR {{ number_format($inquiry->estimated_repair_cost, 2) }}
                            @else —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Damage Assessment --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small d-flex align-items-center justify-content-between">
                <span><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Damage Assessment</span>
                <span class="badge bg-warning-subtle text-warning">{{ $inquiry->damages->count() }} item(s)</span>
            </div>
            @if($inquiry->damages->isEmpty())
            <div class="card-body text-center text-muted py-4 small">
                <i class="bi bi-shield-check fs-3 d-block mb-1 text-success"></i>No damages recorded.
            </div>
            @else
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Location</th>
                                <th>Damage Type</th>
                                <th>Severity</th>
                                <th>Dimensions</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inquiry->damages as $i => $dmg)
                            <tr>
                                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ ucwords(str_replace('_', ' ', $dmg->location)) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $dmg->damage_type)) }}</td>
                                <td>
                                    @php
                                        $sc = match($dmg->severity) {
                                            'minor'    => 'success',
                                            'moderate' => 'warning',
                                            'severe'   => 'danger',
                                            default    => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }}">
                                        {{ ucfirst($dmg->severity) }}
                                    </span>
                                </td>
                                <td class="font-monospace text-muted">{{ $dmg->dimensions ?? '—' }}</td>
                                <td class="text-muted">{{ $dmg->description ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        {{-- Inspector's Findings --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Inspector's Findings
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Overall Condition</div>
                        @if($inquiry->overall_condition)
                        <span class="badge bg-{{ $conditionColor }} px-3 py-2" style="font-size:.8rem;">
                            {{ ucfirst($inquiry->overall_condition) }}
                        </span>
                        @else
                        <span class="text-muted">— Not assessed</span>
                        @endif
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Recommended Action</div>
                        <div class="fw-semibold">
                            {{ $inquiry->recommended_action
                                ? ucwords(str_replace('_', ' ', $inquiry->recommended_action))
                                : '—' }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-1">Detailed Findings</div>
                        @if($inquiry->findings)
                        <div class="bg-light rounded p-3" style="white-space:pre-wrap;font-size:.85rem;">{{ $inquiry->findings }}</div>
                        @else
                        <span class="text-muted small">— No findings recorded</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Photo Gallery --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small d-flex align-items-center justify-content-between">
                <span><i class="bi bi-images me-2 text-primary"></i>Photo Evidence</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $inquiry->photos->count() }} photo(s)</span>
            </div>
            <div class="card-body">
                @if($inquiry->photos->isEmpty())
                <div class="text-center text-muted py-3 small">
                    <i class="bi bi-camera fs-3 d-block mb-1"></i>No photos uploaded.
                </div>
                @else
                <div class="row g-2">
                    @foreach($inquiry->photos as $idx => $photo)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card border shadow-sm photo-thumb"
                             style="overflow:hidden;"
                             data-idx="{{ $idx }}"
                             onclick="openLightbox({{ $idx }})">
                            <img src="{{ asset('storage/' . $photo->photo_path) }}"
                                 class="card-img-top"
                                 style="height:110px;object-fit:cover;"
                                 alt="Photo {{ $idx + 1 }}"
                                 onerror="this.src='https://via.placeholder.com/200x110?text=Photo'">
                            <div class="card-body p-1 text-center">
                                <small class="text-muted" style="font-size:.68rem;">
                                    Photo {{ $idx + 1 }}
                                    @if($photo->created_at)
                                        &nbsp;·&nbsp; {{ $photo->created_at->format('d M') }}
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

    </div>{{-- /col-lg-8 --}}

    {{-- ════════════ RIGHT: sidebar ════════════ --}}
    <div class="col-lg-4">

        {{-- Status & Quick Actions --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-lightning me-2 text-primary"></i>Quick Actions
            </div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('surveys.edit', $inquiry) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-2"></i>Edit Survey
                </a>
                @if(!$inquiry->estimate)
                <a href="{{ route('estimates.create', ['inquiry_id' => $inquiry->id]) }}"
                   class="btn btn-warning btn-sm">
                    <i class="bi bi-tools me-2"></i>Create Repair Estimate
                </a>
                @else
                <a href="{{ route('estimates.show', $inquiry->estimate) }}"
                   class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-receipt me-2"></i>View Estimate ({{ $inquiry->estimate->estimate_no }})
                </a>
                @endif
                <button type="button" class="btn btn-outline-danger btn-sm"
                        data-bs-toggle="modal" data-bs-target="#modalDelete">
                    <i class="bi bi-trash me-2"></i>Delete Survey
                </button>
            </div>
        </div>

        {{-- Linked Estimate --}}
        @if($inquiry->estimate)
        <div class="card content-card mb-4 border-warning">
            <div class="card-header py-2 fw-semibold small bg-warning-subtle">
                <i class="bi bi-tools me-2 text-warning"></i>Linked Repair Estimate
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Estimate No.</span>
                    <a href="{{ route('estimates.show', $inquiry->estimate) }}" class="fw-semibold font-monospace text-decoration-none">
                        {{ $inquiry->estimate->estimate_no }}
                    </a>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status</span>
                    <span class="badge bg-info-subtle text-info">{{ ucfirst($inquiry->estimate->status) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Grand Total</span>
                    <span class="fw-bold">{{ $inquiry->estimate->currency }} {{ number_format($inquiry->estimate->grand_total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Valid Until</span>
                    <span>{{ $inquiry->estimate->valid_until?->format('d M Y') }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Container Info --}}
        @if($inquiry->container)
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-geo-alt me-2 text-primary"></i>Container in Yard
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        <span class="badge bg-success-subtle text-success">
                            {{ ucwords(str_replace('_', ' ', $inquiry->container->status)) }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Location</span>
                        <span class="font-monospace fw-semibold">
                            @if($inquiry->container->location_row)
                                {{ $inquiry->container->location_row }}{{ $inquiry->container->location_bay }}-T{{ $inquiry->container->location_tier }}
                            @else —
                            @endif
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Gate-In Date</span>
                        <span>{{ $inquiry->container->gate_in_date?->format('d M Y') ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Condition</span>
                        <span>{{ ucwords(str_replace('_', ' ', $inquiry->container->condition)) }}</span>
                    </li>
                </ul>
            </div>
        </div>
        @endif

        {{-- Inspection Checklist --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small d-flex align-items-center justify-content-between">
                <span><i class="bi bi-check2-square me-2 text-primary"></i>Inspection Checklist</span>
                @php
                    $checkedCount = $inquiry->checklists->where('is_checked', true)->count();
                    $totalCount   = $inquiry->checklists->count();
                @endphp
                <span class="badge {{ $checkedCount === $totalCount && $totalCount > 0 ? 'bg-success' : 'bg-secondary-subtle text-secondary' }}">
                    {{ $checkedCount }}/{{ $totalCount }}
                </span>
            </div>
            <div class="card-body p-0">
                @if($inquiry->checklists->isEmpty())
                <div class="text-center text-muted py-3 small">No checklist data.</div>
                @else
                <ul class="list-group list-group-flush small">
                    @foreach($inquiry->checklists as $item)
                    <li class="list-group-item py-2 checklist-item {{ $item->is_checked ? 'checked' : 'unchecked' }}">
                        <i class="bi {{ $item->is_checked ? 'bi-check-circle-fill' : 'bi-circle' }} me-2"></i>
                        {{ ucwords(str_replace(['_', '-'], ' ', $item->checklist_item)) }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>

    </div>{{-- /col-lg-4 --}}

</div>

{{-- ══════════ Lightbox ══════════ --}}
<div id="lightbox">
    <span class="lb-close" onclick="closeLightbox()">&times;</span>
    <span class="lb-nav lb-prev" onclick="lbNav(-1)">&#8249;</span>
    <img id="lbImg" src="" alt="">
    <span class="lb-nav lb-next" onclick="lbNav(1)">&#8250;</span>
    <span class="lb-caption" id="lbCaption"></span>
</div>

{{-- ══════════ Delete Modal ══════════ --}}
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="{{ route('surveys.destroy', $inquiry) }}">
                @csrf @method('DELETE')
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Survey</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-2">
                    <p class="mb-0">Delete <strong>{{ $inquiry->inquiry_no }}</strong>?<br>
                    <small class="text-muted">All damages, checklists and photos will be removed. This cannot be undone.</small></p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Lightbox ──────────────────────────────────────────────
    const photos  = @json($inquiry->photos->map(fn($p) => asset('storage/' . $p->photo_path)));
    let lbCurrent = 0;

    function openLightbox(idx) {
        lbCurrent = idx;
        document.getElementById('lbImg').src     = photos[idx];
        document.getElementById('lbCaption').textContent = `Photo ${idx + 1} of ${photos.length}`;
        document.getElementById('lightbox').classList.add('open');
    }

    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
    }

    function lbNav(dir) {
        lbCurrent = (lbCurrent + dir + photos.length) % photos.length;
        openLightbox(lbCurrent);
    }

    // Close on background click
    document.getElementById('lightbox').addEventListener('click', function (e) {
        if (e.target === this) closeLightbox();
    });

    // Keyboard nav
    document.addEventListener('keydown', function (e) {
        if (!document.getElementById('lightbox').classList.contains('open')) return;
        if (e.key === 'ArrowRight') lbNav(1);
        if (e.key === 'ArrowLeft')  lbNav(-1);
        if (e.key === 'Escape')     closeLightbox();
    });
</script>
@endpush
