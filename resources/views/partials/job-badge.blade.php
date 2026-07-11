{{--
    Reusable Job Number / Job Type indicator.

    Params:
      $job   — App\Models\YardJob|null (with jobType loaded ideally)
      $mode  — 'inline' (compact chips for a page-header subtitle)
             | 'cell'   (compact chip + job no for a table cell)
             | 'card'   (labelled block for an info card)   [default]
      $link  — bool, wrap the job no in a link to the job page [default true]

    Mirrors the canonical rendering in yard/jobs/show.blade.php.
--}}
@php
    $mode = $mode ?? 'card';
    $link = $link ?? true;
    $jt   = $job?->jobType;
    $short = $job?->type_short_code ?: ($jt?->type_short_code);
    $typeName = $jt?->job_type_name ?? $job?->job_type_code;
    $jobUrl = $job ? route('yard.jobs.show', $job) : null;
@endphp

@if($mode === 'cell')
    @if($job)
        @if($short)
            <span class="badge bg-primary-subtle text-primary border font-monospace" style="font-size:.65rem">{{ $short }}</span>
        @endif
        @if($link && $jobUrl)
            <a href="{{ $jobUrl }}" class="text-decoration-none font-monospace small">{{ $job->job_no }}</a>
        @else
            <span class="font-monospace small">{{ $job->job_no }}</span>
        @endif
    @else
        <span class="text-muted">—</span>
    @endif
@elseif($mode === 'inline')
    @if($job)
        @if($short)
            <span class="badge bg-primary-subtle text-primary border font-monospace">{{ $short }}</span>
        @endif
        @if($link && $jobUrl)
            <a href="{{ $jobUrl }}" class="text-decoration-none font-monospace fw-semibold">{{ $job->job_no }}</a>
        @else
            <span class="font-monospace fw-semibold">{{ $job->job_no }}</span>
        @endif
        @if($typeName)<span class="text-muted small">· {{ $typeName }}</span>@endif
    @else
        <span class="badge bg-light text-muted border"><i class="bi bi-link-45deg me-1"></i>No linked job</span>
    @endif
@else
    <div class="row g-3">
        <div class="col-auto">
            <div class="text-muted small">Job Number</div>
            @if($job)
                @if($link && $jobUrl)
                    <a href="{{ $jobUrl }}" class="fw-bold font-monospace text-decoration-none">{{ $job->job_no }}</a>
                @else
                    <div class="fw-bold font-monospace">{{ $job->job_no }}</div>
                @endif
            @else
                <div class="text-muted">—</div>
            @endif
        </div>
        <div class="col-auto">
            <div class="text-muted small">Job Type</div>
            @if($job)
                <div>
                    @if($short)
                        <span class="badge bg-primary-subtle text-primary border font-monospace me-1">{{ $short }}</span>
                    @endif
                    <span class="fw-semibold small">{{ $typeName ?? '—' }}</span>
                </div>
            @else
                <div class="text-muted">—</div>
            @endif
        </div>
    </div>
@endif
