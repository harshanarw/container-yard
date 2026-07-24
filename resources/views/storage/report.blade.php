@extends('layouts.app')

@section('title', 'Storage Report')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item active">Storage Report</li>
@endsection

@section('content')
@php
    $used = $summary['used'] ?? 0; $limit = $summary['limit'] ?? 0;
    $pct  = $summary['percent'] ?? 0; $level = $summary['level'] ?? 'success';
    $sections = $summary['sections'] ?? collect();
    $mb = fn ($b) => $b >= 1073741824 ? number_format($b / 1073741824, 2) . ' GB' : number_format($b / 1048576, 1) . ' MB';

    $colors = \App\Models\FileAsset::SECTION_COLORS;
    $denom = $limit > 0 ? max($limit, $used) : max($used, 1);
    $segs = []; $cum = 0;
    foreach ($sections as $sec) {
        $p = $sec->bytes / $denom * 100; if ($p <= 0) continue;
        $segs[] = ['color' => $colors[$sec->section] ?? '#6c757d', 'p' => $p, 'start' => $cum];
        $cum += $p;
    }
    $freeBytes = $limit > 0 ? max(0, $limit - $used) : 0;
    $label = fn ($s) => \App\Models\FileAsset::SECTION_LABELS[$s] ?? ucfirst(str_replace('_', ' ', $s));
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-hdd-stack me-2 text-primary"></i>Storage Report</h4>
        <p class="text-muted mb-0 small">File usage across the system, with per-section breakdown and previews.</p>
    </div>
    <a href="{{ route('settings.company.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Storage settings</a>
</div>

<div class="row g-3 mb-3">
    {{-- Overview donut --}}
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-header py-2"><i class="bi bi-pie-chart me-2 text-primary"></i>Overall usage</div>
            <div class="card-body text-center">
                <svg viewBox="0 0 42 42" style="width:170px;height:170px;">
                    <circle cx="21" cy="21" r="15.915" fill="none" stroke="#eef0f2" stroke-width="5"></circle>
                    @foreach($segs as $s)
                    <circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $s['color'] }}" stroke-width="5"
                            stroke-dasharray="{{ round($s['p'], 3) }} {{ round(100 - $s['p'], 3) }}"
                            transform="rotate({{ round($s['start'] * 3.6 - 90, 3) }} 21 21)"></circle>
                    @endforeach
                    <text x="21" y="20" text-anchor="middle" style="font-size:6px;font-weight:700;fill:var(--bs-body-color,#212529);">{{ $limit > 0 ? $pct.'%' : $mb($used) }}</text>
                    <text x="21" y="25" text-anchor="middle" style="font-size:2.6px;fill:#8a9099;">{{ $limit > 0 ? $mb($used).' / '.$mb($limit) : 'used · no limit' }}</text>
                </svg>
                @if($limit > 0 && $pct >= 90)
                <div class="small text-danger mt-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Nearly full</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Per-section breakdown --}}
    <div class="col-lg-8">
        <div class="card content-card h-100">
            <div class="card-header py-2"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Breakdown by section</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Section</th><th class="text-center">Files</th><th class="text-end">Size</th><th style="width:35%;">Share</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($sections as $sec)
                            @php $share = $used > 0 ? round($sec->bytes / $used * 100, 1) : 0; @endphp
                            <tr>
                                <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $colors[$sec->section] ?? '#6c757d' }};" class="me-1"></span>{{ $label($sec->section) }}</td>
                                <td class="text-center">{{ $sec->files }}</td>
                                <td class="text-end fw-semibold">{{ $mb($sec->bytes) }}</td>
                                <td>
                                    <div class="progress" style="height:8px;"><div class="progress-bar" role="progressbar" style="width:{{ $share }}%;background:{{ $colors[$sec->section] ?? '#6c757d' }};"></div></div>
                                    <span class="text-muted" style="font-size:.7rem;">{{ $share }}% of used</span>
                                </td>
                                <td class="text-end"><a href="{{ route('storage.report', ['section' => $sec->section]) }}" class="btn btn-sm btn-outline-primary py-0">View</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No files stored yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('storage.report') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Section</label>
                <select name="section" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(\App\Models\FileAsset::SECTION_LABELS as $key => $lbl)
                        <option value="{{ $key }}" {{ $section === $key ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Uploaded by</label>
                <select name="uploader" class="form-select form-select-sm">
                    <option value="">Anyone</option>
                    @foreach($uploaders as $u)
                        <option value="{{ $u->id }}" {{ (string) $uploader === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Min size (MB)</label>
                <input type="number" step="0.1" min="0" name="min_mb" value="{{ $minMb ?: '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Path contains</label>
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="e.g. guard-captures">
            </div>
            <div class="col-md-8 text-end">
                <a href="{{ route('storage.report') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

{{-- File list --}}
<div class="card content-card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-files me-2 text-primary"></i>Files</span>
        <span class="small text-muted">{{ $files->total() }} file(s){{ $section ? ' · ' . $label($section) : '' }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th></th><th>File</th><th>Section</th><th class="text-end">Size</th><th>Source</th><th>Uploaded</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($files as $f)
                    <tr>
                        <td style="width:52px;">
                            @if($f->is_image)
                            <a href="{{ $f->preview_url }}" target="_blank" title="Preview">
                                <img src="{{ $f->preview_url }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                            </a>
                            @else
                            <i class="bi bi-file-earmark text-muted fs-4"></i>
                            @endif
                        </td>
                        <td class="font-monospace small text-truncate" style="max-width:280px;">{{ basename($f->path) }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary border">{{ $label($f->section) }}</span></td>
                        <td class="text-end">{{ $mb($f->size) }}</td>
                        <td class="small">
                            @php $ref = $f->ownerReference(); @endphp
                            @if($ref)
                                @if($ref['url'])
                                <a href="{{ $ref['url'] }}" class="text-decoration-none" title="{{ class_basename($f->owner_type) }}"><i class="bi bi-box-arrow-up-right me-1" style="font-size:.7rem;"></i>{{ $ref['number'] }}</a>
                                @else
                                <span class="font-monospace">{{ $ref['number'] }}</span>
                                @endif
                            @elseif($f->owner_type)
                                <span class="text-muted">{{ class_basename($f->owner_type) }} #{{ $f->owner_id }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $f->created_at?->format('d M Y') }}</td>
                        <td class="text-end">
                            <a href="{{ $f->preview_url }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Preview"><i class="bi bi-eye"></i></a>
                            <a href="{{ $f->download_url }}" class="btn btn-sm btn-outline-secondary py-0" title="Download"><i class="bi bi-download"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No files match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($files->hasPages())
<div class="mt-3">{{ $files->withQueryString()->links() }}</div>
@endif

@endsection
