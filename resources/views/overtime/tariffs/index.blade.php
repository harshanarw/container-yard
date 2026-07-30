@extends('layouts.app')

@section('title', 'OT Tariff')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item active">OT Tariff</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-cash-coin me-2 text-primary"></i>OT Tariff Versions</h4>
        <p class="text-muted mb-0 small">
            Effective-dated overtime rates. A rate revision becomes a <strong>new version</strong> — versions that have
            issued receipts stay frozen so printed receipts keep the rate they were billed at.
        </p>
    </div>
    @can('ot.settings.edit')
    <a href="{{ route('overtime.tariffs.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Version
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if(! $effective)
<div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-octagon"></i>
    <div>No version is effective today. Out-of-hours movements will resolve as <em>unconfigured</em>, which blocks
        the gate-in while enforcement is on.</div>
</div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Version</th>
                        <th>Name</th>
                        <th>Effective</th>
                        <th class="text-center">Currency</th>
                        <th class="text-center">Rules</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($versions as $v)
                <tr class="{{ $v->active ? '' : 'opacity-50' }}">
                    <td class="ps-3">
                        <a href="{{ route('overtime.tariffs.show', $v) }}" class="font-monospace small fw-semibold">{{ $v->version_code }}</a>
                        @if($effective && $effective->is($v))<span class="badge bg-success ms-1">In use today</span>@endif
                        @if($v->isLocked())<i class="bi bi-lock-fill text-muted ms-1" title="{{ $v->lockReason() }}"></i>@endif
                    </td>
                    <td class="small">
                        {{ $v->name }}
                        @if($v->source_reference)<div class="text-muted" style="font-size:.72rem">{{ $v->source_reference }}</div>@endif
                    </td>
                    <td class="small text-muted text-nowrap">
                        {{ $v->effective_from->format('d M Y') }}
                        @if($v->effective_to) → {{ $v->effective_to->format('d M Y') }} @else <span class="text-body-secondary">onwards</span> @endif
                    </td>
                    <td class="text-center small">{{ $v->currency }}</td>
                    <td class="text-center small">{{ $v->rules_count }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ ['draft' => 'secondary', 'approved' => 'info', 'active' => 'success', 'retired' => 'dark'][$v->approval_status] ?? 'secondary' }}">
                            {{ $v->statusLabel() }}
                        </span>
                    </td>
                    <td class="text-end pe-3 text-nowrap">
                        <a href="{{ route('overtime.tariffs.show', $v) }}" class="btn btn-outline-secondary btn-xs py-0 px-1" title="Open"><i class="bi bi-box-arrow-in-right"></i></a>
                        @can('ot.settings.approve')
                            @if($v->approval_status !== 'active' && $v->approval_status !== 'retired')
                            <form method="POST" action="{{ route('overtime.tariffs.activate', $v) }}" class="d-inline"
                                  data-confirm="Activate {{ $v->version_code }}? Any older open-ended active version will be closed the day before this one starts."
                                  data-confirm-title="Activate Tariff Version" data-confirm-class="btn-success" data-confirm-label="Activate">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-success btn-xs py-0 px-1" title="Activate"><i class="bi bi-check2-circle"></i></button>
                            </form>
                            @endif
                            @if($v->approval_status === 'active')
                            <form method="POST" action="{{ route('overtime.tariffs.retire', $v) }}" class="d-inline"
                                  data-confirm="Retire {{ $v->version_code }}? It will no longer be used to price new receipts."
                                  data-confirm-title="Retire Tariff Version" data-confirm-class="btn-warning" data-confirm-label="Retire">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-warning btn-xs py-0 px-1" title="Retire"><i class="bi bi-archive"></i></button>
                            </form>
                            @endif
                        @endcan
                        @can('ot.settings.edit')
                            @unless($v->receipts()->exists())
                            <form method="POST" action="{{ route('overtime.tariffs.destroy', $v) }}" class="d-inline"
                                  data-confirm="Delete tariff version {{ $v->version_code }} and all its rate rules? This cannot be undone."
                                  data-confirm-title="Delete Tariff Version" data-confirm-class="btn-danger" data-confirm-label="Delete">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-xs py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                            @endunless
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No tariff versions defined yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
